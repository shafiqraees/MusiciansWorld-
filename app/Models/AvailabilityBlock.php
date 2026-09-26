<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AvailabilityBlock extends Model { protected $guarded=[]; protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime']; } public function resource(){ return $this->belongsTo(Resource::class); } }

