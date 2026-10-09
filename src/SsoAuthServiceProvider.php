<?php
namespace Ekonomi\SsoAuth;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;

class SsoAuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Daftarkan Driver Keycloak secara automatik (Bypass masalah 'Driver not supported')
        Event::listen(
            \SocialiteProviders\Manager\SocialiteWasCalled::class,
            [\SocialiteProviders\Keycloak\KeycloakExtendSocialite::class, 'handle']
        );

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