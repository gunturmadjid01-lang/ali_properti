<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ChecksMarketingAccess
{
    protected function hasMarketingAccess(Request $request, array $defaultRoles, ?string $permission = null): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin'])) {
            return true;
        }

        if (! empty($defaultRoles) && $user->hasAnyRole($this->normalizedRoles($defaultRoles))) {
            return true;
        }

        return $permission ? (bool) $user->can($permission) : false;
    }

    protected function hasAnyMarketingPermission(Request $request, array $permissions): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin'])) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function abortUnlessMarketingAccess(Request $request, array $defaultRoles, ?string $permission = null, int $status = 403, string $message = 'Akses ditolak.'): void
    {
        abort_unless($this->hasMarketingAccess($request, $defaultRoles, $permission), $status, $message);
    }

    protected function normalizedRoles(array $roles): array
    {
        $expanded = [];

        foreach ($roles as $role) {
            $expanded[] = $role;

            if ($role === 'manager') {
                $expanded[] = 'manajer_pimpro';
            }

            if ($role === 'manajer_pimpro') {
                $expanded[] = 'manager';
            }
        }

        return array_values(array_unique($expanded));
    }
}
