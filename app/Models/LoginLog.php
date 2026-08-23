<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $fillable = ['user_id', 'ip', 'location', 'user_agent', 'logged_at'];

    protected $casts = ['logged_at' => 'datetime'];
}
