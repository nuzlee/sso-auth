<?php
namespace Ekonomi\SsoAuth;

use Illuminate\Support\ServiceProvider;

class SsoAuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Muatkan routes secara automatik
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }

    public function register()
    {
        // (Kosong untuk asas)
    }
}