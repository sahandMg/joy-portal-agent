<?php

namespace App\Services;

use App\Models\XrayRuntimeUser;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class XrayRuntimeUserReconciler
{
    public function __construct(private XrayUserManager $manager)
    {
    }

    public function reconcile(?string $email = null): array
    {
        return Cache::lock('xray-runtime-users-reconcile', 30)->block(
            10,
            fn (): array => $this->reconcileUnlocked($email)
        );
    }

    private function reconcileUnlocked(?string $email): array
    {
        $query = XrayRuntimeUser::query()->orderBy('id');
        if ($email !== null && $email !== '') {
            $query->where('email', $email);
        }

        $result = [
            'checked' => 0,
            'added' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $query->chunkById(100, function ($users) use (&$result): void {
            foreach ($users as $user) {
                $result['checked']++;

                try {
                    $exists = $this->manager->exists($user->inbound_tag, $user->email);

                    if ($user->is_active && !$exists) {
                        $this->manager->add(
                            $user->inbound_tag,
                            $user->protocol,
                            $user->uuid,
                            $user->email,
                            $user->port,
                            $user->level,
                            $user->alter_id
                        );
                        $result['added']++;
                    } elseif (!$user->is_active && $exists) {
                        $this->manager->remove($user->inbound_tag, $user->email);
                        $result['removed']++;
                    } else {
                        $result['unchanged']++;
                    }

                    $user->forceFill([
                        'last_synced_at' => now(),
                        'last_error' => null,
                    ])->save();
                } catch (Throwable $exception) {
                    $message = mb_substr($exception->getMessage(), 0, 2000);
                    $user->forceFill(['last_error' => $message])->save();
                    $result['failed']++;
                    $result['errors'][] = [
                        'email' => $user->email,
                        'message' => $message,
                    ];
                }
            }
        });

        return $result;
    }
}
