<?php

namespace App\Services\Payments;

use App\Models\Admin;
use App\Models\Package;
use App\Models\PackageChange;
use App\Models\Payments\EPin;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use App\Services\Network\Registration;
use App\Services\Shop\PickupCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * E-pins (specification section 6). A pin is a code worth a fixed amount
 * that pays one registration, once.
 *
 *  - A member buys pins from their VISA wallet, one package's price each.
 *    The money waits in "E-pins issued and not yet used" until a pin is used.
 *  - An admin creates pins, with a reason; recorded in the audit log.
 *  - A person who has signed up pays their package with a pin. A pin pays a
 *    package costing up to its amount; anything over goes to the new
 *    member's main balance (open decision 32).
 *  - An admin can cancel an unused pin: a bought pin's money goes back to the
 *    buyer's VISA wallet.
 *
 * Codes look like pickup codes ("K7QM-3XPA-R9TD") and are stored encrypted.
 */
class EPins
{
    public const MAX_PER_FORM = 50;

    public function __construct(private readonly Ledger $ledger, private readonly Registration $registration, private readonly Audit $audit) {}

    /** @return Collection<int, EPin> */
    public function buy(User $member, Package $package, int $quantity, string $formId): Collection
    {
        $this->checkQuantity($quantity);
        if (! $member->isActivated() || $member->isBanned()) {
            throw new PaymentException('Only members with a paid package can buy e-pins.');
        }
        if (! $package->is_active) {
            throw new PaymentException("The {$package->name} package is not on offer.");
        }

        return DB::transaction(function () use ($member, $package, $quantity, $formId) {
            $batch = 'member:'.$member->id.':'.$formId;
            if (($done = EPin::where('batch', $batch)->get())->isNotEmpty()) {
                return $done; // the same form sent twice
            }

            $this->ledger->transfer(
                $this->ledger->memberAccount($member, 'visa'), $this->ledger->systemAccount('epins.outstanding'), $package->price * $quantity,
                'epins.buy', 'epins:'.$batch, "{$quantity} e-pin(s) for {$package->name}",
            );

            return $this->issue($quantity, $package, $batch, ['created_by_user_id' => $member->id]);
        });
    }

    /** @return Collection<int, EPin> */
    public function create(Admin $admin, Package $package, int $quantity, string $reason, string $formId): Collection
    {
        $this->checkQuantity($quantity);

        return DB::transaction(function () use ($admin, $package, $quantity, $reason, $formId) {
            $batch = 'admin:'.$admin->id.':'.$formId;
            if (($done = EPin::where('batch', $batch)->get())->isNotEmpty()) {
                return $done;
            }

            $tx = $this->ledger->transfer(
                $this->ledger->systemAccount('adjustments'), $this->ledger->systemAccount('epins.outstanding'), $package->price * $quantity,
                'epins.create', 'epins:'.$batch, "{$quantity} e-pin(s) for {$package->name}, created by an admin: {$reason}", null, $admin->id,
            );
            $pins = $this->issue($quantity, $package, $batch, ['created_by_admin_id' => $admin->id, 'reason' => $reason]);
            $this->audit->record($admin, 'epins.create', null, null, [
                'quantity' => $quantity, 'package' => $package->name, 'amount' => $package->price, 'pins' => $pins->pluck('id')->all(), 'transaction' => $tx->id,
            ], $reason);

            return $pins;
        });
    }

    /** A person who has signed up pays their package with a pin. */
    public function redeem(User $member, string $code, Package $package): PackageChange
    {
        $hash = PickupCode::hash($code);

        return DB::transaction(function () use ($member, $hash, $package) {
            $pin = EPin::where('code_hash', $hash)->lockForUpdate()->first();
            if ($pin === null) {
                throw new PaymentException('That e-pin is not right. Check it and try again.');
            }
            if ($pin->status !== EPin::ACTIVE) {
                throw new PaymentException($pin->status === EPin::USED ? 'That e-pin has already been used.' : 'That e-pin was cancelled.');
            }
            if ($member->isActivated()) {
                throw new PaymentException('Your package is already paid.');
            }
            if (! $package->is_active) {
                throw new PaymentException("The {$package->name} package is not on offer.");
            }
            if ($package->price > $pin->amount) {
                throw new PaymentException(sprintf('This e-pin is worth ₦%s, less than the %s package (₦%s).', number_format($pin->amount / 100), $package->name, number_format($package->price / 100)));
            }

            $key = 'epin:'.$pin->id;
            $outstanding = $this->ledger->systemAccount('epins.outstanding');
            $lines = [[$outstanding, -$pin->amount], [$this->ledger->systemAccount('external'), $package->price]];
            if ($pin->amount > $package->price) {
                $lines[] = [$this->ledger->memberAccount($member, 'main'), $pin->amount - $package->price];
            }
            $this->ledger->post('epins.use', $key, $lines, "E-pin used by @{$member->username} for {$package->name}", $pin);

            $change = $this->registration->activate($member, $package, $key);
            $pin->forceFill(['status' => EPin::USED, 'used_by_user_id' => $member->id, 'used_at' => now()])->save();

            return $change;
        });
    }

    public function cancel(Admin $admin, EPin $pin, string $reason): void
    {
        DB::transaction(function () use ($admin, $pin, $reason) {
            $pin = EPin::lockForUpdate()->findOrFail($pin->id);
            if ($pin->status !== EPin::ACTIVE) {
                throw new PaymentException('Only an unused e-pin can be cancelled.');
            }

            $back = $pin->created_by_user_id
                ? $this->ledger->memberAccount(User::findOrFail($pin->created_by_user_id), 'visa')
                : $this->ledger->systemAccount('adjustments');
            $tx = $this->ledger->transfer($this->ledger->systemAccount('epins.outstanding'), $back, $pin->amount, 'epins.cancel', 'epin:cancel:'.$pin->id, 'E-pin cancelled: '.$reason, $pin, $admin->id);

            $pin->forceFill(['status' => EPin::CANCELLED, 'cancelled_by_admin_id' => $admin->id, 'cancel_reason' => $reason, 'cancelled_at' => now()])->save();
            $this->audit->record($admin, 'epins.cancel', $pin, ['status' => EPin::ACTIVE], ['status' => EPin::CANCELLED, 'refunded_to' => $pin->created_by_user_id ? 'buyer VISA wallet' : 'adjustments', 'transaction' => $tx->id], $reason);
        });
    }

    /**
     * @param  array<string, mixed>  $who
     * @return Collection<int, EPin>
     */
    private function issue(int $quantity, Package $package, string $batch, array $who): Collection
    {
        $pins = collect();
        for ($i = 0; $i < $quantity; $i++) {
            do {
                $code = PickupCode::generate();
                $hash = PickupCode::hash($code);
            } while (EPin::where('code_hash', $hash)->exists());

            $pins->push(EPin::create($who + [
                'code_hash' => $hash, 'code_encrypted' => Crypt::encryptString($code), 'amount' => $package->price,
                'package_id' => $package->id, 'status' => EPin::ACTIVE, 'batch' => $batch,
            ]));
        }

        return $pins;
    }

    private function checkQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_PER_FORM) {
            throw new PaymentException('Choose between 1 and '.self::MAX_PER_FORM.' e-pins.');
        }
    }
}
