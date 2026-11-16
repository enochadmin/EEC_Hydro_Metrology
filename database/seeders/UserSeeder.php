<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demonstration accounts: one per role, used to verify that each role sees and
 * reaches exactly what it should. Refuses to run outside `local` so it can
 * never provision a production login.
 *
 * Password for every account: Password@123
 */
class UserSeeder extends Seeder
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const ACCOUNTS = [
        'Super Admin' => ['System Administrator', 'admin@eec-bhdms.local'],
        'System Administrator' => ['System Administrator (Ops)', 'sysadmin@eec-bhdms.local'],
        'Basin Manager' => ['Basin Manager', 'basin.manager@eec-bhdms.local'],
        'GIS Officer' => ['GIS Officer', 'gis.officer@eec-bhdms.local'],
        'Data Manager' => ['Data Manager', 'data.manager@eec-bhdms.local'],
        'Data Analyst' => ['Data Analyst', 'data.analyst@eec-bhdms.local'],
        'Viewer' => ['Read-only Viewer', 'viewer@eec-bhdms.local'],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('UserSeeder is limited to the local environment.');

            return;
        }

        foreach (self::ACCOUNTS as $role => [$name, $email]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'Password@123',
                    'is_active' => true,
                    'deactivated_at' => null,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);
        }

        $this->command?->info('Demo accounts ready for: '.implode(', ', array_keys(self::ACCOUNTS)));
    }
}
