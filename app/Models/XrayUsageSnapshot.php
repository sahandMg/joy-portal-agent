<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XrayUsageSnapshot extends Model
{
    protected $fillable = [
        'email',
        'uplink_total_bytes',
        'downlink_total_bytes',
        'observed_at',
    ];

    protected $casts = [
        'uplink_total_bytes' => 'integer',
        'downlink_total_bytes' => 'integer',
        'observed_at' => 'datetime',
    ];
}
