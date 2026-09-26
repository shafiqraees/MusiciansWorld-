<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerMembership extends Model { protected $guarded=[]; protected function casts(): array { return ['current_period_start'=>'datetime','current_period_end'=>'datetime','grace_ends_at'=>'datetime','cancelled_at'=>'datetime']; } public function user(){ return $this->belongsTo(User::class); } public function plan(){ return $this->belongsTo(MembershipPlan::class,'membership_plan_id'); } }

