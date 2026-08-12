<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class ReputationResource extends Model
{
    protected $table = 'ReputationResource';

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
