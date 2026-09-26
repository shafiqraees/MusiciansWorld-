<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ticket extends Model { protected $guarded=[]; protected function casts(): array { return ['checked_in_at'=>'datetime']; } public function event(){ return $this->belongsTo(Event::class); } public function order(){ return $this->belongsTo(Order::class); } public function ticketType(){ return $this->belongsTo(TicketType::class); } public function user(){ return $this->belongsTo(User::class); } }

