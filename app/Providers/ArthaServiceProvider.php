<?php

declare(strict_types=1);

namespace App\Providers;

use App\Artha\Http\Controllers\ArthaSsoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ArthaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->group(base_path('routes/artha.php'));

        // Suite single sign-on: the CRM hands a signed user off here. Needs the
        // web group (session + cookies) so the logged-in state actually sticks.
        Route::middleware('web')->group(function (): void {
            Route::get('artha/sso/callback', [ArthaSsoController::class, 'callback'])
                ->name('artha.sso.callback');
        });
    }
}
