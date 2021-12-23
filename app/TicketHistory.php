<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketHistory extends Model
{
    protected $table = "tickets_history";

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'id', 'ticket_id');
    }
}