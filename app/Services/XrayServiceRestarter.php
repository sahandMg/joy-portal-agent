<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

final class XrayServiceRestarter
{
    public function restart(): void
    {
        $service = (string) config('xray.service_name', 'x-ui');
        if (!preg_match('/\A[A-Za-z0-9_.@-]+\z/', $service)) {
            throw new RuntimeException('Invalid Xray service name.');
        }
        $process = new Process(['systemctl', 'restart', $service]);
        $process->setTimeout(30);
        $process->run();
        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput().' '.$process->getOutput());
            throw new RuntimeException('Could not restart '.$service.': '.($message ?: 'unknown error'));
        }
    }
}
