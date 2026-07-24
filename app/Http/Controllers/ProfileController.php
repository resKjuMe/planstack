<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        $locale = app()->getLocale();

        return Inertia::render('Profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'notificationDisplay' => $user->notification_display,
                'isUnverified' => $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail(),
            ],
            'urls' => [
                'profileUpdate' => route('profile.update'),
                'passwordUpdate' => route('password.update'),
                'destroy' => route('profile.destroy'),
                'verificationSend' => route('verification.send'),
                'tokenStore' => route('profile.tokens.store'),
                'tokenDestroy' => route('profile.tokens.destroy', '__ID__'),
            ],
            'flash' => [
                'status' => session('status'),
                'apiToken' => session('api_token'),
                'apiTokenName' => session('api_token_name'),
            ],
            // Token-Liste asynchron nachladen (kleiner DB-Zugriff) — Skeleton dort.
            'tokens' => Inertia::defer(fn () => $user->tokens()->latest()->get()->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'created' => $token->created_at->locale($locale)->diffForHumans(),
                'lastUsed' => $token->last_used_at?->locale($locale)->diffForHumans(),
            ])->values()),
            'strings' => [
                'profile' => __('common.profile'),
                'profileData' => __('profile.profile_data'),
                'profileDataHint' => __('profile.update_your_account_s_profile'),
                'name' => __('common.name'),
                'email' => __('common.email'),
                'unverified' => __('profile.your_email_address_is_unverified'),
                'resend' => __('profile.click_here_to_re_send_the_verification'),
                'verificationSent' => __('profile.a_new_verification_link_has_been_sent'),
                'language' => __('profile.language'),
                'german' => __('profile.german'),
                'englishUs' => __('profile.english_us'),
                'notificationDisplay' => __('profile.notification_display'),
                'notificationDisplayHint' => __('profile.notification_display_hint'),
                'notificationDropdown' => __('profile.notification_dropdown'),
                'notificationSidebar' => __('profile.notification_sidebar'),
                'save' => __('common.save'),
                'saved' => __('profile.saved'),
                'updatePassword' => __('profile.update_password'),
                'updatePasswordHint' => __('profile.use_a_long_random_password_to_keep_your'),
                'currentPassword' => __('profile.current_password'),
                'newPassword' => __('profile.new_password'),
                'confirmPassword' => __('common.confirm_password'),
                'apiTokens' => __('profile.api_tokens'),
                'apiTokensHint' => __('profile.personal_access_tokens_for_the'),
                'copyNowTpl' => __('profile.new_token_name_copy_it_now_it_won_t_be', ['name' => '__NAME__']),
                'copy' => __('profile.copy'),
                'copied' => __('common.copied'),
                'tokenRevoked' => __('profile.token_revoked'),
                'tokenName' => __('profile.token_name'),
                'createToken' => __('profile.create_token'),
                'created' => __('profile.created'),
                'lastUsed' => __('profile.last_used'),
                'neverUsed' => __('profile.never_used'),
                'revoke' => __('profile.revoke'),
                'revokeConfirmTpl' => __('profile.revoke_token_name_applications_using_it', ['name' => '__NAME__']),
                'noTokens' => __('profile.no_tokens_yet'),
                'deleteAccount' => __('profile.delete_account'),
                'deleteAccountHint' => __('profile.once_your_account_is_deleted_all_of_its_2'),
                'deleteConfirmTitle' => __('profile.are_you_sure_you_want_to_delete_your'),
                'deleteConfirmHint' => __('profile.once_your_account_is_deleted_all_of_its'),
                'password' => __('common.password'),
                'cancel' => __('common.cancel'),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
