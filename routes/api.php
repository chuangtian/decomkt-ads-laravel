<?php

use App\Http\Controllers\Api\PublicStudentDiscountController;
use App\Http\Controllers\Api\ShopifyStudentDiscountController;
use App\Http\Controllers\Api\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('student-discount.cors')->group(function (): void {
        Route::options('/public/student-discounts', [PublicStudentDiscountController::class, 'options']);
        Route::get('/public/student-discounts', [PublicStudentDiscountController::class, 'show']);
        Route::post('/public/student-discounts', [PublicStudentDiscountController::class, 'store']);
    });
    Route::post('/webhooks/shopify/app-uninstalled', [ShopifyWebhookController::class, 'uninstall']);

    Route::middleware('shopify.session')->prefix('shopify/student-discounts')->group(function (): void {
        Route::get('/', [ShopifyStudentDiscountController::class, 'index']);
        Route::put('/', [ShopifyStudentDiscountController::class, 'update']);
        Route::put('/smtp', [ShopifyStudentDiscountController::class, 'updateSmtp']);
        Route::delete('/smtp', [ShopifyStudentDiscountController::class, 'resetSmtp']);
        Route::patch('/claims/{claim}', [ShopifyStudentDiscountController::class, 'review']);
        Route::get('/claims/{claim}/evidence', [ShopifyStudentDiscountController::class, 'evidence']);
    });
});
