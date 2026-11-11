<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceitAuthController extends Controller
{
    /**
     * Redirect to Faceit.
     * Manual URL generation to ensure space-separated scopes and PKCE.
     */
    public function redirect()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        // 1. Create PKCE Verifier and Challenge
        $verifier = Str::random(64);
        $hash = hash('sha256', $verifier, true);
        $challenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        // 2. Store in session
        session(['faceit_verifier' => $verifier]);
        session()->save();

        // 3. Build URL with space-separated scopes
        $query = http_build_query([
            'client_id'             => config('services.faceit.client_id'),
            'redirect_uri'          => config('services.faceit.redirect'),
            'response_type'         => 'code',
            'scope'                 => 'openid profile', // Space-separated
          	'redirect_popup'        => 'true',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect('https://accounts.faceit.com/?' . $query);
    }

    /**
     * Handle Faceit Callback.
     */
    public function callback(Request $request)
    {
        // Keep the log as requested to verify the incoming 'code'
        Log::info('Faceit Callback reached!', [
            'params' => $request->all(),
            'has_verifier' => session()->has('faceit_verifier')
        ]);

        $code = $request->query('code');
        $verifier = session()->get('faceit_verifier');

        if (!$code || !$verifier) {
            Log::error('Faceit Auth failed: Missing code or verifier.');
            return redirect('/profile')->with('error', 'Session expired or invalid request.');
        }

        try {
            // 4. Exchange Code for Token using Basic Auth
            // This bypasses the "Whitelabel Error" by providing credentials in the header
            $tokenResponse = Http::asForm()
                ->withBasicAuth(
                    config('services.faceit.client_id'), 
                    config('services.faceit.client_secret')
                )
                ->post('https://api.faceit.com/auth/v1/oauth/token', [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => config('services.faceit.redirect'),
                    'code_verifier' => $verifier,
                ]);

            if ($tokenResponse->failed()) {
                Log::error('Faceit Token Exchange Failed', ['response' => $tokenResponse->body()]);
                return redirect('/profile')->with('error', 'Faceit rejected the connection.');
            }

            $accessToken = $tokenResponse->json()['access_token'];

            // 5. Fetch User Info
            $userResponse = Http::withToken($accessToken)
                ->get('https://api.faceit.com/auth/v1/resources/userinfo');

            if ($userResponse->failed()) {
                Log::error('Faceit Profile Fetch Failed', ['response' => $userResponse->body()]);
                return redirect('/profile')->with('error', 'Could not fetch user profile.');
            }

            $faceitData = $userResponse->json();
            $user = Auth::user();

            // 6. Update User Record
            // Faceit usually uses 'guid' or 'sub' for the unique ID
            $user->update([
                'faceit_id'       => $faceitData['guid'] ?? $faceitData['sub'],
                'faceit_nickname' => $faceitData['nickname'] ?? 'Faceit User',
            ]);

            session()->forget('faceit_verifier');

            return redirect('/profile')->with('success', 'Faceit connected!');

        } catch (\Exception $e) {
            Log::error('Faceit Exception: ' . $e->getMessage());
            return redirect('/profile')->with('error', 'An unexpected error occurred.');
        }
    }
}