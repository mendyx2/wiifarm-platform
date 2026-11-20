<?php

namespace App\Services\Payments;

use App\Models\Admin;
use App\Models\Payments\DepositMethod;
use App\Models\Payments\ManualDeposit;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use App\Services\Messages\Messages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Deposits by a manual method such as a bank transfer (specification
 * section 6). The member pays outside the platform and uploads proof; an
 * admin checks the bank and approves (the amount goes to the main balance,
 * once) or refuses with a reason the member sees. A deposit is the member's
 * own money, not a sale: nothing is split until it is spent.
 */
class ManualDeposits
{
    /** Proof files are kept on the private disk, never in the public folder. */
    public const DISK = 'local';

    public function __construct(private readonly Ledger $ledger, private readonly Audit $audit) {}

    public function submit(User $member, DepositMethod $method, int $amount, string $reference, UploadedFile $proof, string $formId): ManualDeposit
    {
        if ($done = ManualDeposit::where('form_id', $formId)->first()) {
            return $done;   // the same form sent twice
        }
        if (! $method->is_active) {
            throw new InvalidArgumentException('This way of depositing is not available.');
        }
        if ($amount < $method->min_amount || $amount > $method->max_amount) {
            throw new InvalidArgumentException(sprintf('Deposit between %s and %s.', Messages::naira($method->min_amount), Messages::naira($method->max_amount)));
        }
        if (trim($reference) === '') {
            throw new InvalidArgumentException('Enter the reference of your payment.');
        }

        $path = $proof->storeAs('deposits/'.$member->id, Str::uuid().'.'.$proof->guessExtension(), self::DISK);
        try {
            return ManualDeposit::create([
                'user_id' => $member->id, 'method_id' => $method->id, 'amount' => $amount,
                'reference' => mb_substr(trim($reference), 0, 120), 'proof_path' => $path,
                'status' => ManualDeposit::PENDING, 'form_id' => $formId,
            ]);
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);   // no orphan file

            throw $e;
        }
    }

    /** The money is in Wiifarm's account: credit the main balance, once. */
    public function approve(ManualDeposit $deposit, Admin $admin): ManualDeposit
    {
        return DB::transaction(function () use ($deposit, $admin) {
            $deposit = $this->pending($deposit);
            $tx = $this->ledger->transfer(
                $this->ledger->systemAccount('external'), $this->ledger->memberAccount($deposit->user, 'main'), $deposit->amount,
                'deposit', 'deposit:manual:'.$deposit->id, "Deposit #{$deposit->id} by {$deposit->method->name}, ref. {$deposit->reference}", $deposit, $admin->id,
            );
            $deposit->forceFill(['status' => ManualDeposit::APPROVED, 'decided_by_admin_id' => $admin->id, 'decided_at' => now(), 'transaction_id' => $tx->id])->save();
            $this->audit->record($admin, 'deposits.approve', $deposit, ['status' => ManualDeposit::PENDING], ['status' => ManualDeposit::APPROVED, 'amount' => $deposit->amount]);
            app(Messages::class)->send($deposit->user, 'deposit.received', ['amount' => Messages::naira($deposit->amount)]);

            return $deposit;
        });
    }

    public function reject(ManualDeposit $deposit, Admin $admin, string $reason): ManualDeposit
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Give a reason. The member sees it.');
        }

        return DB::transaction(function () use ($deposit, $admin, $reason) {
            $deposit = $this->pending($deposit);
            $deposit->forceFill(['status' => ManualDeposit::REJECTED, 'reject_reason' => mb_substr($reason, 0, 250), 'decided_by_admin_id' => $admin->id, 'decided_at' => now()])->save();
            $this->audit->record($admin, 'deposits.reject', $deposit, ['status' => ManualDeposit::PENDING], ['status' => ManualDeposit::REJECTED], $reason);
            app(Messages::class)->send($deposit->user, 'deposit.rejected', ['amount' => Messages::naira($deposit->amount), 'reason' => $reason]);

            return $deposit;
        });
    }

    private function pending(ManualDeposit $deposit): ManualDeposit
    {
        $locked = ManualDeposit::with(['user', 'method'])->lockForUpdate()->findOrFail($deposit->id);
        if ($locked->status !== ManualDeposit::PENDING) {
            throw new InvalidArgumentException('This deposit has already been decided.');
        }

        return $locked;
    }
}
