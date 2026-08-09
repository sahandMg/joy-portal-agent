<?php

namespace App\Console\Commands;

use App\Models\XrayRuntimeUser;
use App\Models\XrayUsageSnapshot;
use App\Services\XrayUserManager;
use Illuminate\Console\Command;
use Throwable;

class AddXrayUser extends Command
{
    protected $signature = 'xray:users:add
        {tag : Existing inbound tag}
        {protocol : vless or vmess}
        {uuid : Client UUID}
        {email : Unique Xray statistics email}
        {--port= : Inbound listen port; inferred from tags like inbound-20180}
        {--level=0}
        {--alter-id=0 : VMess alterId}
        {--persist-only : Save a user that already exists in the running Xray instance}
        {--force : Skip interactive confirmation}';
    protected $description = 'Persist and add one runtime user to an existing Xray inbound';

    public function handle(XrayUserManager $manager): int
    {
        $tag = (string) $this->argument('tag');
        $protocol = strtolower((string) $this->argument('protocol'));
        $uuid = strtolower((string) $this->argument('uuid'));
        $email = (string) $this->argument('email');
        $port = $this->option('port');

        if (($port === null || $port === '') && preg_match('/(?:^|-)inbound-(\d+)$/', $tag, $matches)) {
            $port = $matches[1];
        }

        $port = (int) $port;

        if (!in_array($protocol, ['vless', 'vmess'], true)) {
            $this->error('Protocol must be vless or vmess.');
            return self::INVALID;
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            $this->error('UUID is invalid.');
            return self::INVALID;
        }
        if (!preg_match('/^[A-Za-z0-9._:@-]{3,128}$/', $email)) {
            $this->error('Email/stat key contains unsupported characters.');
            return self::INVALID;
        }
        if ($port < 1 || $port > 65535) {
            $this->error('A valid inbound port is required. Pass --port=20180.');
            return self::INVALID;
        }

        $this->line("Inbound: {$tag}; port: {$port}; protocol: {$protocol}; email: {$email}");
        if (!$this->option('force') && !$this->confirm('Add this test user?', false)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        $level = max(0, (int) $this->option('level'));
        $alterId = max(0, (int) $this->option('alter-id'));
        $existing = XrayRuntimeUser::query()->where('email', $email)->first();

        if ($existing && (
            $existing->inbound_tag !== $tag ||
            $existing->protocol !== $protocol ||
            $existing->uuid !== $uuid ||
            $existing->port !== $port
        )) {
            $this->error('This email is already persisted with different connection details. Remove it first.');
            return self::FAILURE;
        }

        $runtimeUser = XrayRuntimeUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'inbound_tag' => $tag,
                'protocol' => $protocol,
                'uuid' => $uuid,
                'port' => $port,
                'level' => $level,
                'alter_id' => $alterId,
                'is_active' => true,
                'last_error' => null,
            ]
        );

        try {
            $alreadyExists = $manager->exists($tag, $email);
            $payload = null;

            if (!$this->option('persist-only') && !$alreadyExists) {
                $payload = $manager->add(
                    $tag,
                    $protocol,
                    $uuid,
                    $email,
                    $port,
                    $level,
                    $alterId
                );
            }

            if ($this->option('persist-only') && !$alreadyExists) {
                throw new \RuntimeException(
                    'The user is not present in running Xray; omit --persist-only to add it now.'
                );
            }

            $runtimeUser->forceFill([
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $runtimeUser->forceFill([
                'last_synced_at' => null,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info($alreadyExists
            ? 'User was already present and is now persisted for restart recovery.'
            : 'Xray added the user and it is persisted for restart recovery.');
        XrayUsageSnapshot::query()->firstOrCreate(
            ['email' => $email],
            [
                'uplink_total_bytes' => 0,
                'downlink_total_bytes' => 0,
                'observed_at' => now(),
            ]
        );
        if ($payload !== null) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        return self::SUCCESS;
    }
}
