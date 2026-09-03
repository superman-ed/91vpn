<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrashLog extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'platform', 'brand', 'model',
        'os_version', 'app_version', 'message', 'stack', 'fingerprint', 'ip',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
