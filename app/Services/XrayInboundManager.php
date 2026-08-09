<?php

namespace App\Services;

use App\Models\XrayRuntimeInbound;
use RuntimeException;
use Symfony\Component\Process\Process;

class XrayInboundManager
{
    public function listTags(): array
    {
        return array_keys($this->profiles());
    }

    public function profiles(): array
    {
        $payload = $this->runJson(['api', 'lsi']);
        $profiles = [];
        foreach ($payload['inbounds'] ?? [] as $inbound) {
            if (!is_array($inbound) || !is_string($inbound['tag'] ?? null)) continue;
            $receiver = is_array($inbound['receiverSettings'] ?? null) ? $inbound['receiverSettings'] : [];
            $proxy = is_array($inbound['proxySettings'] ?? null) ? $inbound['proxySettings'] : [];
            $typed = strtolower((string) ($proxy['_TypedMessage_'] ?? ''));
            $protocol = str_contains($typed, '.vless.') ? 'vless' :
                (str_contains($typed, '.vmess.') ? 'vmess' : null);
            $stream = is_array($receiver['streamSettings'] ?? null) ? $receiver['streamSettings'] : [];
            $protocolName = strtolower((string) ($stream['protocolName'] ?? 'tcp'));
            $transport = $protocolName === 'websocket' ? 'ws' : $protocolName;
            $path = null;
            foreach ($stream['transportSettings'] ?? [] as $settings) {
                if (($settings['protocolName'] ?? null) === 'websocket') {
                    $path = (string) ($settings['settings']['path'] ?? '/');
                }
            }
            $profiles[$inbound['tag']] = [
                'protocol' => $protocol,
                'transport' => $transport,
                'port' => (int) ($receiver['portList'] ?? 0),
                'ws_path' => $transport === 'ws' ? ($path ?: '/') : null,
            ];
        }
        return $profiles;
    }

    public function add(XrayRuntimeInbound $inbound): void
    {
        $this->ensureWritesEnabled();
        $settings = ['clients' => []];
        if ($inbound->protocol === 'vless') $settings['decryption'] = 'none';

        $stream = ['network' => $inbound->transport, 'security' => 'none'];
        if ($inbound->transport === 'ws') {
            $stream['wsSettings'] = ['path' => $inbound->ws_path ?: '/'];
        }

        $payload = ['inbounds' => [[
            'tag' => $inbound->tag,
            'port' => $inbound->port,
            'protocol' => $inbound->protocol,
            'settings' => $settings,
            'streamSettings' => $stream,
            'sniffing' => ['enabled' => true, 'destOverride' => ['http', 'tls']],
        ]]];

        $path = tempnam(storage_path('app'), 'xray-add-inbound-');
        if ($path === false) throw new RuntimeException('Could not create temporary inbound file.');
        try {
            if (file_put_contents($path, json_encode($payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
                throw new RuntimeException('Could not write temporary inbound file.');
            }
            $this->run(['api', 'adi', $path]);
        } finally {
            @unlink($path);
        }
    }

    public function remove(string $tag): void
    {
        $this->ensureWritesEnabled();
        $this->run(['api', 'rmi', $tag]);
    }

    private function ensureWritesEnabled(): void
    {
        if (!config('xray.user_writes_enabled')) {
            throw new RuntimeException('Xray writes are disabled.');
        }
    }

    private function runJson(array $arguments): array
    {
        $payload = json_decode(trim($this->run($arguments)), true);
        if (!is_array($payload)) throw new RuntimeException('Xray returned invalid inbound JSON.');
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
            throw new RuntimeException('Xray inbound API failed: '.($message ?: 'unknown error'));
        }
        return $process->getOutput();
    }
}
