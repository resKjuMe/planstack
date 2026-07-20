<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Team;
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
        $inviteParam = trim((string) $request->query('invite'));
        [$organization, $invitation] = $this->resolveInvite($inviteParam);

        return view('auth.register', [
            // Rohwert unverändert durchreichen (individueller Token ist
            // Groß-/Kleinschreibungs-sensitiv; der Org-Code nicht).
            'inviteParam' => $inviteParam !== '' ? $inviteParam : null,
            'inviteOrganization' => $organization,
            // Bei einer individuellen Einladung die hinterlegte Adresse vorbefüllen.
            'prefillEmail' => $invitation?->email,
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

        // Registrierung über einen Einladungslink (?invite=…): individueller
        // Token → Organisation + hinterlegte Teams; sonst Org-Beitrittscode →
        // nur Organisation.
        [$organization, $invitation] = $this->resolveInvite(trim((string) $request->input('invite')));

        if ($organization) {
            $user->organization_id = $organization->id;
            $user->save();
        }

        if ($invitation) {
            $teamIds = Team::whereIn('id', $invitation->team_ids ?? [])->pluck('id')->all();
            if ($teamIds) {
                $user->teams()->syncWithoutDetaching($teamIds);
            }
            $invitation->forceFill(['accepted_at' => now()])->save();
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Resolve an invite token to its (pending) invitation and organization.
     * Beitritt erfolgt ausschließlich über individuelle Einladungslinks.
     *
     * @return array{0: ?Organization, 1: ?OrganizationInvitation}
     */
    private function resolveInvite(string $raw): array
    {
        if ($raw === '') {
            return [null, null];
        }

        $invitation = OrganizationInvitation::whereNull('accepted_at')
            ->where('token', $raw)
            ->first();

        return [$invitation?->organization, $invitation];
    }
}
