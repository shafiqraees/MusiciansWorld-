<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Order extends Model { protected $guarded=[]; public function event(){ return $this->belongsTo(Event::class); } public function user(){ return $this->belongsTo(User::class); } public function items(){ return $this->hasMany(OrderItem::class); } public function tickets(){ return $this->hasMany(Ticket::class); } public function transactions(){ return $this->morphMany(FinancialTransaction::class,'transactable'); } }

