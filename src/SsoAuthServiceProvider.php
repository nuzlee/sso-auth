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
        // Ambil konfigurasi sedia ada (filter keluar nilai null atau string kosong)
        $existing = array_filter(config('services.keycloak') ?? [], fn ($v) => !is_null($v) && $v !== '');

        $realm        = $existing['realms'] ?? $existing['realm'] ?? env('KEYCLOAK_REALM') ?: env('KEYCLOAK_REALMS') ?: env('KE_SSO_REALM') ?: 'kementerian-ekonomi';
        $baseUrl      = $existing['base_url'] ?? env('KEYCLOAK_BASE_URL') ?: env('KE_SSO_BASE_URL') ?: 'http://10.29.59.211:8080';
        $clientId     = $existing['client_id'] ?? env('KEYCLOAK_CLIENT_ID') ?: env('KE_SSO_CLIENT_ID');
        $clientSecret = $existing['client_secret'] ?? env('KEYCLOAK_CLIENT_SECRET') ?: env('KE_SSO_CLIENT_SECRET');
        $redirect     = $existing['redirect'] ?? env('KEYCLOAK_REDIRECT_URI') ?: env('KE_SSO_REDIRECT_URI');

        config([
            'services.keycloak' => array_merge([
                'close_tab'       => env('KEYCLOAK_CLOSE_TAB', false),
                'post_logout_url' => env('KEYCLOAK_POST_LOGOUT_URL'),
                'global_logout'   => env('KEYCLOAK_GLOBAL_LOGOUT', false),
                'login_redirect'  => env('KEYCLOAK_LOGIN_REDIRECT', '/dashboard'),
            ], $existing, [
                'client_id'       => $clientId,
                'client_secret'   => $clientSecret,
                'redirect'        => $redirect,
                'base_url'        => $baseUrl,
                'realms'          => $realm,
                'realm'           => $realm,
            ])
        ]);
    }
}