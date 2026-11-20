<?php

namespace App\Services\Usdt;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the Tron network through TronGrid. Only Tether's official USDT
 * contract counts (anyone can make a token called "USDT"), and only final
 * transactions: the "solidity" node answers only for confirmed blocks.
 */
class TronGrid
{
    /** keccak256("Transfer(address,address,uint256)"): the event every TRC20 transfer logs. */
    public const TRANSFER_TOPIC = 'ddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    public static function network(): array
    {
        return config('usdt.networks')[config('usdt.network')] ?? throw new RuntimeException('Unknown Tron network.');
    }

    /**
     * One transaction: missing, pending (not final yet), failed, or confirmed
     * with its official USDT transfers.
     *
     * @return array{state: string, time_ms: ?int, transfers: list<array{from: ?string, to: string, units: int}>}
     *
     * @throws TronGridUnavailable
     */
    public function transaction(string $txid): array
    {
        $txid = strtolower($txid);
        $info = $this->post('/walletsolidity/gettransactioninfobyid', ['value' => $txid]);
        if (empty($info['id'])) {
            $unconfirmed = $this->post('/wallet/gettransactioninfobyid', ['value' => $txid]);

            return ['state' => empty($unconfirmed['id']) ? 'missing' : 'pending', 'time_ms' => null, 'transfers' => []];
        }
        if (strtolower((string) $info['id']) !== $txid) {
            throw new TronGridUnavailable('TronGrid answered about another transaction.');
        }
        $time = isset($info['blockTimeStamp']) ? (int) $info['blockTimeStamp'] : null;
        if (($info['result'] ?? null) === 'FAILED' || ($info['receipt']['result'] ?? 'SUCCESS') !== 'SUCCESS') {
            return ['state' => 'failed', 'time_ms' => $time, 'transfers' => []];
        }

        return ['state' => 'confirmed', 'time_ms' => $time, 'transfers' => $this->transfers((array) ($info['log'] ?? []))];
    }

    /**
     * Confirmed USDT payments into an address since a moment, oldest first.
     *
     * @return list<array{txid: string, units: int, time_ms: int}>
     *
     * @throws TronGridUnavailable
     */
    public function incoming(string $address, int $sinceMs): array
    {
        $network = self::network();
        $query = ['only_to' => 'true', 'only_confirmed' => 'true', 'contract_address' => $network['contract'], 'limit' => 200, 'min_timestamp' => $sinceMs, 'order_by' => 'block_timestamp,asc'];
        $found = [];
        for ($page = 0; $page < 20; $page++) {
            $body = $this->get("/v1/accounts/{$address}/transactions/trc20", $query);
            if (($body['success'] ?? false) !== true || ! is_array($body['data'] ?? null)) {
                throw new TronGridUnavailable('TronGrid gave an unexpected answer.');
            }
            foreach ($body['data'] as $row) {
                $txid = strtolower((string) ($row['transaction_id'] ?? ''));
                if (preg_match('/^[0-9a-f]{64}$/', $txid) && ($row['to'] ?? null) === $address
                    && ($row['token_info']['address'] ?? null) === $network['contract'] && ctype_digit((string) ($row['value'] ?? ''))) {
                    $found[] = ['txid' => $txid, 'units' => (int) $row['value'], 'time_ms' => (int) ($row['block_timestamp'] ?? 0)];
                }
            }
            $next = $body['meta']['fingerprint'] ?? null;
            if (! $next || count($body['data']) < $query['limit']) {
                break;
            }
            $query['fingerprint'] = $next;
        }

        return $found;
    }

    /** The official USDT Transfer events among a transaction's logs. */
    private function transfers(array $logs): array
    {
        $contract = substr((string) TronAddress::toHex(self::network()['contract']), 2);
        $out = [];
        foreach ($logs as $log) {
            $address = strtolower((string) ($log['address'] ?? ''));
            $address = strlen($address) === 42 && str_starts_with($address, '41') ? substr($address, 2) : $address;
            $topics = array_map(fn ($t) => strtolower((string) $t), (array) ($log['topics'] ?? []));
            $data = strtolower((string) ($log['data'] ?? ''));
            if ($address !== $contract || count($topics) !== 3 || $topics[0] !== self::TRANSFER_TOPIC || ! preg_match('/^[0-9a-f]{64}$/', $data)) {
                continue;
            }
            // The amount is a 256-bit number; anything beyond 15 hex digits is not a real deposit.
            $significant = ltrim($data, '0');
            if (strlen($significant) > 15 || ($to = TronAddress::fromHex($topics[2])) === null) {
                continue;
            }
            $out[] = ['from' => TronAddress::fromHex($topics[1]), 'to' => $to, 'units' => (int) hexdec($significant === '' ? '0' : $significant)];
        }

        return $out;
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl(self::network()['api'])->acceptJson()->timeout(15);

        return config('usdt.api_key') ? $client->withHeaders(['TRON-PRO-API-KEY' => config('usdt.api_key')]) : $client;
    }

    private function post(string $path, array $body): array
    {
        return $this->send(fn () => $this->client()->post($path, $body));
    }

    private function get(string $path, array $query): array
    {
        return $this->send(fn () => $this->client()->get($path, $query));
    }

    private function send(callable $call): array
    {
        try {
            $response = $call();
        } catch (ConnectionException $e) {
            throw new TronGridUnavailable('TronGrid could not be reached.', previous: $e);
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new TronGridUnavailable('TronGrid answered with an error.');
        }

        return $response->json();
    }
}
