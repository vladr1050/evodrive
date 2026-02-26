<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdminUser extends Command
{
    protected $signature = 'evodrive:make-admin
                            {--name= : Display name}
                            {--email= : Email (login)}
                            {--password= : Password (min 8 chars)}
                            {--role=admin : Role: admin or manager}';

    protected $description = 'Create a new admin/manager user for the Filament panel.';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Name');
        $email = $this->option('email') ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password (min 8 characters)');
        $role = $this->option('role') ?: 'admin';

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'role' => ['required', 'in:admin,manager'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        $this->info("User [{$email}] created. They can log in at /admin with role: {$role}.");
        return self::SUCCESS;
    }
}
