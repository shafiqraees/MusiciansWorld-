<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Program extends Model { protected $guarded=[]; protected function casts(): array { return ['schedule'=>'array','payment_required'=>'boolean','is_active'=>'boolean']; } public function registrations(){ return $this->hasMany(ProgramRegistration::class); } }

