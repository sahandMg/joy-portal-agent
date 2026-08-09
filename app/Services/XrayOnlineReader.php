<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

final class XrayOnlineReader
{
    /**
     * Read Xray's live IP list for the supplied users.
     *
     * Some Xray builds leave the scalar statsonline counter at 1 after the
     * last IP has already disappeared. The IP list is therefore the source
     * of truth for presence.
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
                'statsonlineiplist',
                '--server='.(string) config('xray.api_address'),
                '--timeout='.(int) config('xray.timeout'),
                '-email',
                $email,
            ]);
            $process->setTimeout((int) config('xray.timeout') + 3);
            $process->run();

            if ($process->isSuccessful()) {
                $result[$email] = $this->parseIpList($process->getOutput(), $email);
                continue;
            }

            $message = trim($process->getErrorOutput().' '.$process->getOutput());
            if (str_contains($message, 'code = NotFound') ||
                str_contains($message, ">>>online not found")) {
                $result[$email] = false;
                continue;
            }

            throw new RuntimeException(
                "Xray online IP query failed for {$email}: ".($message ?: 'unknown error')
            );
        }

        return $result;
    }

    public function parseIpList(string $output, string $email): bool
    {
        $payload = json_decode(trim($output), true);
        if (!is_array($payload)) {
            throw new RuntimeException(
                'Xray returned invalid statsonlineiplist JSON: '.mb_substr(trim($output), 0, 500)
            );
        }

        $expectedName = "user>>>{$email}>>>online";
        $name = $payload['name'] ?? ($payload['stat']['name'] ?? null);
        if ($name !== $expectedName) {
            throw new RuntimeException('Xray statsonlineiplist response belongs to another user.');
        }

        return $this->containsIpAddress($payload);
    }

    private function containsIpAddress(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if ($key === 'name') continue;

            $keyCandidate = trim((string) $key, "[] \t\n\r\0\x0B");
            if (filter_var($keyCandidate, FILTER_VALIDATE_IP) !== false) {
                return true;
            }

            if (is_array($value) && $this->containsIpAddress($value)) return true;
            if (!is_string($value)) continue;

            $candidate = trim($value, "[] \t\n\r\0\x0B");
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) return true;

            // Be tolerant if a future CLI version emits IP:port.
            $host = parse_url('tcp://'.$value, PHP_URL_HOST);
            if (is_string($host) && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
                return true;
            }
        }

        return false;
    }
}
