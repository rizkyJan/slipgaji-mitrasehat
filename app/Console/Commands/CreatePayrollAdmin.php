<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreatePayrollAdmin extends Command
{
    protected $signature = 'payroll:make-admin';
    protected $description = 'Buat administrator payroll melalui terminal (tanpa registrasi publik)';

    public function handle(): int
    {
        $name = $this->ask('Nama administrator');
        $email = strtolower((string) $this->ask('Email administrator untuk login'));
        $password = $this->secret('Password baru (minimal 12 karakter)');
        $validation = Validator::make(compact('name', 'email', 'password'), [
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12',
        ]);
        if ($validation->fails()) {
            $this->error($validation->errors()->first());
            return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'admin', 'active' => true]);
        $this->info('Akun administrator berhasil dibuat.');
        return self::SUCCESS;
    }
}
