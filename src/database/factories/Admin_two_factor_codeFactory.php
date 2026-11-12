<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Admin_two_factor_code;
use App\Support\AdminTwoFactorHash;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admin_two_factor_code>
 */
class Admin_two_factor_codeFactory extends Factory
{
    /**
     * 既定は、コード「123456」・失敗0回・10分後に期限切れ・送信は今
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Admin::factory(),
            'code' => AdminTwoFactorHash::code('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
        ];
    }
}
