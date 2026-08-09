<?php

namespace App\Console\Commands;

use App\Models\XrayRuntimeUser;
use App\Services\XrayRuntimeUserReconciler;
use Illuminate\Console\Command;

class RemoveXrayUser extends Command
{
    protected $signature = 'xray:users:remove
        {tag : Existing inbound tag}
        {email : Exact email to remove}
        {--force : Skip interactive confirmation}';
    protected $description = 'Disable and remove one persistent runtime Xray user by email';

    public function handle(XrayRuntimeUserReconciler $reconciler): int
    {
        $tag = (string) $this->argument('tag');
        $email = (string) $this->argument('email');
        $this->warn('This immediately removes the selected runtime credential.');

        if (!$this->option('force') && !$this->confirm("Remove {$email} from {$tag}?", false)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        $user = XrayRuntimeUser::query()
            ->where('inbound_tag', $tag)
            ->where('email', $email)
            ->first();

        if (!$user) {
            $this->error('Persistent runtime user was not found.');
            return self::FAILURE;
        }

        $user->forceFill(['is_active' => false, 'last_synced_at' => null])->save();
        $result = $reconciler->reconcile($email);
        if ($result['failed'] > 0) {
            $this->error($result['errors'][0]['message'] ?? 'Removal reconciliation failed.');
            $this->warn('The desired state is saved as disabled and the scheduler will retry removal.');
            return self::FAILURE;
        }

        $this->info('User disabled persistently and removed from the running Xray instance.');
        return self::SUCCESS;
    }
}
