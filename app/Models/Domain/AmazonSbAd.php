<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AmazonSbAd extends Model
{
    protected $table = 'AmazonSbAd';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'impressions' => 'integer',
            'viewableImpressions' => 'integer',
            'clicks' => 'integer',
            'ctr' => 'decimal:4',
            'spend' => 'decimal:4',
            'cpc' => 'decimal:4',
            'vcpm' => 'decimal:4',
            'acos' => 'decimal:4',
            'roas' => 'decimal:4',
            'sales14d' => 'decimal:4',
            'orders14d' => 'integer',
            'units14d' => 'integer',
            'conversionRate' => 'decimal:4',
            'vtr' => 'decimal:4',
            'vctr' => 'decimal:4',
            'brandSearches14d' => 'integer',
            'dpv14d' => 'integer',
            'newBuyerOrders14d' => 'integer',
            'createdAt' => 'datetime',
        ];
    }
}
