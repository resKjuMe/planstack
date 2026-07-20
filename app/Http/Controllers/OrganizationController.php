<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
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

        return view('organization.index', compact('user', 'organization'));
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
            'invite_code' => Organization::generateInviteCode(),
        ]);

        $user->organization_id = $organization->id;
        $user->save();

        return redirect()->route('organization.index')
            ->with('status', "Organisation \"{$organization->name}\" gegründet.");
    }

    public function join(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->organization_id !== null) {
            return back()->withErrors(['invite_code' => 'Du gehörst bereits einer Organisation an.']);
        }

        $request->validate(['invite_code' => ['required', 'string', 'max:16']]);

        // Eingabe tolerant normalisieren (Bindestriche/Leerzeichen, Groß-/Kleinschreibung).
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('invite_code')));
        $organization = Organization::where('invite_code', $code)->first();

        if (! $organization) {
            return back()
                ->withErrors(['invite_code' => 'Kein Treffer für diesen Einladungscode.'])
                ->withInput();
        }

        $user->organization_id = $organization->id;
        $user->save();

        return redirect()->route('organization.index')
            ->with('status', "Du bist der Organisation \"{$organization->name}\" beigetreten.");
    }

    /**
     * Verschickt einen Registrierungs-Einladungslink per E-Mail. Über den Link
     * (?invite=CODE) wird das neue Konto automatisch dieser Organisation
     * zugeordnet.
     */
    public function invite(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->organization;

        // Nur der Gründer der Organisation darf Einladungen versenden.
        if (! $organization || ! $organization->isOwner($user)) {
            abort(403);
        }

        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Empfänger-Adresse mitgeben, damit sie auf der Registrierungsseite
        // vorbefüllt ist.
        $registerUrl = route('register', [
            'invite' => $organization->invite_code,
            'email' => $data['email'],
        ]);

        try {
            Mail::to($data['email'])->send(
                new OrganizationInvitationMail($organization, $user, $registerUrl)
            );
        } catch (\Throwable $e) {
            report($e);

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
