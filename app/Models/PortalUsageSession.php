<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalUsageSession extends Model
{
    protected $guarded = [];
    protected $casts = [
        'sequence' => 'integer',
        'total_bytes' => 'integer',
        'last_reported_total_bytes' => 'integer',
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_reported_at' => 'datetime',
    ];
}
