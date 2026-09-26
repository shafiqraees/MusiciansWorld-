<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PlatformSetting extends Model { protected $guarded=[]; public static function valueOf(string $key, mixed $default=null): mixed { $row=static::where('key',$key)->first(); if(!$row)return $default; return match($row->type){'integer'=>(int)$row->value,'boolean'=>filter_var($row->value,FILTER_VALIDATE_BOOLEAN),'json'=>json_decode($row->value,true),default=>$row->value}; } }

