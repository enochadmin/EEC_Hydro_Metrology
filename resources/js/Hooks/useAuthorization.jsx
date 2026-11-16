import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

/**
 * Reads the authorization context shared by HandleInertiaRequests.
 *
 * The UI uses this to hide actions the user cannot perform. Every check is
 * repeated on the server (route middleware + policies); hiding a button is
 * never the only protection.
 */
export function useAuthorization() {
    const { auth } = usePage().props;
    const user = auth?.user ?? null;
    const roles = user?.roles ?? [];
    const permissions = user?.permissions ?? [];

    return useMemo(
        () => ({
            user,
            roles,
            permissions,
            isSuperAdmin: Boolean(user?.is_super_admin) || roles.includes('Super Admin'),
            can: (permission) => permissions.includes(permission),
            canAny: (list = []) => list.some((permission) => permissions.includes(permission)),
            hasRole: (role) => roles.includes(role),
        }),
        [user, roles, permissions],
    );
}
