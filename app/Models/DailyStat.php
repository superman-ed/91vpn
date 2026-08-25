<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyStat extends Model
{
    protected $fillable = ['date', 'dau', 'peak_online', 'new_users'];

    protected $casts = ['date' => 'date'];
}
