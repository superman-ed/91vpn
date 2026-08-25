<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = ['to_email', 'type', 'subject', 'status', 'error'];
}
