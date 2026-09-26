<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizerProfile extends Model { protected $guarded=[]; protected function casts(): array { return ['requirements'=>'array','is_verified'=>'boolean','stripe_charges_enabled'=>'boolean','stripe_payouts_enabled'=>'boolean']; } public function user(){ return $this->belongsTo(User::class); } }

