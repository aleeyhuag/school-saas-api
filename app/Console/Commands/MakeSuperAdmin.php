<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the platform owner's account — the only way to get the
 * super_admin role, since school registration only ever creates a
 * `proprietor`. Deliberately a CLI-only command, not an API endpoint —
 * you don't want ANY public way to create a super_admin account.
 *
 * Usage: php artisan make:super-admin
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'make:super-admin';

    protected $description = 'Create the platform owner (super_admin) account';

    public function handle(): int
    {
        $name = $this->ask('Your name');
        $email = $this->ask('Your email');
        $password = $this->secret('Choose a password (min 8 characters)');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return self::FAILURE;
        }

        $user = User::create([
            'school_id' => null, // super_admin is not tied to any single school
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'approved',
        ]);

        $user->assignRole('super_admin');

        $this->info("Super admin account created for {$email}. You can now log in via /api/auth/login like any other user.");

        return self::SUCCESS;
    }
}
