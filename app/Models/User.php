<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_ADMIN = 'admin';
    public const TYPE_STAFF = 'staff';
    public const TYPES = [self::TYPE_CUSTOMER => 'Customer', self::TYPE_ADMIN => 'Internal Admin', self::TYPE_STAFF => 'Internal Staff'];
    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'];

    protected $guarded = [];
    protected $hidden = ['password','remember_token'];
    protected $attributes = ['status' => 'active', 'account_type' => self::TYPE_CUSTOMER];
    protected function casts(): array { return ['email_verified_at'=>'datetime','last_login_at'=>'datetime','password'=>'hashed','communication_preferences'=>'array']; }
    public function roles(){ return $this->belongsToMany(Role::class); }
    public function directPermissions(){ return $this->belongsToMany(Permission::class); }
    public function memberships(){ return $this->hasMany(CustomerMembership::class); }
    public function bookings(){ return $this->hasMany(Booking::class); }
    public function registrations(){ return $this->hasMany(ProgramRegistration::class); }
    public function tickets(){ return $this->hasMany(Ticket::class); }
    public function orders(){ return $this->hasMany(Order::class); }
    public function events(){ return $this->hasMany(Event::class,'organizer_id'); }
    public function organizerProfile(){ return $this->hasOne(OrganizerProfile::class); }
    public function transactions(){ return $this->hasMany(FinancialTransaction::class); }
    public function submissions(){ return $this->hasMany(FormSubmission::class); }
    public function auditActions(){ return $this->hasMany(AuditLog::class,'actor_id'); }
    public function auditHistory(){ return $this->morphMany(AuditLog::class,'auditable'); }
    public function hasRole(string $role): bool { return $this->roles->contains('name',$role); }
    public function hasAnyRole(array $roles): bool { return $this->roles->whereIn('name',$roles)->isNotEmpty(); }
    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }
        if ($this->directPermissions()->where('name', $permission)->exists()) {
            return true;
        }
        return $this->roles()->whereHas('permissionRecords', fn ($query) => $query->where('name', $permission))->exists();
    }

    /** Every permission name this user effectively holds (roles + direct grants). */
    public function effectivePermissions(): \Illuminate\Support\Collection
    {
        if ($this->hasRole('super_admin')) {
            return Permission::orderBy('group')->orderBy('label')->get();
        }
        $viaRoles = Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $this->roles->pluck('id')))->get();
        return $viaRoles->merge($this->directPermissions)->unique('id')->sortBy([['group', 'asc'], ['label', 'asc']])->values();
    }

    public function isStaffAccount(): bool
    {
        return in_array($this->account_type, [self::TYPE_ADMIN, self::TYPE_STAFF], true) || $this->hasAnyRole(Role::STAFF_ROLES);
    }

    public function isAdminAccount(): bool
    {
        return $this->account_type === self::TYPE_ADMIN || $this->hasAnyRole(Role::ADMIN_ROLES);
    }

    public function accountTypeLabel(): string
    {
        return self::TYPES[$this->account_type] ?? ucfirst((string) $this->account_type);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = implode('', array_map(fn ($part) => mb_substr($part, 0, 1), array_slice($parts, 0, 2)));
        return strtoupper($initials ?: 'U');
    }

    public function activeMembership(){ return $this->memberships()->whereIn('status',['active','trialing','grace'])->latest()->first(); }
}
