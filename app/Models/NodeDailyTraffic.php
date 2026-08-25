<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeDailyTraffic extends Model
{
    protected $table = 'node_daily_traffic';

    protected $fillable = ['node_id', 'date', 'u', 'd', 'billed'];

    protected $casts = ['date' => 'date'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
