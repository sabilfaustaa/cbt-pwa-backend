<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = (int) $this->route('id');

        return [
            'nama' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'string', 'min:6'],
            'nik' => ['sometimes', 'string', 'size:16', Rule::unique('users', 'nik')->ignore($userId)],
            'no_agenda' => ['sometimes', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nik.size' => 'NIK harus tepat 16 digit.',
        ];
    }
}
