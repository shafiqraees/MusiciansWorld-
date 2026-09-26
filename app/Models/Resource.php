<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Resource extends Model { protected $guarded=[]; protected function casts(): array { return ['settings'=>'array','is_active'=>'boolean']; } public function bookings(){ return $this->hasMany(Booking::class); } public function blocks(){ return $this->hasMany(AvailabilityBlock::class); } public function pricingRules(){ return $this->hasMany(PricingRule::class); } }

