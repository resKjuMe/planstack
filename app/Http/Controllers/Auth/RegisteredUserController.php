<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view. An optional ?invite=CODE pre-selects the
     * organization the new account will be assigned to (see store()).
     */
    public function create(Request $request): View
    {
        $inviteCode = $this->normalizeInviteCode($request->query('invite'));

        return view('auth.register', [
            'inviteCode' => $inviteCode,
            'inviteOrganization' => $inviteCode
                ? Organization::where('invite_code', $inviteCode)->first()
                : null,
            // Aus dem Einladungslink vorbefüllte E-Mail-Adresse.
            'prefillEmail' => $request->query('email'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Registrierung über einen Einladungslink (?invite=CODE) ordnet das neue
        // Konto direkt der eingeladenen Organisation zu.
        if ($code = $this->normalizeInviteCode($request->input('invite'))) {
            if ($organization = Organization::where('invite_code', $code)->first()) {
                $user->organization_id = $organization->id;
                $user->save();
            }
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Normalize a submitted invite code (strip separators, upper-case). Returns
     * null for empty input.
     */
    private function normalizeInviteCode(?string $raw): ?string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $raw));

        return $code !== '' ? $code : null;
    }
}
