<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateAdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_the_admin_portal_password_for_that_username(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@musiciansworld.com',
            'password' => 'OldPassword1',
            'account_type' => User::TYPE_ADMIN,
        ]);

        $this->artisan('mwcn:update-admin-password')
            ->expectsQuestion('Username', 'Admin@MusiciansWorld.com')
            ->expectsQuestion('Password', 'NewPassword1')
            ->expectsQuestion('Confirm password', 'NewPassword1')
            ->expectsOutput('Password updated for admin@musiciansworld.com.')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('NewPassword1', $admin->fresh()->password));
    }

    public function test_rejects_a_customer_username_and_leaves_the_password_unchanged(): void
    {
        $customer = User::factory()->create([
            'email' => 'customer@musiciansworld.com',
            'password' => 'OldPassword1',
            'account_type' => User::TYPE_CUSTOMER,
        ]);

        $this->artisan('mwcn:update-admin-password')
            ->expectsQuestion('Username', 'customer@musiciansworld.com')
            ->expectsQuestion('Password', 'NewPassword1')
            ->expectsQuestion('Confirm password', 'NewPassword1')
            ->expectsOutput('No admin portal account uses that username.')
            ->assertFailed();

        $this->assertTrue(Hash::check('OldPassword1', $customer->fresh()->password));
    }

    public function test_rejects_a_password_confirmation_that_does_not_match(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@musiciansworld.com',
            'password' => 'OldPassword1',
            'account_type' => User::TYPE_ADMIN,
        ]);

        $this->artisan('mwcn:update-admin-password')
            ->expectsQuestion('Username', 'admin@musiciansworld.com')
            ->expectsQuestion('Password', 'NewPassword1')
            ->expectsQuestion('Confirm password', 'DifferentPass1')
            ->assertFailed();

        $this->assertTrue(Hash::check('OldPassword1', $admin->fresh()->password));
    }
}
