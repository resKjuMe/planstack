<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ApiTokenController extends Controller
{
    /**
     * Mint a personal access token for the current user and flash the plaintext
     * value once — Sanctum only ever exposes it at creation time.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()->createToken($data['name']);

        return Redirect::route('profile.edit')
            ->with('api_token', $token->plainTextToken)
            ->with('api_token_name', $data['name'])
            ->withFragment('api-tokens');
    }

    /**
     * Revoke one of the current user's tokens.
     */
    public function destroy(Request $request, string $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return Redirect::route('profile.edit')
            ->with('status', 'api-token-revoked')
            ->withFragment('api-tokens');
    }
}
