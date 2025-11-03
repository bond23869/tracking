<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\IngestionToken;
use App\Models\Organization;
use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateIngestionToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracking:create-token 
                            {--website-id= : Website ID to create token for}
                            {--name= : Token name}
                            {--create-test-data : Create test organization, account, and website if needed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an ingestion token for API requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $websiteId = $this->option('website-id');
        $createTestData = $this->option('create-test-data');

        // Create test data if requested or no website exists
        if ($createTestData || (!$websiteId && Website::count() === 0)) {
            $this->info('Creating test data...');
            $website = $this->createTestWebsite();
            $this->info("Created website: {$website->name} (ID: {$website->id})");
        } else {
            $website = Website::find($websiteId);
            
            if (!$website) {
                $this->error("Website with ID {$websiteId} not found.");
                $this->info('Run with --create-test-data to create a test website.');
                return 1;
            }
        }

        $name = $this->option('name') ?: 'test-token-' . now()->format('Y-m-d-H-i-s');

        // Generate token
        $tokenPrefix = Str::random(12);
        $tokenSecret = Str::random(32);
        $fullToken = $tokenPrefix . '.' . $tokenSecret;
        $tokenHash = Hash::make($fullToken);

        $ingestionToken = IngestionToken::create([
            'website_id' => $website->id,
            'name' => $name,
            'token_prefix' => $tokenPrefix,
            'token_hash' => $tokenHash,
        ]);

        $this->info('Token created successfully!');
        $this->newLine();
        $this->info('Token Details:');
        $this->line("  ID: {$ingestionToken->id}");
        $this->line("  Name: {$ingestionToken->name}");
        $this->line("  Website: {$website->name} (ID: {$website->id})");
        $this->newLine();
        $this->warn('IMPORTANT: Save this token now. It cannot be retrieved later!');
        $this->newLine();
        $this->line("Full Token: <fg=green>{$fullToken}</>");
        $this->newLine();
        $this->info('Usage example:');
        $this->line('  curl -X POST ' . config('app.url') . '/api/tracking/events \\');
        $this->line('    -H "Authorization: Bearer ' . $fullToken . '" \\');
        $this->line('    -H "Content-Type: application/json" \\');
        $this->line('    -d \'{"event":"page_view","identity":{"type":"cookie","value":"test123"},"url":"https://example.com"}\'');

        return 0;
    }

    /**
     * Create test organization, account, and website.
     */
    protected function createTestWebsite(): Website
    {
        // Create or get test organization
        $organization = Organization::firstOrCreate(
            ['slug' => 'test-organization'],
            [
                'name' => 'Test Organization',
                'slug' => 'test-organization',
                'plan' => 'free',
            ]
        );

        // Create or get test account
        $account = Account::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => 'test-account',
            ],
            [
                'organization_id' => $organization->id,
                'name' => 'Test Account',
                'slug' => 'test-account',
            ]
        );

        // Create or get test website
        $website = Website::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'account_id' => $account->id,
                'name' => 'Test Website',
            ],
            [
                'organization_id' => $organization->id,
                'account_id' => $account->id,
                'name' => 'Test Website',
                'url' => 'https://example.com',
                'status' => 'active',
                'connection_status' => 'connected',
            ]
        );

        return $website;
    }
}
