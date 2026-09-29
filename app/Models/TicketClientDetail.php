<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketClientDetail extends Model
{
    protected $table = 'v2_ticket_client_details';

    protected $primaryKey = 'client_id';

    protected $fillable = [
        'client_type',
        'client_name',
        'client_division_agency',
        'client_empID',
        'client_email',
        'client_sex',
        'client_referenceNo',
    ];

    public function detail()
    {
        return $this->hasOne(\App\Models\TicketDetail::class,
            'client_referenceNo',   // FK on details
            'client_referenceNo'    // local key on clients
        );
    }

    public function rating()
    {
        return $this->hasOne(TicketRatingDetail::class, 'client_referenceNo', 'client_referenceNo');
    }



}
