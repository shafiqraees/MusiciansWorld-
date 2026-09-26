<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PricingRule extends Model { protected $guarded=[]; protected function casts(): array { return ['conditions'=>'array','is_active'=>'boolean','effective_from'=>'datetime','effective_until'=>'datetime']; } public function resource(){ return $this->belongsTo(Resource::class); } }

