<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { protected $guarded=[]; protected function casts(): array { return ['before'=>'array','after'=>'array']; } public function actor(){ return $this->belongsTo(User::class,'actor_id'); } public function auditable(){ return $this->morphTo(); } }

