<?php

namespace App\Providers;

use App\Services\GoogleSheetReader;
use App\Services\SheetReader;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SheetReader::class, fn () => new GoogleSheetReader(config('sheets.credentials')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Inertia pages get the resource itself, not wrapped in "data"
        JsonResource::withoutWrapping();
    }
}
