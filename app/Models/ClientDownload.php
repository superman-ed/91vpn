<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientDownload extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean'];

    /** 用户页要显示的：启用的，按 sort。`[!]` url 为空的也列出来 —— 那是"即将推出"。 */
    public function scopeVisible($q)
    {
        return $q->where('enabled', true)->orderBy('sort')->orderBy('id');
    }
}
