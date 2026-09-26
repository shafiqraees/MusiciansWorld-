<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function permissionRecords()
    {
        return $this->belongsToMany(Permission::class);
    }
}

