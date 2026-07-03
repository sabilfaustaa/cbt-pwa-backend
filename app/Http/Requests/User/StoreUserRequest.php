<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi kondisional: petugas (admin/pengawas) vs peserta.
 * Role ID di-lookup dinamis — tidak hardcode 1/2/3 agar aman dengan Postgres sequences.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isPeserta = $this->isPeserta();

        return [
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'nama' => ['required', 'string', 'max:255'],
            'email' => $isPeserta
                ? ['prohibited']
                : ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $isPeserta
                ? ['prohibited']
                : ['required', 'string', 'min:6'],
            'nik' => $isPeserta
                ? ['required', 'string', 'size:16', 'unique:users,nik']
                : ['prohibited'],
            'no_agenda' => $isPeserta
                ? ['required', 'string', 'max:20']
                : ['prohibited'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.prohibited' => 'Email tidak boleh diisi untuk peserta.',
            'password.prohibited' => 'Password tidak boleh diisi untuk peserta.',
            'nik.prohibited' => 'NIK hanya untuk peserta.',
            'no_agenda.prohibited' => 'No. agenda hanya untuk peserta.',
            'nik.required' => 'NIK wajib diisi untuk peserta.',
            'nik.size' => 'NIK harus tepat 16 digit.',
            'no_agenda.required' => 'No. agenda wajib diisi untuk peserta.',
        ];
    }

    public function isPeserta(): bool
    {
        $pesertaRoleId = Role::where('nama_role', RoleName::Peserta)->value('id');

        return (int) $this->role_id === (int) $pesertaRoleId;
    }
}
