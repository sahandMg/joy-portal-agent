<?php

namespace App\Services;

use App\Models\XrayRuntimeUser;
use App\Models\XrayRuntimeInbound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

final class XrayCredentialSynchronizer
{
    public function __construct(
        private JoyCredentialClient $client,
        private XrayDesiredStateReconciler $reconciler,
    ) {}

    public function sync(): array
    {
        return Cache::lock('xray-credential-snapshot-sync', 30)->block(
            10,
            fn (): array => $this->syncUnlocked()
        );
    }

    private function syncUnlocked(): array
    {
        $snapshot = $this->client->snapshot();
        $inbounds = collect($snapshot['inbounds'])->map(fn ($row) => $this->validateInbound($row));
        $rows = collect($snapshot['credentials'])->map(fn ($row) => $this->validateRow($row));
        $inboundTags = $inbounds->pluck('tag')->all();
        foreach ($rows as $row) {
            if (!in_array($row['inbound_tag'], $inboundTags, true)) {
                throw new RuntimeException('Credential references an inbound missing from the snapshot: '.
                    $row['inbound_tag']);
            }
        }

        $saved = DB::transaction(function () use ($rows, $inbounds): array {
            $tags = [];
            foreach ($inbounds as $row) {
                $tags[] = $row['tag'];
                XrayRuntimeInbound::query()->updateOrCreate(['tag' => $row['tag']], $row);
            }
            $missingInbounds = XrayRuntimeInbound::query();
            if ($tags !== []) $missingInbounds->whereNotIn('tag', $tags);
            $missingInbounds->update(['is_active' => false, 'last_synced_at' => null]);

            $emails = [];
            foreach ($rows as $row) {
                $emails[] = $row['email'];
                $user = XrayRuntimeUser::query()->firstOrNew(['email' => $row['email']]);
                $desired = [
                    'inbound_tag' => $row['inbound_tag'], 'protocol' => $row['protocol'],
                    'port' => $row['port'], 'level' => $row['level'],
                    'alter_id' => $row['alter_id'], 'is_active' => $row['is_active'],
                ];
                foreach ($desired as $field => $value) {
                    if (!$user->exists || $user->{$field} !== $value) $user->{$field} = $value;
                }
                // Assigning an encrypted cast always creates a new ciphertext, so only
                // touch it when the plaintext UUID actually changed.
                if (!$user->exists || $user->uuid !== $row['uuid']) $user->uuid = $row['uuid'];
                if ($user->isDirty()) $user->save();
            }

            $missing = XrayRuntimeUser::query();
            if ($emails !== []) $missing->whereNotIn('email', $emails);
            $missing->update(['is_active' => false, 'last_synced_at' => null]);
            return ['credentials' => count($emails), 'inbounds' => count($tags)];
        }, 3);

        $reconcile = $this->reconciler->reconcile();
        $this->client->reportRuntimeStatus(XrayRuntimeUser::query()->orderBy('id')->get()->map(
            function (XrayRuntimeUser $user): array {
                $status = $user->last_error
                    ? 'error'
                    : ($user->is_active
                        ? ($user->last_synced_at ? 'created' : 'pending')
                        : 'removed');
                return ['email' => $user->email, 'status' => $status,
                    'error' => $user->last_error];
            }
        )->all());
        return ['received' => $saved, 'reconcile' => $reconcile];
    }

    private function validateRow($row): array
    {
        if (!is_array($row)) throw new RuntimeException('Credential row must be an object.');
        $email = trim((string) ($row['email'] ?? ''));
        $tag = trim((string) ($row['inbound_tag'] ?? ''));
        $protocol = strtolower((string) ($row['protocol'] ?? ''));
        $uuid = strtolower((string) ($row['uuid'] ?? ''));
        $port = (int) ($row['port'] ?? 0);

        if (!preg_match('/^[A-Za-z0-9._:@-]{3,190}$/', $email) ||
            !preg_match('/^[A-Za-z0-9._-]{1,100}$/', $tag) ||
            !in_array($protocol, ['vmess', 'vless'], true) ||
            !Str::isUuid($uuid) || $port < 1 || $port > 65535) {
            throw new RuntimeException('Credential snapshot contains invalid connection data for '.$email.'.');
        }

        return [
            'email' => $email, 'inbound_tag' => $tag, 'protocol' => $protocol,
            'uuid' => $uuid, 'port' => $port,
            'level' => max(0, (int) ($row['level'] ?? 0)),
            'alter_id' => $protocol === 'vmess' ? max(0, (int) ($row['alter_id'] ?? 0)) : 0,
            'is_active' => (bool) ($row['is_active'] ?? false),
        ];
    }

    private function validateInbound($row): array
    {
        if (!is_array($row)) throw new RuntimeException('Inbound row must be an object.');
        $tag = trim((string) ($row['tag'] ?? ''));
        $protocol = strtolower((string) ($row['protocol'] ?? ''));
        $transport = strtolower((string) ($row['transport'] ?? ''));
        $port = (int) ($row['port'] ?? 0);
        $path = $transport === 'ws' ? (string) ($row['ws_path'] ?? '/') : null;
        if (!preg_match('/^[A-Za-z0-9._-]{1,100}$/', $tag) ||
            !in_array($protocol, ['vmess', 'vless'], true) ||
            !in_array($transport, ['ws', 'tcp'], true) ||
            $port < 1 || $port > 65535 ||
            ($transport === 'ws' && !preg_match('/^\/[^\s]{0,254}$/', $path))) {
            throw new RuntimeException('Inbound snapshot contains invalid data for '.$tag.'.');
        }
        return ['tag' => $tag, 'protocol' => $protocol, 'transport' => $transport,
            'port' => $port, 'ws_path' => $path, 'is_active' => (bool) ($row['is_active'] ?? false)];
    }
}
