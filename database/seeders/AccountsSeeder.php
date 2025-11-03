<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::first();

        $account = $organization->accounts()->updateOrCreate(
            ['slug' => 'account-1'],
            [
                'name' => 'Account 1',
                'monthly_orders' => 'less_than_500',
            ]
        );

        $website = $account->websites()->updateOrCreate(
            ['url' => 'https://maneks_wp.test/'],
            [
                'name' => 'Maneks WP Test',
                'organization_id' => $organization->id,
                'status' => 'active',
                'connection_status' => 'connected',
                'connection_error' => null,
            ]
        );
    }
}
