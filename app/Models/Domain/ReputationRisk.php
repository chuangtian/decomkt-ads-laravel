<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class ReputationRisk extends Model
{
    protected $table = 'ReputationRisk';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
