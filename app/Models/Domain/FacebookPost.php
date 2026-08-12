<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class FacebookPost extends Model
{
    protected $table = 'FacebookPost';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'views' => 'integer',
            'reach' => 'integer',
            'reactions' => 'integer',
            'comments' => 'integer',
            'shares' => 'integer',
            'totalClicks' => 'integer',
            'otherClicks' => 'integer',
            'photoClicks' => 'integer',
            'linkClicks' => 'integer',
            'negativeFeedback' => 'integer',
            'createdAt' => 'datetime',
        ];
    }
}
