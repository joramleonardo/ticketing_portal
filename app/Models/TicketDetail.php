<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketDetail extends Model
{
    protected $table = 'v2_ticket_details';

    protected $primaryKey = 'ticket_id';

    protected $fillable = [
        'client_referenceNo',
        'ticket_type',
        'ticket_nature_of_support',
        'ticket_support_type',
        'ticket_assigned_by',
        'ticket_assigned_staff',
        'ticket_attended_by',
        'ticket_assisted_by',
        'ticket_status',
        'ticket_problem_that_needed_support',
        'ticket_actions_taken',
        'ticket_remarks',
        'date_requested',
        'date_completed',
    ];

    public function client()
    {
        return $this->belongsTo(V2TicketClientDetail::class, 'client_referenceNo', 'client_referenceNo');
    }

    public function rating()
    {
        return $this->hasOne(TicketRatingDetail::class, 'client_referenceNo', 'client_referenceNo');
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachmentDetail::class, 'client_referenceNo', 'client_referenceNo');
    }

}
