<?php

namespace App\Models;

use App\Models\DatabaseNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

// class User extends Authenticatable implements MustVerifyEmail
// class User extends Authenticatable implements FilamentUser
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $connection = 'mysql2';
    protected $table = 'systemusers';
    protected $primaryKey = 'recid';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guard_name = 'web';
    protected $guarded = ['recid'];


    protected $hidden = [
        'UserPassword',
        'laravel_password',
        'remember_token',
    ];

    public function getNameAttribute(): string
    {
        return (string) ($this->FullName ?? $this->UserName ?? 'Unknown');
    }

    public function getAuthPasswordName(): string
    {
        return 'UserPassword';
    }
    public function getAuthPassword()
    {
        return $this->UserPassword;
    }
    public function getAuthIdentifierName()
    {
        return 'recid'; // ← must match the primary key
    }

    public function getRememberTokenName()
    {
        return null; // systemusers has no remember_token column
    }

    // Disable password rehashing (no password column update)
    public function rehashPasswordIfRequired($password, array $options = [], bool $force = false): void
    {
        // Do nothing
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
        // throw new \Exception('Not implemented');
    }
    // public function canAccessPanel(Panel $panel): bool
    // {
    //     // For testing / quick fix — allow everyone (NOT recommended long-term!)
    //     return true;

    //     // Better: real authorization examples
    //     // return $this->hasRoleSafe('admin'); // if using spatie/laravel-permission
    //     // return str_ends_with($this->email, '@yourdomain.com') && $this->email_verified_at !== null;
    //     // return $this->is_admin === true;
    // }



    // protected $guarded = ['id'];
    // /**
    //  * The attributes that should be hidden for serialization.
    //  *
    //  * @var list<string>
    //  */
    // protected $hidden = [
    //     'password',
    //     'remember_token',
    // ];

    // /**
    //  * Get the attributes that should be cast.
    //  *
    //  * @return array<string, string>
    //  */
    // protected function casts(): array
    // {
    //     return [
    //         'email_verified_at' => 'datetime',
    //         'password' => 'hashed',
    //     ];
    // }

    public function office()
    {
        return $this->belongsTo(Office::class, 'department_code', 'department_code');
    }

    public function userEmployee()
    {
        return $this->belongsTo(UserEmployee::class, 'cats_number', 'empl_id');
    }

    public function requiredDocuments()
    {
        return $this->belongsToMany(\App\Models\RequiredDocument::class, 'document_user', 'user_id', 'document_id');
    }


     public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOffice(): bool
    {
        return $this->role === 'office';
    }

    public function isAdminOrSuperAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function notifications()
    {
        return $this->morphMany(
            DatabaseNotification::class,
            'notifiable'
        )->orderBy('created_at', 'desc');
    }


    public function readNotifications()
    {
        return $this->notifications()->whereNotNull('read_at');
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function hasRoleSafe(string ...$roles): bool
    {
        $roleIds = \DB::connection('mysql')
            ->table('model_has_roles')
            ->where('model_id', $this->recid)
            ->where('model_type', static::class)
            ->pluck('role_id')
            ->toArray();

    if (empty($roleIds)) return false;

    return \DB::connection('mysql')
        ->table('roles')
        ->whereIn('id', $roleIds)
        ->whereIn('name', $roles)
        ->exists();
    }

}
