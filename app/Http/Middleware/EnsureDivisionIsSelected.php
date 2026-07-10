<?php

namespace App\Http\Middleware;

use App\Models\Pis\Division;
use Closure;
use Illuminate\Http\Request;

class EnsureDivisionIsSelected
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (! $user) {
            return $next($request);
        }

        // Avoid redirect loop on the selection page itself
        if ($request->routeIs('filament.admin.pages.select-division')) {
            return $next($request);
        }

        // Does this user's department even have divisions to choose from?
        $hasDivisions = Division::where('department_code', $user->department_code)->exists();

        if (! $hasDivisions) {
            return $next($request); // office has no divisions, nothing to select
        }

        // Has the user already picked one?
        $alreadySelected = ! empty($user->divisionCodes());

        if (! $alreadySelected) {
            return redirect()->route('filament.admin.pages.select-division');
        }

        return $next($request);
    }
}