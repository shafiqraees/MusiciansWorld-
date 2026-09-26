<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizerSettlement extends Model { protected $guarded=[]; protected function casts(): array { return ['approved_at'=>'datetime','paid_at'=>'datetime']; } public function event(){ return $this->belongsTo(Event::class); } }

