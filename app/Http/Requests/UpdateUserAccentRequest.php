<?php

namespace App\Http\Requests;

use App\Support\UserAccentColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accent_color' => ['required', 'string', Rule::in(UserAccentColor::ALLOWED)],
        ];
    }
}
