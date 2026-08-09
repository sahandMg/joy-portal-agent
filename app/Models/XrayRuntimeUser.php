<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class XrayRuntimeUser extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uuid' => 'encrypted',
        'port' => 'integer',
        'level' => 'integer',
        'alter_id' => 'integer',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
