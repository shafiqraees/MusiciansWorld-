<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TicketType extends Model { protected $guarded=[]; protected function casts(): array { return ['sales_start_at'=>'datetime','sales_end_at'=>'datetime','is_refundable'=>'boolean']; } public function event(){ return $this->belongsTo(Event::class); } public function tickets(){ return $this->hasMany(Ticket::class); } }

