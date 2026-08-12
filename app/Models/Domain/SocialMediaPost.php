<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class SocialMediaPost extends Model
{
    protected $table = 'SocialMediaPost';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'views' => 'integer',
            'likes' => 'integer',
            'shares' => 'integer',
            'comments' => 'integer',
            'saves' => 'integer',
            'reach' => 'integer',
            'followers' => 'integer',
            'createdAt' => 'datetime',
        ];
    }
}
