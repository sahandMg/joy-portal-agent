<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XrayUsageSample extends Model
{
    protected $fillable = [
        'collection_id',
        'email',
        'uplink_total_bytes',
        'downlink_total_bytes',
        'uplink_delta_bytes',
        'downlink_delta_bytes',
        'counter_reset_detected',
        'observed_at',
    ];

    protected $casts = [
        'uplink_total_bytes' => 'integer',
        'downlink_total_bytes' => 'integer',
        'uplink_delta_bytes' => 'integer',
        'downlink_delta_bytes' => 'integer',
        'counter_reset_detected' => 'boolean',
        'observed_at' => 'datetime',
    ];
}
