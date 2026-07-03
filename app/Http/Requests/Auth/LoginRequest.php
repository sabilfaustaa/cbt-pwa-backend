<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Login request — support dua mode:
 *  - email + password  (admin / pengawas)
 *  - nik + no_agenda   (peserta)
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
            'password' => ['nullable', 'string'],
            'nik' => ['nullable', 'string', 'size:16'],
            'no_agenda' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function isPesertaLogin(): bool
    {
        return filled($this->nik) && filled($this->no_agenda);
    }

    public function isAdminLogin(): bool
    {
        return filled($this->email) && filled($this->password);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nik.size' => 'NIK harus tepat 16 digit.',
        ];
    }
}
