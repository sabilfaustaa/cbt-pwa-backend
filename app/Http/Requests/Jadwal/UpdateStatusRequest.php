<?php

namespace App\Http\Requests\Jadwal;

use App\Enums\StatusJadwal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(StatusJadwal::cases(), 'value'))],
        ];
    }
}
