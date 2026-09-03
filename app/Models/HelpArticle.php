<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    protected $fillable = ['category', 'title', 'content', 'sort', 'published'];

    protected $casts = ['published' => 'boolean'];
}
