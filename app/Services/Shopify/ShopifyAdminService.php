<?php

namespace App\Services\Shopify;

use App\Models\Domain\ShopifyInstallation;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyAdminService
{
    public function __construct(private readonly ShopifyTokenService $tokens) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function graphql(ShopifyInstallation $installation, string $query, array $variables = []): array
    {
        $installation = $this->tokens->refreshIfNeeded($installation);
        $response = Http::acceptJson()
            ->withHeader('X-Shopify-Access-Token', (string) $installation->accessToken)
            ->timeout(20)
            ->post(sprintf(
                'https://%s/admin/api/%s/graphql.json',
                $installation->shopDomain,
                config('shopify.api_version'),
            ), compact('query', 'variables'));

        if (! $response->successful()) {
            throw new ShopifyApiException(
                "Shopify Admin API failed ({$response->status()}).",
                $response->status(),
            );
        }
        /** @var array<string, mixed> $json */
        $json = (array) $response->json();
        $errors = $this->messages($json['errors'] ?? []);
        if ($errors !== '') {
            throw new ShopifyApiException($errors);
        }

        return (array) ($json['data'] ?? []);
    }

    /** @param array<string, mixed> $input */
    public function createBasicDiscount(ShopifyInstallation $installation, array $input): string
    {
        $data = $this->graphql($installation, <<<'GRAPHQL'
mutation CreateStudentDiscount($basicCodeDiscount: DiscountCodeBasicInput!) {
  discountCodeBasicCreate(basicCodeDiscount: $basicCodeDiscount) {
    codeDiscountNode { id }
    userErrors { field message }
  }
}
GRAPHQL, ['basicCodeDiscount' => $input]);
        $payload = is_array($data['discountCodeBasicCreate'] ?? null) ? $data['discountCodeBasicCreate'] : [];
        $errors = $this->messages($payload['userErrors'] ?? []);
        $node = is_array($payload['codeDiscountNode'] ?? null) ? $payload['codeDiscountNode'] : [];
        $id = $node['id'] ?? null;
        if ($errors || ! $id) {
            throw new RuntimeException($errors ?: 'Shopify did not create the discount code.');
        }

        return (string) $id;
    }

    public function deleteDiscount(ShopifyInstallation $installation, string $id): void
    {
        $data = $this->graphql($installation, <<<'GRAPHQL'
mutation DeleteStudentDiscount($id: ID!) {
  discountCodeDelete(id: $id) { deletedCodeDiscountId userErrors { message } }
}
GRAPHQL, ['id' => $id]);
        $payload = is_array($data['discountCodeDelete'] ?? null) ? $data['discountCodeDelete'] : [];
        $errors = $this->messages($payload['userErrors'] ?? []);
        if ($errors) {
            throw new RuntimeException($errors);
        }
    }

    private function messages(mixed $errors): string
    {
        if (! is_array($errors)) {
            return '';
        }

        $messages = [];
        foreach ($errors as $error) {
            if (is_array($error) && is_string($error['message'] ?? null) && $error['message'] !== '') {
                $messages[] = $error['message'];
            }
        }

        return implode('; ', $messages);
    }
}
