<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'Notification';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'isRead' => 'boolean',
            'recipientId' => 'integer',
            'createdAt' => 'datetime',
        ];
    }
}
