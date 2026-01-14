<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('admin123'),
            ]
        );

        // Assign ADMIN role
        if (! $admin->hasRole('ADMIN')) {
            $admin->assignRole('ADMIN');
        }
    }
}
