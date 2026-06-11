<?php

namespace App\Console\Commands;

use App\Mail\OperationalDigestMail;
use App\Services\OperationalAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOperationalAlerts extends Command
{
    protected $signature = 'notifications:operational-alerts {--dry-run : Listar destinatários sem enviar}';

    protected $description = 'Envia e-mail diário com retornos atrasados, OS atrasadas e preventiva vencida';

    public function handle(OperationalAlertService $alerts): int
    {
        if (! $alerts->enabled()) {
            $this->warn('Alertas operacionais desabilitados (OPERATIONAL_ALERTS_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! $alerts->hasAnyAlert()) {
            $this->info('Nenhum alerta operacional hoje — e-mail não enviado.');

            return self::SUCCESS;
        }

        $sent = 0;
        $users = $alerts->notifiableUsers();

        foreach ($users as $user) {
            $sections = $alerts->sectionsForUser($user);
            if ($sections === []) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("→ {$user->email} ({$user->name})");
                $sent++;

                continue;
            }

            Mail::to($user->email)->send(new OperationalDigestMail($user, $sections));
            $this->info("Enviado para {$user->email}");
            $sent++;
        }

        foreach ($alerts->extraRecipientEmails() as $email) {
            if ($this->option('dry-run')) {
                $this->line("→ {$email} (extra)");
                $sent++;

                continue;
            }

            $fallbackUser = new \App\Models\User(['name' => 'Equipe operacional', 'email' => $email]);
            $sections = $alerts->allSections();

            if ($sections === []) {
                continue;
            }

            Mail::to($email)->send(new OperationalDigestMail($fallbackUser, $sections));
            $this->info("Enviado para {$email} (extra)");
            $sent++;
        }

        $this->info("Concluído: {$sent} destinatário(s).");

        return self::SUCCESS;
    }
}
