<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\Office;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AuthEventListener
{
    public function __construct(protected Request $request) {}

    /**
     * Handle the Login event.
     */
    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        // Skip remember-me token auto-logins
        if ($event->remember) return;

        // Dedupe: prevent double firing within 5 seconds
        $cacheKey = 'auth_login_' . ($user->recid ?? $user->id);
        if (Cache::has($cacheKey)) return;
        Cache::put($cacheKey, true, now()->addSeconds(5));

        $officeName = Office::where('department_code', $user->department_code)
            ->value('office') ?? $user->department_code;

        try {
            AuditLog::create([
                'event'                 => 'logged in',
                'user_id'               => $user->recid ?? $user->id,
                'acted_by'              => $user->FullName ?? $user->name ?? 'Unknown',
                'action_at'             => now(),
                'requirement_id'        => null,
                'requirement_name'      => null,
                'complying_office_id'   => null,
                'office_name'           => $officeName,
                'requiring_agency_name' => null,
                'remarks'               => 'Login from IP: ' . $this->request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log login event', [
                'user_id' => $user->recid ?? $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Logout event.
     */
    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if (!$user) return;

        // Dedupe: prevent double firing within 5 seconds
        $cacheKey = 'auth_logout_' . ($user->recid ?? $user->id);
        if (Cache::has($cacheKey)) return;
        Cache::put($cacheKey, true, now()->addSeconds(5));

        $officeName = Office::where('department_code', $user->department_code)
            ->value('office') ?? $user->department_code;

        try {
            AuditLog::create([
                'event'                 => 'logged out',
                'user_id'               => $user->recid ?? $user->id,
                'acted_by'              => $user->FullName ?? $user->name ?? 'Unknown',
                'action_at'             => now(),
                'requirement_id'        => null,
                'requirement_name'      => null,
                'complying_office_id'   => null,
                'office_name'           => $officeName,
                'requiring_agency_name' => null,
                'remarks'               => 'Logout from IP: ' . $this->request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log logout event', [
                'user_id' => $user->recid ?? $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}