<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class XrayStatsReader
{
    /**
     * @return array<string, array{uplink:int, downlink:int}>
     */
    public function read(): array
    {
        $binary = (string) config('xray.binary');

        if ($binary === '' || !is_file($binary) || !is_executable($binary)) {
            throw new RuntimeException("Xray binary is missing or not executable: {$binary}");
        }

        $arguments = [
            $binary,
            'api',
            'statsquery',
            '--server='.(string) config('xray.api_address'),
            '--timeout='.(int) config('xray.timeout'),
            '-pattern',
            (string) config('xray.stats_pattern'),
            '-reset='.(config('xray.reset_after_read') ? 'true' : 'false'),
        ];

        $process = new Process($arguments);
        $process->setTimeout((int) config('xray.timeout') + 3);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput().' '.$process->getOutput());
            throw new RuntimeException('Xray stats query failed: '.($message ?: 'unknown error'));
        }

        return $this->parse($process->getOutput());
    }

    /**
     * @return array<string, array{uplink:int, downlink:int}>
     */
    public function parse(string $output): array
    {
        $payload = json_decode(trim($output), true);

        if (!is_array($payload)) {
            throw new RuntimeException('Xray returned invalid JSON: '.mb_substr(trim($output), 0, 500));
        }

        $stats = $payload['stat'] ?? $payload['stats'] ?? [];

        if (!is_array($stats)) {
            throw new RuntimeException('Xray JSON does not contain a stat list.');
        }

        $users = [];

        foreach ($stats as $stat) {
            $name = (string) ($stat['name'] ?? '');
            $value = max(0, (int) ($stat['value'] ?? 0));

            if (!preg_match('/^user>>>(.+)>>>traffic>>>(uplink|downlink)$/', $name, $matches)) {
                continue;
            }

            $email = $matches[1];
            $direction = $matches[2];
            $users[$email] ??= ['uplink' => 0, 'downlink' => 0];
            $users[$email][$direction] += $value;
        }

        ksort($users);

        return $users;
    }
}
