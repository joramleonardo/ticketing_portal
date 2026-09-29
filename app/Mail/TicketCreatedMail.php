<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $refNo;
    public string $clientName;
    public ?string $problem;
    public ?string $employeeCode;

    public function __construct(string $refNo, string $clientName, ?string $problem = null, ?string $employeeCode = null)
    {
        $this->refNo        = $refNo;
        $this->clientName   = $clientName;
        $this->problem      = $problem;
        $this->employeeCode = $employeeCode;
    }

    public function build()
    {
        return $this->subject("Ticket Created")
            ->markdown('emails.tickets.created');
    }
}
