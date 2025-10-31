<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StarterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $org = Organization::firstOrCreate([
            'slug' => 'organization-1',
        ], [
            'name' => 'Organization 1',
            'settings' => json_encode([]),
            'billing_status' => 'active',
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'plan' => 'free',
        ]);

        $user = User::firstOrCreate([
            'email' => 'admin@' . strtolower(env('APP_NAME')) . '.com',
        ], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'organization_id' => $org->id,
        ]);

        // Assign admin role to the first user if they don't have any roles
        if (!$user->hasAnyRole()) {
            $user->assignRole('admin');
        }
    }
}
