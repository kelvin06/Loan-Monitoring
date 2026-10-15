<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->createBootstrapAccount(
            env('BOOTSTRAP_ADMIN_EMAIL'), env('BOOTSTRAP_ADMIN_PASSWORD'),
            env('BOOTSTRAP_ADMIN_NAME', 'System Administrator'), 'admin'
        );
        $this->createBootstrapAccount(
            env('BOOTSTRAP_USER_EMAIL'), env('BOOTSTRAP_USER_PASSWORD'),
            env('BOOTSTRAP_USER_NAME', 'Loan Monitoring User'), 'user'
        );
    }

    private function createBootstrapAccount(?string $email, ?string $password, string $name, string $role): void
    {
        if (!$email || !$password) return;

        User::firstOrCreate(['email' => $email], [
            'name' => $name, 'password' => Hash::make($password),
            'role' => $role, 'is_active' => true,
        ]);
    }
}
