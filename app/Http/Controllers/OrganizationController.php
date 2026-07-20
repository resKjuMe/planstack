<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Organisationszugehörigkeit: jeder User gehört höchstens einer Organisation an.
 * Die Seite zeigt entweder die eigene Organisation (Mitglieder, Einladungscode,
 * Austreten/Löschen) oder — falls man keiner angehört — Formulare zum Gründen
 * bzw. Beitreten (per Einladungscode).
 */
class OrganizationController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $organization = $user->organization;
        $organization?->load(['owner', 'members']);

        // Teams, die der Gründer einer Einladung zuordnen kann (seine eigenen).
        $assignableTeams = $user->teams()->orderBy('name')->get();

        return view('organization.index', compact('user', 'organization', 'assignableTeams'));
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->organization_id !== null) {
            return back()->withErrors(['name' => 'Du gehörst bereits einer Organisation an.']);
        }

        $organization = Organization::create([
            'created_by_id' => $user->id,
            'name' => $request->validated()['name'],
        ]);

        $user->organization_id = $organization->id;
        $user->save();

        return redirect()->route('organization.index')
            ->with('status', "Organisation \"{$organization->name}\" gegründet.");
    }

    /**
     * Beitritt eines bereits registrierten Users über den individuellen Code aus
     * der Einladungs-E-Mail (der Registrierungslink selbst ist nur für neue
     * Konten). Ordnet Organisation und hinterlegte Teams zu.
     */
    public function join(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->organization_id !== null) {
            return back()->withErrors(['token' => 'Du gehörst bereits einer Organisation an.']);
        }

        $data = $request->validate(['token' => ['required', 'string']]);
        $token = trim($data['token']);

        $invitation = OrganizationInvitation::whereNull('accepted_at')
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return back()
                ->withErrors(['token' => 'Kein Treffer für diesen Einladungscode.'])
                ->withInput();
        }

        $user->organization_id = $invitation->organization_id;
        $user->save();

        $teamIds = Team::whereIn('id', $invitation->team_ids ?? [])->pluck('id')->all();
        if ($teamIds) {
            $user->teams()->syncWithoutDetaching($teamIds);
        }

        $invitation->forceFill(['accepted_at' => now()])->save();

        return redirect()->route('organization.index')
            ->with('status', "Du bist der Organisation \"{$invitation->organization->name}\" beigetreten.");
    }

    /**
     * Lädt eine Person per E-Mail ein. Existiert bereits ein Konto mit dieser
     * Adresse, wird es direkt der Organisation und den gewählten Teams
     * zugeordnet; andernfalls wird ein individueller Registrierungslink
     * verschickt (?invite=TOKEN → Zuordnung nach der Registrierung).
     */
    public function invite(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->organization;

        // Nur der Gründer der Organisation darf Einladungen versenden.
        if (! $organization || ! $organization->isOwner($user)) {
            abort(403);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer'],
        ]);

        // Nur eigene Teams des Gründers dürfen zugeordnet werden.
        $allowedTeamIds = $user->teams()->pluck('teams.id')->all();
        $teamIds = array_values(array_intersect(
            array_map('intval', $data['team_ids'] ?? []),
            $allowedTeamIds,
        ));

        // Existiert bereits ein Konto mit dieser Adresse? Dann direkt zuordnen
        // statt eine Registrierung anzustoßen.
        $existing = User::where('email', $data['email'])->first();
        if ($existing) {
            if ($existing->organization_id !== null && $existing->organization_id !== $organization->id) {
                return back()->withErrors([
                    'email' => 'Diese Person gehört bereits einer anderen Organisation an.',
                ])->withInput();
            }

            if ($existing->organization_id === null) {
                $existing->organization_id = $organization->id;
                $existing->save();
            }

            if ($teamIds) {
                $existing->teams()->syncWithoutDetaching($teamIds);
            }

            return back()->with('status', "{$existing->name} wurde der Organisation hinzugefügt.");
        }

        // Individuelle, einmalige Einladung anlegen.
        $invitation = $organization->invitations()->create([
            'created_by_id' => $user->id,
            'email' => $data['email'],
            'token' => OrganizationInvitation::generateToken(),
            'team_ids' => $teamIds ?: null,
        ]);

        $registerUrl = route('register', ['invite' => $invitation->token]);

        try {
            Mail::to($data['email'])->send(
                new OrganizationInvitationMail($organization, $user, $registerUrl, $invitation->token)
            );
        } catch (\Throwable $e) {
            report($e);
            $invitation->delete();

            return back()->withErrors([
                'email' => 'Die Einladung konnte nicht versendet werden. Bitte später erneut versuchen.',
            ])->withInput();
        }

        return back()->with('status', "Einladung an {$data['email']} versendet.");
    }

    public function leave(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->organization;

        if (! $organization) {
            return redirect()->route('organization.index');
        }

        if ($organization->isOwner($user)) {
            return back()->withErrors([
                'leave' => 'Als Gründer kannst du nicht austreten – lösche stattdessen die Organisation.',
            ]);
        }

        $user->organization_id = null;
        $user->save();

        return redirect()->route('organization.index')
            ->with('status', "Du hast die Organisation \"{$organization->name}\" verlassen.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->organization;

        if (! $organization || ! $organization->isOwner($user)) {
            abort(403);
        }

        // Die Zugehörigkeit der Mitglieder wird über den FK (nullOnDelete) gelöst,
        // die User selbst bleiben erhalten.
        $name = $organization->name;
        $organization->delete();

        return redirect()->route('organization.index')
            ->with('status', "Organisation \"{$name}\" gelöscht.");
    }
}
