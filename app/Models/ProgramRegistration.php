<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProgramRegistration extends Model { protected $guarded=[]; protected function casts(): array { return ['answers'=>'array']; } public function user(){ return $this->belongsTo(User::class); } public function program(){ return $this->belongsTo(Program::class); } public function instructor(){ return $this->belongsTo(User::class,'instructor_id'); } }

