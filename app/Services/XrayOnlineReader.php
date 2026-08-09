<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

final class XrayOnlineReader
{
    /**
     * Read Xray's live online counter for the supplied users.
     *
     * @param iterable<string> $emails
     * @return array<string, bool>
     */
    public function read(iterable $emails): array
    {
        $binary = (string) config('xray.binary');
        if ($binary === '' || !is_file($binary) || !is_executable($binary)) {
            throw new RuntimeException("Xray binary is missing or not executable: {$binary}");
        }

        $result = [];
        foreach (array_values(array_unique(array_filter(
            array_map('strval', is_array($emails) ? $emails : iterator_to_array($emails))
        ))) as $email) {
            $process = new Process([
                $binary,
                'api',
                'statsonline',
                '--server='.(string) config('xray.api_address'),
                '--timeout='.(int) config('xray.timeout'),
                '-email',
                $email,
            ]);
            $process->setTimeout((int) config('xray.timeout') + 3);
            $process->run();

            if ($process->isSuccessful()) {
                $result[$email] = $this->parse($process->getOutput(), $email);
                continue;
            }

            $message = trim($process->getErrorOutput().' '.$process->getOutput());
            if (str_contains($message, 'code = NotFound') ||
                str_contains($message, ">>>online not found")) {
                $result[$email] = false;
                continue;
            }

            throw new RuntimeException(
                "Xray online query failed for {$email}: ".($message ?: 'unknown error')
            );
        }

        return $result;
    }

    public function parse(string $output, string $email): bool
    {
        $payload = json_decode(trim($output), true);
        $stat = is_array($payload) ? ($payload['stat'] ?? null) : null;
        if (!is_array($stat)) {
            throw new RuntimeException(
                'Xray returned invalid statsonline JSON: '.mb_substr(trim($output), 0, 500)
            );
        }

        $expectedName = "user>>>{$email}>>>online";
        if (($stat['name'] ?? null) !== $expectedName) {
            throw new RuntimeException('Xray statsonline response belongs to another user.');
        }

        return (int) ($stat['value'] ?? 0) > 0;
    }
}
