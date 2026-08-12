<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class SocialWeeklyReport extends Model
{
    protected $table = 'SocialWeeklyReport';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
