<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'orvionart:create-super-admin {--name= : Yönetici adı} {--email= : Yönetici e-posta adresi}';
    protected $description = 'Etkileşimli ve güvenli şekilde ilk Süper Admin hesabını oluşturur';

    public function handle(): int
    {
        if (User::where('role', 'super_admin')->exists()) {
            $this->error('En az bir Süper Admin hesabı zaten mevcut. Bu komut ikinci bir ilk yönetici oluşturmaz.');
            return self::FAILURE;
        }
        $name = trim((string) ($this->option('name') ?: $this->ask('Yönetici adı')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Yönetici e-posta adresi'))));
        if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            $this->error('Ad veya e-posta geçersiz.');
            return self::INVALID;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('Bu e-posta adresi zaten kayıtlı.');
            return self::FAILURE;
        }
        $password = (string) $this->secret('En az 14 karakterli güçlü bir yönetici parolası girin');
        $confirmation = (string) $this->secret('Parolayı tekrar girin');
        if (mb_strlen($password) < 14 || !hash_equals($password, $confirmation)) {
            $this->error('Parola en az 14 karakter olmalı ve tekrar girişiyle eşleşmeli.');
            return self::INVALID;
        }
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'super_admin', 'is_active' => true]);
        AuditLog::create(['actor_id' => $user->id, 'action' => 'setup.super_admin_created', 'subject_type' => 'user', 'subject_id' => $user->id, 'ip_address' => null, 'user_agent' => 'CLI setup', 'metadata' => ['method' => 'artisan'], 'created_at' => now()]);
        $this->info('Süper Admin hesabı oluşturuldu. Parola tekrar gösterilmeyecek.');
        $this->warn('Kurulumdan sonra CLI erişimini ve sunucu günlüklerini koruyun.');
        return self::SUCCESS;
    }
}
