<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class SocialDailyReview extends Model
{
    protected $table = 'SocialDailyReview';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'isDraft' => 'boolean',
            'creatorId' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
