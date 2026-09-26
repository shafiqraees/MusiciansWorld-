<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IntegrationJob extends Model { protected $guarded=[]; protected function casts(): array { return ['payload'=>'array','next_attempt_at'=>'datetime']; } public function subject(){ return $this->morphTo(); } }

