<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class KolRecommendBatch extends Model
{
    protected $table = 'KolRecommendBatch';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
        ];
    }
}
