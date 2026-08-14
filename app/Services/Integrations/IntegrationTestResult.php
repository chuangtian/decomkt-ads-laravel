<?php

namespace App\Services\Integrations;

readonly class IntegrationTestResult
{
    /** @param array<string, int|string|bool|null> $details */
    public function __construct(
        public bool $ok,
        public string $platform,
        public string $message,
        public array $details = [],
    ) {}
}
