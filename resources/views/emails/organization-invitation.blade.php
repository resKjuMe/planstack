<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einladung zu {{ $organization->name }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#111827;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <h1 style="margin:0 0 16px; font-size:20px; font-weight:700;">Einladung zu {{ $organization->name }}</h1>

            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#374151;">
                {{ $inviter->name }} lädt dich ein, der Organisation
                <strong>{{ $organization->name }}</strong> auf Planstack beizutreten.
            </p>

            <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#374151;">
                Erstelle über den folgenden Button ein Konto – du wirst dabei automatisch
                der Organisation zugeordnet.
            </p>

            <p style="margin:0 0 24px;">
                <a href="{{ $registerUrl }}"
                   style="display:inline-block; background:#4f46e5; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:12px 20px; border-radius:8px;">
                    Konto erstellen &amp; beitreten
                </a>
            </p>

            <p style="margin:0 0 8px; font-size:12px; line-height:1.6; color:#6b7280;">
                Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:
            </p>
            <p style="margin:0 0 24px; font-size:12px; line-height:1.6; word-break:break-all;">
                <a href="{{ $registerUrl }}" style="color:#4f46e5;">{{ $registerUrl }}</a>
            </p>

            <div style="margin:0 0 24px; padding:16px; background:#f9fafb; border-radius:8px;">
                <p style="margin:0 0 8px; font-size:12px; line-height:1.6; color:#6b7280;">
                    Du hast bereits ein Konto? Dann melde dich an, öffne „Organisation" und gib dort diesen persönlichen Code ein:
                </p>
                <p style="margin:0; font-family:monospace; font-size:13px; word-break:break-all; color:#111827;">{{ $inviteCode }}</p>
            </div>

            <p style="margin:0; font-size:12px; line-height:1.6; color:#9ca3af;">
                Du hast diese Einladung nicht erwartet? Dann kannst du diese E-Mail ignorieren.
            </p>
        </div>
    </div>
</body>
</html>
