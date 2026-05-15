<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:20480'],
            'livewire.temporary_file_upload.max_upload_time' => 10,
        ]);

        Livewire::useScriptTagAttributes([
            'data-cfasync' => 'false',
        ]);
    }
}
