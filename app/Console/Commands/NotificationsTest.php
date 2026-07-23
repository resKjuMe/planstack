<?php

namespace App\Console\Commands;

use App\Support\NotificationBroadcaster;
use Illuminate\Console\Command;

class NotificationsTest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'notifications:test {organization : Organisations-ID (Channel organization-{id})} {--message=Testnachricht : Freitext für die Nutzlast}';

    /**
     * @var string
     */
    protected $description = 'Sendet ein Test-Event via Pusher auf organization-{id} (Header-Glocke) und zeigt Konfig + Ergebnis.';

    public function handle(NotificationBroadcaster $broadcaster): int
    {
        $orgId = (int) $this->argument('organization');
        $config = config('broadcasting.connections.pusher');

        $this->line('Pusher-Konfiguration:');
        $this->line('  app_id     : '.(! empty($config['app_id']) ? 'gesetzt ('.$config['app_id'].')' : '<FEHLT>'));
        $this->line('  key        : '.(! empty($config['key']) ? 'gesetzt ('.$config['key'].')' : '<FEHLT>'));
        $this->line('  secret     : '.(! empty($config['secret']) ? 'gesetzt' : '<FEHLT>'));
        $this->line('  cluster    : '.($config['options']['cluster'] ?? '-'));
        $this->line('  verify_ssl : '.(($config['verify_ssl'] ?? true) ? 'true' : 'false'));
        $this->newLine();

        $pusher = $broadcaster->pusher();

        if (! $pusher) {
            $this->error('Credentials unvollständig — es wird nichts gesendet. Prüfe PUSHER_APP_ID/KEY/SECRET in der .env (danach `php artisan config:clear`).');

            return self::FAILURE;
        }

        $channel = "organization-{$orgId}";
        $payload = [
            'task_id' => 0,
            'event' => 'test',
            'message' => $this->option('message'),
            'organization_id' => $orgId,
        ];

        try {
            $result = $pusher->trigger($channel, NotificationBroadcaster::EVENT, $payload);

            $this->info("Gesendet: Channel {$channel} · Event ".NotificationBroadcaster::EVENT);
            $this->line('Pusher-Antwort: '.var_export($result, true));
            $this->newLine();
            $this->line('Erwartung: Die Glocke einer im selben Org (#'.$orgId.') eingeloggten Session zählt hoch und loggt die Nutzlast in der Konsole.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Fehler beim Senden: '.get_class($e).': '.$e->getMessage());
            $this->line('Häufige Ursachen: falsche app_id/secret (401), Cluster stimmt nicht, oder keine Netzwerkausgang zu Pusher.');

            return self::FAILURE;
        }
    }
}
