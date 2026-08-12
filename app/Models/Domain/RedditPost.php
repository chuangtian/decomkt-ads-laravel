<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class RedditPost extends Model
{
    protected $table = 'RedditPost';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'upvotes' => 'integer',
            'views' => 'integer',
            'commentCount' => 'integer',
            'postDate' => 'datetime',
            'week' => 'integer',
            'replied' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
