<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AmazonMonitorLink extends Model
{
    protected $table = 'AmazonMonitorLink';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
