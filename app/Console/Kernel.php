<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (config('xray.user_writes_enabled') && config('xray.credential_sync_enabled')) {
            $schedule->command('xray:users:sync')
                ->everyMinute()
                ->withoutOverlapping(5);
        }

        if (config('xray.user_writes_enabled') && config('xray.user_reconcile_enabled')) {
            $schedule->command('xray:desired:reconcile')
                ->everyMinute()
                ->withoutOverlapping(5);
        }

        if (config('xray.collection_enabled')) {
            $schedule->command('xray:usage:collect --quiet')
                ->everyMinute()
                ->withoutOverlapping();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
