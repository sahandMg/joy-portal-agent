<?php

namespace App\Console\Commands;

use App\Services\XrayCredentialSynchronizer;
use Illuminate\Console\Command;
use Throwable;

final class SyncXrayUsers extends Command
{
    protected $signature = 'xray:users:sync
        {--watch : Keep polling Joy for credential changes}
        {--interval=5 : Successful polling interval in seconds}';
    protected $description = 'Pull the complete desired credential snapshot from Joy and reconcile Xray';

    public function handle(XrayCredentialSynchronizer $synchronizer): int
    {
        $watch = (bool) $this->option('watch');
        $interval = max(2, min(300, (int) $this->option('interval')));

        do {
            $status = $this->syncOnce($synchronizer);
            if (!$watch) return $status;
            sleep($status === self::SUCCESS ? $interval : max(15, $interval));
        } while (true);
    }

    private function syncOnce(XrayCredentialSynchronizer $synchronizer): int
    {
        try {
            $result = $synchronizer->sync();
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $reconcile = $result['reconcile'];
        $this->line(sprintf('Received %d; added %d; removed %d; unchanged %d; failed %d.',
            $result['received']['credentials'], $reconcile['users']['added'],
            $reconcile['users']['removed'], $reconcile['users']['unchanged'],
            $reconcile['users']['failed'] + $reconcile['inboundEnsure']['failed'] +
                $reconcile['inboundCleanup']['failed']));
        $failed = $reconcile['users']['failed'] + $reconcile['inboundEnsure']['failed'] +
            $reconcile['inboundCleanup']['failed'];
        foreach (['inboundEnsure', 'users', 'inboundCleanup'] as $section) {
            foreach ($reconcile[$section]['errors'] ?? [] as $error) {
                $subject = $error['tag'] ?? $error['email'] ?? $section;
                $this->error($subject.': '.($error['message'] ?? 'Unknown error'));
            }
        }
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
