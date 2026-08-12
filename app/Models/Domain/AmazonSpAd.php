<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AmazonSpAd extends Model
{
    protected $table = 'AmazonSpAd';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'impressions' => 'integer',
            'clicks' => 'integer',
            'ctr' => 'decimal:4',
            'cpc' => 'decimal:4',
            'spend' => 'decimal:4',
            'sales7d' => 'decimal:4',
            'acos' => 'decimal:4',
            'roas' => 'decimal:4',
            'orders7d' => 'integer',
            'units7d' => 'integer',
            'conversionRate' => 'decimal:4',
            'skuUnits7d' => 'integer',
            'otherSkuUnits7d' => 'integer',
            'skuSales7d' => 'decimal:4',
            'otherSkuSales7d' => 'decimal:4',
            'createdAt' => 'datetime',
        ];
    }
}
