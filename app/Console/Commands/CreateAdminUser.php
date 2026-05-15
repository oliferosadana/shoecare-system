<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin {email?} {--name=} {--phone=}';

    protected $description = 'Create or update a production admin user interactively.';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email admin');
        $name = $this->option('name') ?: $this->ask('Nama admin', 'Admin ZOLIX');
        $phone = $this->option('phone') ?: $this->ask('No. WhatsApp', null);
        $password = $this->secret('Password admin');

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'password' => $password,
        ], [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::min(10)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::updateOrCreate([
            'email' => $email,
        ], [
            'name' => $name,
            'role' => 'admin',
            'phone' => $phone,
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        $this->info("Admin user ready: {$email}");

        return self::SUCCESS;
    }
}
