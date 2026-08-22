<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTraffic extends Model
{
    protected $table = 'daily_traffic';

    protected $fillable = ['user_id', 'date', 'u', 'd'];

    protected $casts = ['date' => 'date'];
}
