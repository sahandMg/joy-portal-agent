<?php

namespace App\Console\Commands;

use App\Services\XrayUserManager;
use Illuminate\Console\Command;
use Throwable;

class RemoveXrayUser extends Command
{
    protected $signature = 'xray:users:remove
        {tag : Existing inbound tag}
        {email : Exact email to remove}
        {--force : Skip interactive confirmation}';
    protected $description = 'Remove one runtime Xray user by email';

    public function handle(XrayUserManager $manager): int
    {
        $tag = (string) $this->argument('tag');
        $email = (string) $this->argument('email');
        $this->warn('This immediately removes the selected runtime credential.');

        if (!$this->option('force') && !$this->confirm("Remove {$email} from {$tag}?", false)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $manager->remove($tag, $email);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('User removed from the running Xray instance.');
        return self::SUCCESS;
    }
}
