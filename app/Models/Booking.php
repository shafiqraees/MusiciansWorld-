<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Booking extends Model { protected $guarded=[]; protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime','hold_expires_at'=>'datetime','meta'=>'array']; } public function user(){ return $this->belongsTo(User::class); } public function resource(){ return $this->belongsTo(Resource::class); } public function transactions(){ return $this->morphMany(FinancialTransaction::class,'transactable'); } }

