import {
    BarChart3,
    Database,
    FileSpreadsheet,
    GitBranch,
    KeyRound,
    LayoutDashboard,
    LockKeyhole,
    Map,
    RadioTower,
    ScrollText,
    Settings,
    ShieldCheck,
    Users,
    Waves,
} from 'lucide-react';

/**
 * Sidebar information architecture.
 *
 * `permission` gates visibility for the current user; `available` marks items
 * whose page exists. Planned items stay visible (locked) for users who will
 * eventually hold the permission, and are hidden from everyone else.
 */
export const navigationGroups = [
    {
        label: 'Overview',
        items: [
            {
                key: 'dashboard',
                label: 'Dashboard',
                href: '/dashboard',
                icon: LayoutDashboard,
                permission: 'view dashboard',
                available: true,
            },
        ],
    },
    {
        label: 'Basin registry',
        items: [
            { key: 'basins', label: 'Basins', href: '/basins', icon: Waves, permission: 'view basins', available: true },
            {
                key: 'sub-basins',
                label: 'Sub-basins',
                href: '/sub-basins',
                icon: GitBranch,
                permission: 'view sub-basins',
                available: true,
            },
            { key: 'gis', label: 'GIS map', href: '/gis', icon: Map, permission: 'view map', available: true },
        ],
    },
    {
        label: 'Hydrological data',
        items: [
            {
                key: 'stations',
                label: 'Monitoring stations',
                href: '/stations',
                icon: RadioTower,
                permission: 'view stations',
                phase: 7,
            },
            {
                key: 'imports',
                label: 'Excel import',
                href: '/excel',
                icon: FileSpreadsheet,
                permission: 'view data',
                available: true,
            },
            {
                key: 'records',
                label: 'Hydrological records',
                href: '/records',
                icon: Database,
                permission: 'view data',
                phase: 9,
            },
            {
                key: 'quality',
                label: 'Quality control',
                href: '/quality-control',
                icon: ShieldCheck,
                permission: 'approve data',
                phase: 9,
            },
        ],
    },
    {
        label: 'Insights',
        items: [
            {
                key: 'reports',
                label: 'Reports & analytics',
                href: '/reports',
                icon: BarChart3,
                permission: 'view reports',
                available: true,
            },
        ],
    },
    {
        label: 'Administration',
        items: [
            { key: 'users', label: 'Users', href: '/users', icon: Users, permission: 'view users', available: true },
            {
                key: 'roles',
                label: 'Roles & permissions',
                href: '/access-control/roles',
                icon: KeyRound,
                permission: 'manage permissions',
                available: true,
            },
            {
                key: 'activity',
                label: 'Activity logs',
                href: '/activity-logs',
                icon: ScrollText,
                permission: 'view activity logs',
                available: true,
            },
            {
                key: 'settings',
                label: 'System settings',
                href: '/settings/system',
                icon: Settings,
                permission: 'manage settings',
                available: true,
            },
        ],
    },
];

export const plannedIcon = LockKeyhole;

/**
 * Determine whether a nav item matches the current Inertia URL.
 */
export function isActivePath(url, href) {
    if (!url) {
        return false;
    }

    const path = url.split('?')[0];

    if (href === '/dashboard') {
        return path === '/dashboard';
    }

    return path === href || path.startsWith(`${href}/`);
}