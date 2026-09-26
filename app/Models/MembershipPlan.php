<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MembershipPlan extends Model { protected $guarded=[]; public function benefits(){ return $this->belongsToMany(Benefit::class)->withPivot(['value','effective_from','effective_until']); } public function memberships(){ return $this->hasMany(CustomerMembership::class); } }

