<?php

namespace App\Console\Commands;

use App\Services\XrayUserManager;
use Illuminate\Console\Command;
use Throwable;

class ListXrayUsers extends Command
{
    protected $signature = 'xray:users:list {tag} {--email=}';
    protected $description = 'List runtime users of an Xray inbound without modifying it';

    public function handle(XrayUserManager $manager): int
    {
        try {
            $payload = $manager->list((string) $this->argument('tag'), $this->option('email') ?: null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
