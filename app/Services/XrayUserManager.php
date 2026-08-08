<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class XrayUserManager
{
    public function list(string $tag, ?string $email = null): array
    {
        $arguments = ['api', 'inbounduser', '-tag='.$tag];

        if ($email !== null) {
            $arguments[] = '-email='.$email;
        }

        return $this->runJson($arguments);
    }

    public function count(string $tag): array
    {
        return $this->runJson(['api', 'inboundusercount', '-tag='.$tag]);
    }

    public function add(
        string $tag,
        string $protocol,
        string $uuid,
        string $email,
        int $level = 0,
        int $alterId = 0
    ): array {
        $this->ensureWritesEnabled();

        $client = [
            'id' => $uuid,
            'email' => $email,
            'level' => $level,
        ];

        if ($protocol === 'vmess') {
            $client['alterId'] = $alterId;
        }

        $settings = ['clients' => [$client]];

        if ($protocol === 'vless') {
            $settings['decryption'] = 'none';
        }

        $payload = [
            'inbounds' => [[
                'tag' => $tag,
                'protocol' => $protocol,
                'settings' => $settings,
            ]],
        ];

        $path = tempnam(storage_path('app'), 'xray-add-user-');

        if ($path === false) {
            throw new RuntimeException('Could not create temporary Xray user file.');
        }

        try {
            if (file_put_contents(
                $path,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ) === false) {
                throw new RuntimeException('Could not write temporary Xray user file.');
            }

            $this->run(['api', 'adu', $path]);

            return $this->list($tag, $email);
        } finally {
            @unlink($path);
        }
    }

    public function remove(string $tag, string $email): void
    {
        $this->ensureWritesEnabled();
        $this->run(['api', 'rmu', '-tag='.$tag, $email]);
    }

    private function ensureWritesEnabled(): void
    {
        if (!config('xray.user_writes_enabled')) {
            throw new RuntimeException(
                'Xray user writes are disabled. Set XRAY_USER_WRITES_ENABLED=true only for an isolated test.'
            );
        }
    }

    private function runJson(array $arguments): array
    {
        $output = $this->run($arguments);
        $payload = json_decode(trim($output), true);

        if (!is_array($payload)) {
            throw new RuntimeException('Xray returned invalid JSON: '.mb_substr(trim($output), 0, 500));
        }

        return $payload;
    }

    private function run(array $arguments): string
    {
        $binary = (string) config('xray.binary');

        if ($binary === '' || !is_file($binary) || !is_executable($binary)) {
            throw new RuntimeException("Xray binary is missing or not executable: {$binary}");
        }

        array_splice($arguments, 2, 0, [
            '--server='.(string) config('xray.api_address'),
            '--timeout='.(int) config('xray.timeout'),
        ]);

        $process = new Process(array_merge([$binary], $arguments));
        $process->setTimeout((int) config('xray.timeout') + 5);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput().' '.$process->getOutput());
            throw new RuntimeException('Xray user API failed: '.($message ?: 'unknown error'));
        }

        return $process->getOutput();
    }
}
