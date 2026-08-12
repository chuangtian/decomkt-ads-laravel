<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class KolRecommendItem extends Model
{
    protected $table = 'KolRecommendItem';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'avgER' => 'decimal:4',
            'matchScore' => 'integer',
            'addedToPool' => 'boolean',
            'createdAt' => 'datetime',
        ];
    }
}
