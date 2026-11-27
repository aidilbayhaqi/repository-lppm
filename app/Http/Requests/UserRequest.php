<?php

namespace App\Http\Requests;

use App\Services\RegistrationApprovalService;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'confirmed', ...PasswordPolicy::rules()],
            'lock_version' => [$this->isMethod('post') ? 'nullable' : 'required', 'integer', 'min:0'],
            'role' => ['required', Rule::in(['admin', ...RegistrationApprovalService::ASSIGNABLE_ROLES])],
            'is_active' => ['required', 'boolean'], 'penulis_id' => ['nullable', 'integer', 'exists:penulis,id'],
        ];
    }
}
