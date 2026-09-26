<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Benefit extends Model { protected $guarded=[]; public function plans(){ return $this->belongsToMany(MembershipPlan::class)->withPivot(['value','effective_from','effective_until']); } }

