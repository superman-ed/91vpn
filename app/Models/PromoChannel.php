<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoChannel extends Model
{
    protected $fillable = ['code', 'name', 'note', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    /** 该推广码带来的用户 */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'promo_code', 'code');
    }
}
