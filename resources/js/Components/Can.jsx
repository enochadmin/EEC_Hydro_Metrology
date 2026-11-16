import { useAuthorization } from '@/Hooks/useAuthorization';

/**
 * Renders children only when the current user holds the permission.
 *
 * <Can permission="create users">…</Can>
 * <Can any={['edit users', 'assign roles']}>…</Can>
 */
export default function Can({ permission, any, children, fallback = null }) {
    const { can, canAny } = useAuthorization();

    const allowed = permission ? can(permission) : canAny(any ?? []);

    return allowed ? children : fallback;
}
