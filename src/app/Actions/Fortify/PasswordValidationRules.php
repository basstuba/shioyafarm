<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @param  bool  $required  false のときは任意（空のままなら検証しない。登録情報の変更で使う）
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', Password::default(), 'confirmed'];
    }
}
