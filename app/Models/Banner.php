<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * 此刻应当展示的 Banner。
     *
     * `[!!]` 时间窗必须在【查询里】判，不能在视图里判。
     * 放视图里的话，"过期的还在首页挂着"这种事只有在有人看见时才会被发现，
     * 而运营最常见的失误恰恰是忘了下架。
     */
    public function scopeLive($q)
    {
        $now = now();

        return $q->where('enabled', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('sort')->orderBy('id');
    }

    /** 给后台列表用：说清楚当前为什么不展示。 */
    public function whyHidden(): ?string
    {
        if (! $this->enabled) {
            return '已停用';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return '还没到开始时间（'.$this->starts_at->format('m-d H:i').'）';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return '已过结束时间（'.$this->ends_at->format('m-d H:i').'）';
        }

        return null;
    }
}
