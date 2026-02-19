<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

// class User extends Authenticatable implements MustVerifyEmail
// class User extends Authenticatable implements FilamentUser
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

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
    //     // return $this->hasRole('admin'); // if using spatie/laravel-permission
    //     // return str_ends_with($this->email, '@yourdomain.com') && $this->email_verified_at !== null;
    //     // return $this->is_admin === true;
    // }

    protected $guarded = ['id'];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

}
