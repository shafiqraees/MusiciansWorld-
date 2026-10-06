<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model
{
    /** Roles that grant access to the internal operations portal. */
    public const STAFF_ROLES = ['super_admin', 'finance', 'operations', 'check_in', 'instructor'];
    /** Roles considered "Internal Admin" level. */
    public const ADMIN_ROLES = ['super_admin', 'finance'];
    /** Roles that belong to public customers. */
    public const CUSTOMER_ROLES = ['customer', 'organizer'];

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

    public function isStaffRole(): bool
    {
        return in_array($this->name, self::STAFF_ROLES, true);
    }
}
