<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'app:make-super-admin {email}';

    protected $description = 'Promote an existing user to super admin (full access, can create other admins and manage permissions)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No account found for {$this->argument('email')}. They need to register (or be added via Add User) first.");

            return self::FAILURE;
        }

        if ($user->role === 'super_admin') {
            $this->info("{$user->name} is already a super admin.");

            return self::SUCCESS;
        }

        $user->update(['role' => 'super_admin']);

        $this->info("{$user->name} ({$user->email}) is now a super admin.");

        return self::SUCCESS;
    }
}
