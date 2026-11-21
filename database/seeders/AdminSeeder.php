<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('cdrwa.admin_email');
        $password = (string) config('cdrwa.admin_password');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('CDRWA_ADMIN_EMAIL is missing or not a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('CDRWA_ADMIN_PASSWORD is missing or shorter than 8 characters.');
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = 'Administrator';
            // The model's "hashed" cast hashes this value on save.
            $user->password = $password;
        }

        // Role is set explicitly because it is not mass-assignable.
        $user->role = User::ROLE_ADMIN;

        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $user->save();

        $this->command?->info('Admin user ready: ' . $email);
    }
}