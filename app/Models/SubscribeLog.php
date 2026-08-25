<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscribeLog extends Model
{
    protected $fillable = ['user_id', 'type', 'ip', 'location', 'client', 'fetched_at'];
    protected $casts = ['fetched_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
