<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the platform owner's account.
 *
 * Normal/local usage:
 *   php artisan make:super-admin
 *
 * One-time production bootstrap:
 *   php artisan make:super-admin --bootstrap
 *
 * The production bootstrap is intended to be called from docker/entrypoint.sh
 * using temporary environment variables. It is NOT exposed as an API endpoint.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'make:super-admin
                            {--bootstrap : Create the account non-interactively from SUPERADMIN_* environment variables}
                            {--name= : Super Admin name (bootstrap mode)}
                            {--email= : Super Admin email (bootstrap mode)}
                            {--password= : Super Admin password (bootstrap mode)}';

    protected $description = 'Create the platform owner (super_admin) account';

    public function handle(): int
    {
        $bootstrap = (bool) $this->option('bootstrap');

        if ($bootstrap) {
            return $this->handleBootstrap();
        }

        return $this->handleInteractive();
    }

    /**
     * Existing interactive/local workflow.
     */
    private function handleInteractive(): int
    {
        $name = trim($this->ask('Your name'));
        $email = strtolower(trim($this->ask('Your email')));
        $password = $this->secret('Choose a password (min 8 characters)');

        if ($name === '' || $email === '' || $password === '') {
            $this->error('Name, email and password are required.');
            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');
            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return self::FAILURE;
        }

        $user = $this->createSuperAdmin($name, $email, $password);

        $this->info("Super admin account created for {$user->email}. You can now log in via /api/auth/login like any other user.");

        return self::SUCCESS;
    }

    /**
     * One-time production bootstrap.
     *
     * The entrypoint supplies the values from:
     *   SUPERADMIN_NAME
     *   SUPERADMIN_EMAIL
     *   SUPERADMIN_PASSWORD
     *
     * This mode is deliberately idempotent:
     * - If the email does not exist, create the Super Admin.
     * - If the email already belongs to a Super Admin, do nothing and succeed.
     * - If the email belongs to another role, stop with an error rather than
     *   silently upgrading an existing account.
     */
    private function handleBootstrap(): int
    {
        $name = trim((string) ($this->option('name') ?: env('SUPERADMIN_NAME', '')));
        $email = strtolower(trim((string) ($this->option('email') ?: env('SUPERADMIN_EMAIL', ''))));
        $password = (string) ($this->option('password') ?: env('SUPERADMIN_PASSWORD', ''));

        if ($name === '' || $email === '' || $password === '') {
            $this->error('Production Super Admin bootstrap requires SUPERADMIN_NAME, SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD.');
            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('SUPERADMIN_EMAIL is not a valid email address.');
            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('SUPERADMIN_PASSWORD must be at least 8 characters.');
            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            if ($existing->hasRole('super_admin')) {
                $this->info("Super admin {$email} already exists. Nothing to create.");
                return self::SUCCESS;
            }

            $this->error("A non-super-admin user already exists with {$email}. No account was changed.");
            return self::FAILURE;
        }

        $user = $this->createSuperAdmin($name, $email, $password);

        $this->info("Production super admin account created for {$user->email}.");

        return self::SUCCESS;
    }

    private function createSuperAdmin(string $name, string $email, string $password): User
    {
        $user = User::create([
            'school_id' => null, // Super Admin is not tied to a single school.
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'approved',
        ]);

        $user->assignRole('super_admin');

        return $user;
    }
}