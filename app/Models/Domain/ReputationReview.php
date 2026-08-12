<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class ReputationReview extends Model
{
    protected $table = 'ReputationReview';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'star' => 'integer',
            'week' => 'integer',
            'publishDate' => 'datetime',
            'replied' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
