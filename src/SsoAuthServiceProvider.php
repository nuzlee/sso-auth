<?php
namespace Ekonomi\SsoAuth;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Route;

class SsoAuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Muatkan routes secara automatik dengan middleware 'web' supaya Session boleh dibaca
        Route::middleware('web')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        });
    }

    public function register()
    {
        // (Kosong untuk asas)
    }
}