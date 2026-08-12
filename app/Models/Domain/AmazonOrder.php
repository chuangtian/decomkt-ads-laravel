<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AmazonOrder extends Model
{
    protected $table = 'AmazonOrder';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'purchaseDate' => 'datetime',
            'lastUpdatedDate' => 'datetime',
            'quantity' => 'integer',
            'itemPrice' => 'decimal:4',
            'itemTax' => 'decimal:4',
            'shippingPrice' => 'decimal:4',
            'shippingTax' => 'decimal:4',
            'isBusinessOrder' => 'boolean',
            'createdAt' => 'datetime',
        ];
    }
}
