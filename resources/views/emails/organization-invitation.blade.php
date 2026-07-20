<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.invitation_to_organization', ['organization' => $organization->name]) }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#111827;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <h1 style="margin:0 0 16px; font-size:20px; font-weight:700;">{{ __('emails.invitation_to_organization', ['organization' => $organization->name]) }}</h1>

            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#374151;">
                {{ __('emails.inviter_is_inviting_you_to_join_the', ['inviter' => $inviter->name]) }}
                <strong>{{ $organization->name }}</strong> {{ __('emails.on_planstack') }}
            </p>

            <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#374151;">
                {{ __('emails.create_an_account_using_the_button') }}
            </p>

            <p style="margin:0 0 24px;">
                <a href="{{ $registerUrl }}"
                   style="display:inline-block; background:#4f46e5; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:12px 20px; border-radius:8px;">
                    {{ __('emails.create_account_join') }}
                </a>
            </p>

            <p style="margin:0 0 8px; font-size:12px; line-height:1.6; color:#6b7280;">
                {{ __('emails.if_the_button_doesn_t_work_copy_this') }}
            </p>
            <p style="margin:0 0 24px; font-size:12px; line-height:1.6; word-break:break-all;">
                <a href="{{ $registerUrl }}" style="color:#4f46e5;">{{ $registerUrl }}</a>
            </p>

            <div style="margin:0 0 24px; padding:16px; background:#f9fafb; border-radius:8px;">
                <p style="margin:0 0 8px; font-size:12px; line-height:1.6; color:#6b7280;">
                    {{ __('emails.already_have_an_account_sign_in_open') }}
                </p>
                <p style="margin:0; font-family:monospace; font-size:13px; word-break:break-all; color:#111827;">{{ $inviteCode }}</p>
            </div>

            <p style="margin:0; font-size:12px; line-height:1.6; color:#9ca3af;">
                {{ __('emails.weren_t_expecting_this_invitation_you') }}
            </p>
        </div>
    </div>
</body>
</html>
