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
        // Suntik konfigurasi services.keycloak secara automatik dari .env
        $existing = config('services.keycloak') ?? [];
        $realm = $existing['realms'] ?? $existing['realm'] ?? env('KEYCLOAK_REALM') ?? env('KEYCLOAK_REALMS');

        config([
            'services.keycloak' => array_merge([
                'client_id'       => env('KEYCLOAK_CLIENT_ID'),
                'client_secret'   => env('KEYCLOAK_CLIENT_SECRET'),
                'redirect'        => env('KEYCLOAK_REDIRECT_URI'),
                'base_url'        => env('KEYCLOAK_BASE_URL'),
                'close_tab'       => env('KEYCLOAK_CLOSE_TAB', false),
                'post_logout_url' => env('KEYCLOAK_POST_LOGOUT_URL'),
                'global_logout'   => env('KEYCLOAK_GLOBAL_LOGOUT', false),
                'login_redirect'  => env('KEYCLOAK_LOGIN_REDIRECT', '/dashboard'),
            ], $existing, [
                'realms'          => $realm,
                'realm'           => $realm,
            ])
        ]);
    }
}