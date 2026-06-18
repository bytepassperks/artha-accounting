<?php

declare(strict_types=1);

use App\Artha\Http\Controllers\ArthaApiController;
use App\Artha\Http\Middleware\VerifyArthaToken;
use Illuminate\Support\Facades\Route;

/*
| Read-only cross-module API for the Artha Business OS suite. Other modules
| (CRM "Ask Artha", the Automations engine) consume these endpoints with the
| shared bearer token (config: artha.api_token). All routes are additive and
| live in isolated files so upstream erpsaas updates merge without conflict.
*/

Route::prefix('api/artha')
    ->middleware(VerifyArthaToken::class)
    ->group(function (): void {
        Route::get('ping', [ArthaApiController::class, 'ping']);
        Route::get('customers', [ArthaApiController::class, 'customers']);
        Route::get('invoices', [ArthaApiController::class, 'invoices']);
        Route::get('summary', [ArthaApiController::class, 'summary']);
    });
