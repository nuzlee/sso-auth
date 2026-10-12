<?php
namespace Ekonomi\SsoAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;

class SsoController
{
    public function redirect()
    {
        return \Laravel\Socialite\Facades\Socialite::driver('keycloak')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            if (!$request->has('code')) {
                throw new \Exception('Tiada kod pengesahan dari Keycloak.');
            }

            // 1. Minta Token dari Keycloak
            $tokenUrl = rtrim(config('services.keycloak.base_url'), '/')
                . '/realms/' . config('services.keycloak.realms')
                . '/protocol/openid-connect/token';

            $response = Http::asForm()->post($tokenUrl, [
                'grant_type'    => 'authorization_code',
                'client_id'     => config('services.keycloak.client_id'),
                'client_secret' => config('services.keycloak.client_secret'),
                'redirect_uri'  => config('services.keycloak.redirect'),
                'code'          => $request->code,
            ]);

            if ($response->failed()) {
                throw new \Exception('Gagal mendapatkan token dari Keycloak.');
            }

            $idToken = $response->json('id_token');
            if (!$idToken) {
                throw new \Exception('Tiada ID Token (JWT) dalam respons.');
            }

            // 2. Decode JWT payload
            $parts = explode('.', $idToken);
            if (count($parts) < 3) {
                throw new \Exception('Format ID Token tidak sah.');
            }
            $decoded = json_decode(
                base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])),
                true
            );

            $username = $decoded['preferred_username'] ?? null;
            $email    = $decoded['email'] ?? null;
            $name     = $decoded['name'] ?? $username;

            if (!$username) {
                throw new \Exception('Maklumat pengguna tidak lengkap dalam token SSO.');
            }

            // 3. Cari pengguna sedia ada (cuba username dulu, kemudian email)
            $user = User::where('username', $username)->first();
            if (!$user && $email) {
                $user = User::where('email', $email)->first();
            }

            // 4. Sediakan data — auto-detect lajur yang wujud dalam jadual
            $table = (new User)->getTable();

            // Peta data dari token Keycloak ke lajur database
            $fieldMap = [
                'name'     => $name,
                'username' => $username,
                'email'    => $email,
                'nokp'     => $decoded['icno'] ?? null,
            ];

            // Hanya masukkan data untuk lajur yang benar-benar wujud
            $safeData = [];
            foreach ($fieldMap as $col => $val) {
                if ($val !== null && Schema::hasColumn($table, $col)) {
                    $safeData[$col] = $val;
                }
            }

            // sso_profile — kendalikan cast secara automatik
            if (Schema::hasColumn($table, 'sso_profile')) {
                $casts = (new User)->getCasts();
                $safeData['sso_profile'] = isset($casts['sso_profile']) ? $decoded : json_encode($decoded);
            }

            // 5. Kemaskini atau cipta pengguna
            if ($user) {
                // Pengguna sedia ada: kemaskini profil sahaja
                $user->forceFill($safeData)->save();
            } else {
                // Pengguna baru: cipta akaun
                $safeData['password'] = bcrypt(Str::random(16));

                // Isi lajur NOT NULL yang tiada data dari Keycloak dengan nilai fallback
                if (Schema::hasColumn($table, 'nokp') && empty($safeData['nokp'])) {
                    $safeData['nokp'] = $username;
                }

                $user = new User();
                $user->forceFill($safeData)->save();
            }

            // 6. Simpan token dalam Session untuk Seamless Logout
            Session::put('sso_id_token', $idToken);

            // 7. Log masuk dan halakan ke halaman utama sistem
            Auth::login($user);

            return redirect()->intended(
                env('KEYCLOAK_LOGIN_REDIRECT', '/dashboard')
            );

        } catch (\Exception $e) {
            Log::error('SSO Login Error: ' . $e->getMessage());
            return redirect('/login')->with('error', 'Ralat SSO: ' . $e->getMessage());
        }
    }

    public function logout(Request $request)
    {
        $idTokenHint = Session::get('sso_id_token');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // JIKA PENGGUNA LOG MASUK SECARA NATIVE (Direct Login)
        if (empty($idTokenHint)) {
            return redirect('/login')->with('logout_success', 'Anda telah berjaya log keluar.');
        }

        // JIKA PENGGUNA LOG MASUK MELALUI SSO
        if (env('KEYCLOAK_CLOSE_TAB', false)) {
            $postLogoutUri = url('/auth/close-tab');
        } else {
            $postLogoutUri = env('KEYCLOAK_POST_LOGOUT_URL', url('/'));
        }

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

    public function closeTab()
    {
        $fallbackUrl = env('KEYCLOAK_POST_LOGOUT_URL', url('/'));
        return response(
            '<script>
                window.close();
                setTimeout(function() {
                    window.location.href = "'.$fallbackUrl.'";
                }, 1000);
            </script>'
        );
    }
}