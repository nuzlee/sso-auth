<?php
namespace Ekonomi\SsoAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;

class SsoController
{
    public function redirect()
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            if (!$request->has('code')) {
                throw new \Exception('Tiada kod pengesahan dari Keycloak.');
            }

            // 1. Minta Token
            $response = Http::asForm()->post(config('services.keycloak.base_url') . '/realms/' . config('services.keycloak.realms') . '/protocol/openid-connect/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.keycloak.client_id'),
                'client_secret' => config('services.keycloak.client_secret'),
                'redirect_uri' => config('services.keycloak.redirect'),
                'code' => $request->code,
            ]);

            if ($response->failed()) {
                throw new \Exception('Gagal mendapatkan token.');
            }

            $data = $response->json();
            $idToken = $data['id_token'] ?? null;

            if (!$idToken) {
                throw new \Exception('Tiada ID Token (JWT).');
            }

            // 2. Decode ID Token
            $payload = explode('.', $idToken)[1];
            $decoded = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

            $username = $decoded['preferred_username'] ?? null;
            $email    = $decoded['email'] ?? null;
            $name     = $decoded['name'] ?? $username;

            if (!$username) {
                throw new \Exception('Gagal mengekstrak maklumat pengguna.');
            }
            
            // 3. Simpan ke Database
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name'        => $name,
                    'email'       => $email,
                    'sso_profile' => $decoded,
                    'password'    => bcrypt(\Illuminate\Support\Str::random(16)),
                ]
            );
            
            if ($user->wasRecentlyCreated && empty($user->id_peranan)) {
                $user->update(['id_peranan' => 2]);
            }
            
            // 4. Simpan Session untuk Seamless Logout
            Session::put('sso_id_token', $idToken);
            
            Auth::login($user);
            return redirect('/home'); // Atau dashboard mengikut sistem masing-masing
            
        } catch (\Exception $e) {
            return redirect('/')->withErrors(['error' => 'Ralat SSO: ' . $e->getMessage()]);
        }
    }

    public function logout(Request $request)
    {
        $idTokenHint = Session::get('sso_id_token');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $postLogoutUri = url('/');
        $keycloakBaseUrl = config('services.keycloak.base_url');
        $keycloakRealm   = config('services.keycloak.realms');

        if ($keycloakBaseUrl && $keycloakRealm) {
            $params = [
                'post_logout_redirect_uri' => $postLogoutUri,
                'client_id' => config('services.keycloak.client_id'),
            ];
            
            if ($idTokenHint) {
                $params['id_token_hint'] = $idTokenHint;
            }

            $keycloakLogoutUrl = rtrim($keycloakBaseUrl, '/') 
                . '/realms/' . $keycloakRealm 
                . '/protocol/openid-connect/logout?' . http_build_query($params);

            return redirect()->away($keycloakLogoutUrl);
        }

        return redirect('/');
    }
}