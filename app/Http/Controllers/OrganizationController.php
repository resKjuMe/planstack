<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Team;
use App\Models\User;
use App\Support\OrganizationTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Organisationszugehörigkeit: jeder User gehört höchstens einer Organisation an.
 * Die Seite zeigt entweder die eigene Organisation (Mitglieder, Einladungscode,
 * Austreten/Löschen) oder — falls man keiner angehört — Formulare zum Gründen
 * bzw. Beitreten (per Einladungscode).
 */
class OrganizationController extends Controller
{
    public function index(): InertiaResponse
    {
        $user = Auth::user();
        $organization = $user->organization;
        $organization?->load(['owner', 'members']);

        $isOwner = $organization && $organization->isOwner($user);

        // Teams, die der Gründer einer Einladung zuordnen kann (seine eigenen).
        $assignableTeams = $user->teams()->orderBy('name')->get();

        return Inertia::render('Organization', [
            'tabs' => $organization ? OrganizationTabs::for('organization') : null,
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'organization' => $organization ? [
                'name' => $organization->name,
                'ownerName' => $organization->owner?->name,
                'memberCount' => $organization->members->count(),
                'isOwner' => $isOwner,
                'members' => $organization->members->sortBy('name')->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'email' => $m->email,
                    'isFounder' => $organization->isOwner($m),
                    'isYou' => $m->id === $user->id,
                ])->values(),
            ] : null,
            'assignableTeams' => $assignableTeams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'urls' => [
                'store' => route('organization.store'),
                'join' => route('organization.join'),
                'invite' => route('organization.invite'),
                'leave' => route('organization.leave'),
                'destroy' => route('organization.destroy'),
            ],
            'strings' => [
                'organization' => __('common.organization'),
                'foundedBy' => __('organization.founded_by'),
                'member' => __('organization.member'),
                'members' => __('common.members'),
                'name' => __('common.name'),
                'email' => __('common.email'),
                'founder' => __('organization.founder'),
                'you' => __('organization.you'),
                'deleteConfirmTpl' => __('organization.really_delete_organization_name_all', ['name' => '__NAME__']),
                'deleteOrganization' => __('organization.delete_organization'),
                'deleteHint' => __('organization.removes_the_organization_for_all'),
                'leaveConfirm' => __('organization.really_leave_this_organization'),
                'leaveOrganization' => __('organization.leave_organization'),
                'inviteMembers' => __('organization.invite_members'),
                'inviteHint' => __('organization.send_a_personal_invitation_by_email_the'),
                'emailAddress' => __('organization.email_address'),
                'sendInvitation' => __('organization.send_invitation'),
                'emailPlaceholder' => __('organization.colleague_company_com'),
                'teamsOptional' => __('organization.teams_optional'),
                'teamsHint' => __('organization.the_invited_person_will_be_added_to'),
                'noTeamsYet' => __('organization.you_are_not_in_any_team_yet_create_some'),
                'noOrgIntro' => __('organization.you_don_t_belong_to_any_organization'),
                'createOrganization' => __('organization.create_organization'),
                'createHint' => __('organization.create_a_new_organization_you'),
                'organizationName' => __('organization.organization_name'),
                'create' => __('organization.create'),
                'orgNamePlaceholder' => __('organization.e_g_my_company'),
                'joinOrganization' => __('organization.join_organization'),
                'joinHint' => __('organization.enter_the_personal_invitation_code_from'),
                'invitationCode' => __('organization.invitation_code'),
                'join' => __('organization.join'),
                'codePlaceholder' => __('organization.code_from_the_email'),
            ],
        ]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->organization_id !== null) {
            return back()->withErrors(['name' => __('flash.already_in_organization')]);
        }

        $organization = Organization::create([
            'created_by_id' => $user->id,
            'name' => $request->validated()['name'],
        ]);

        $user->organization_id = $organization->id;
        $user->save();

        return redirect()->route('organization.index')
            ->with('status', __('flash.organization_founded', ['name' => $organization->name]));
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
            return back()->withErrors(['token' => __('flash.already_in_organization')]);
        }

        $data = $request->validate(['token' => ['required', 'string']]);
        $token = trim($data['token']);

        $invitation = OrganizationInvitation::whereNull('accepted_at')
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return back()
                ->withErrors(['token' => __('flash.invitation_code_no_match')])
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
            ->with('status', __('flash.organization_joined', ['name' => $invitation->organization->name]));
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
                    'email' => __('flash.person_in_other_organization'),
                ])->withInput();
            }

            if ($existing->organization_id === null) {
                $existing->organization_id = $organization->id;
                $existing->save();
            }

            if ($teamIds) {
                $existing->teams()->syncWithoutDetaching($teamIds);
            }

            return back()->with('status', __('flash.person_added_to_organization', ['name' => $existing->name]));
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
                'email' => __('flash.invitation_send_failed'),
            ])->withInput();
        }

        return back()->with('status', __('flash.invitation_sent', ['email' => $data['email']]));
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
                'leave' => __('flash.owner_cannot_leave'),
            ]);
        }

        $user->organization_id = null;
        $user->save();

        return redirect()->route('organization.index')
            ->with('status', __('flash.organization_left', ['name' => $organization->name]));
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
            ->with('status', __('flash.organization_deleted', ['name' => $name]));
    }
}
