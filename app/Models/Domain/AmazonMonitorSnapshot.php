<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AmazonMonitorSnapshot extends Model
{
    protected $table = 'AmazonMonitorSnapshot';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'rating' => 'decimal:4',
            'reviews' => 'integer',
            'activeVariations' => 'integer',
            'bsr' => 'integer',
            'bigCategoryRank' => 'integer',
            'smallCategoryRank' => 'integer',
            'checkedAt' => 'datetime',
        ];
    }
}
