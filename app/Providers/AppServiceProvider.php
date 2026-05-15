<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production') && config('app.url')) {
            URL::forceRootUrl(config('app.url'));

            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:20480'],
            'livewire.temporary_file_upload.max_upload_time' => 10,
        ]);

        Livewire::useScriptTagAttributes([
            'data-cfasync' => 'false',
        ]);
    }
}
