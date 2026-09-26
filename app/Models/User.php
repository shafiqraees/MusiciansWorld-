<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;
    protected $guarded = [];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','communication_preferences'=>'array']; }
    public function roles(){ return $this->belongsToMany(Role::class); }
    public function memberships(){ return $this->hasMany(CustomerMembership::class); }
    public function bookings(){ return $this->hasMany(Booking::class); }
    public function registrations(){ return $this->hasMany(ProgramRegistration::class); }
    public function tickets(){ return $this->hasMany(Ticket::class); }
    public function events(){ return $this->hasMany(Event::class,'organizer_id'); }
    public function organizerProfile(){ return $this->hasOne(OrganizerProfile::class); }
    public function transactions(){ return $this->hasMany(FinancialTransaction::class); }
    public function hasRole(string $role): bool { return $this->roles->contains('name',$role); }
    public function hasAnyRole(array $roles): bool { return $this->roles->whereIn('name',$roles)->isNotEmpty(); }
    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }
        return $this->roles()->whereHas('permissionRecords', fn ($query) => $query->where('name', $permission))->exists();
    }
    public function activeMembership(){ return $this->memberships()->whereIn('status',['active','trialing','grace'])->latest()->first(); }
}
