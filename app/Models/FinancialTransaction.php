<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinancialTransaction extends Model { protected $guarded=[]; protected function casts(): array { return ['meta'=>'array']; } public function transactable(){ return $this->morphTo(); } public function user(){ return $this->belongsTo(User::class); } }

