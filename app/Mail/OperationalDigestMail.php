<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperationalDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $sections
     */
    public function __construct(
        public User $user,
        public array $sections,
    ) {}

    public function envelope(): Envelope
    {
        $parts = [];
        if (isset($this->sections['overdue_returns'])) {
            $parts[] = count($this->sections['overdue_returns']).' retorno(s)';
        }
        if (isset($this->sections['overdue_orders'])) {
            $parts[] = count($this->sections['overdue_orders']).' OS';
        }
        if (isset($this->sections['preventive_due'])) {
            $parts[] = count($this->sections['preventive_due']).' preventiva(s)';
        }

        $summary = $parts !== [] ? implode(', ', $parts) : 'alertas';

        return new Envelope(
            subject: 'Gestão Acesso — Alertas operacionais ('.$summary.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.operational-digest',
        );
    }
}
