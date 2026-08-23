<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscribeLog extends Model
{
    protected $fillable = ['user_id', 'ip', 'location', 'client', 'fetched_at'];
    protected $casts = ['fetched_at' => 'datetime'];
}
