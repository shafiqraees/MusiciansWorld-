<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EventService extends Model { protected $guarded=[]; protected function casts(): array { return ['requirements'=>'array','is_active'=>'boolean']; } }

