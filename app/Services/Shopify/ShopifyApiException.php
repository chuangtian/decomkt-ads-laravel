<?php

namespace App\Services\Shopify;

use RuntimeException;

class ShopifyApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }
}
