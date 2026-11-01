<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UpdateAdminPassword extends Command
{
    protected $signature = 'mwcn:update-admin-password';

    protected $description = 'Update the password for an admin portal account';

    public function handle(): int
    {
        $username = Str::lower(trim((string) $this->ask('Username')));
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['username' => $username, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'username' => ['required', 'email'],
                'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            ],
            [
                'username.email' => 'Enter the email address used on the admin login page.',
            ],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$username])->first();

        if ($user === null || ! $user->isStaffAccount()) {
            $this->error('No admin portal account uses that username.');

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Password updated for {$user->email}.");

        return self::SUCCESS;
    }
}
