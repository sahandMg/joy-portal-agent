<?php

namespace App\Console\Commands;

use App\Services\XrayUserManager;
use Illuminate\Console\Command;
use Throwable;

class CountXrayUsers extends Command
{
    protected $signature = 'xray:users:count {tag}';
    protected $description = 'Return the runtime user count of an Xray inbound';

    public function handle(XrayUserManager $manager): int
    {
        try {
            $payload = $manager->count((string) $this->argument('tag'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
