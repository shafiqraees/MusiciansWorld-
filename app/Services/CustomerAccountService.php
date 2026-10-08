<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    /**
     * Resolve the customer for a public form and create an account when needed.
     *
     * Existing guest accounts are linked by normalized email but are not
     * modified until the customer authenticates.
     *
     * @param  array{name: string, email: string, phone?: ?string, city?: ?string, state?: ?string, postal_code?: ?string}  $profile
     */
    public function resolve(?User $authenticatedUser, array $profile): User
    {
        $profile['email'] = Str::lower(trim($profile['email']));

        if ($authenticatedUser) {
            $this->updateAuthenticatedProfile($authenticatedUser, $profile);

            return $authenticatedUser->fresh();
        }

        $existingUser = User::query()->where('email', $profile['email'])->first();
        if ($existingUser) {
            if ($existingUser->isStaffAccount()) {
                throw ValidationException::withMessages([
                    'email' => 'This email belongs to a staff account. Please sign in before submitting this form.',
                ]);
            }

            $this->ensureCustomerSetup($existingUser);

            return $existingUser;
        }

        $temporaryPassword = Str::password(
            max(12, min(64, (int) config('customer-accounts.temporary_password_length', 16)))
        );

        $user = DB::transaction(function () use ($profile, $temporaryPassword): User {
            $user = User::firstOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['name'],
                    'phone' => $profile['phone'] ?? null,
                    'city' => $profile['city'] ?? null,
                    'state' => $profile['state'] ?? null,
                    'postal_code' => $profile['postal_code'] ?? null,
                    'password' => Hash::make($temporaryPassword),
                ],
            );

            $this->ensureCustomerSetup($user);

            return $user;
        });

        if ($user->wasRecentlyCreated) {
            app(NotificationService::class)->customerAccountAfterResponse($user, $temporaryPassword);
        }

        return $user;
    }

    /**
     * The authenticated customer may update contact fields from any public form.
     * Email is intentionally changed only from the account/security workflow.
     *
     * @param  array{name: string, email: string, phone?: ?string, city?: ?string, state?: ?string, postal_code?: ?string}  $profile
     */
    private function updateAuthenticatedProfile(User $user, array $profile): void
    {
        $updates = ['name' => $profile['name']];

        foreach (['phone', 'city', 'state', 'postal_code'] as $field) {
            if (array_key_exists($field, $profile) && filled($profile[$field])) {
                $updates[$field] = $profile[$field];
            }
        }

        $user->update($updates);
        if (! $user->isStaffAccount()) {
            $this->ensureCustomerSetup($user);
        }
    }

    private function ensureCustomerSetup(User $user): void
    {
        $customerRoleId = Role::query()->where('name', 'customer')->value('id');
        if ($customerRoleId) {
            $user->roles()->syncWithoutDetaching([$customerRoleId]);
        }

        $communityPlanId = MembershipPlan::query()->where('slug', 'community')->value('id');
        if ($communityPlanId) {
            $user->memberships()->firstOrCreate(
                ['membership_plan_id' => $communityPlanId],
                ['status' => 'active'],
            );
        }
    }
}
