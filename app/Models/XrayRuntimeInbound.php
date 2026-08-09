<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class XrayRuntimeInbound extends Model
{
    protected $guarded = [];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
