<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'user';
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($this->user())],
            'current_password' => [
                Rule::requiredIf($this->filled('password')),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->filled('password') && ! Hash::check((string) $value, $this->user()->password)) {
                        $fail('The current password is incorrect.');
                    }
                },
            ],
            'password' => ['sometimes', 'required', 'string', 'min:12', 'confirmed'],
        ];
    }
}
