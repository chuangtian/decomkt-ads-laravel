<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class ReputationWeeklyReport extends Model
{
    protected $table = 'ReputationWeeklyReport';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'year' => 'integer',
            'overallScore' => 'decimal:4',
            'tpAvg' => 'decimal:4',
            'websiteAvg' => 'decimal:4',
            'redditPosts' => 'integer',
            'negativeCount' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
