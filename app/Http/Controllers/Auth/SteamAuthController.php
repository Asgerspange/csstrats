<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Ilzrv\LaravelSteamAuth\Exceptions\Authentication\SteamResponseNotValidAuthenticationException;
use Ilzrv\LaravelSteamAuth\Exceptions\Validation\ValidationException;
use Ilzrv\LaravelSteamAuth\SteamAuthenticator;
use Ilzrv\LaravelSteamAuth\SteamUserDto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

final class SteamAuthController
{
    public function __invoke(
        Request $request,
        Redirector $redirector,
        Client $client,
        HttpFactory $httpFactory,
        AuthManager $authManager,
    ): RedirectResponse {
        $steamAuthenticator = new SteamAuthenticator(
            new Uri($request->getUri()),
            $client,
            $httpFactory,
        );

        try {
            $steamAuthenticator->auth();
        } catch (ValidationException|SteamResponseNotValidAuthenticationException) {
            return $redirector->to(
                $steamAuthenticator->buildAuthUrl()
            );
        }

        $steamUser = $steamAuthenticator->getSteamUser();

        $authManager->login(
            $this->updateUser($steamUser),
            true
        );

        return $redirector->to('/profile');
    }

    private function updateUser(SteamUserDto $steamUser): User
    {
        $user = Auth::user();

        $user->userInfo->steam_id = $steamUser->getSteamId();
        $user->userInfo->country_code = $steamUser->getLocCountryCode();

        $user->userInfo->save();

        Cache::forget('global-user:' . Auth::id());
        return $user;
    }
}