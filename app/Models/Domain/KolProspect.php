<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class KolProspect extends Model
{
    protected $table = 'KolProspect';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'avgER' => 'decimal:4',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
