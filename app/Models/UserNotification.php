<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $table = 'user_notifications';

    protected $fillable = ['user_id', 'batch_id', 'title', 'content', 'type', 'pinned', 'read_at'];

    protected $casts = ['read_at' => 'datetime', 'pinned' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }
}
