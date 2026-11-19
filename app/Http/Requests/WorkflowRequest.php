<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['submit', 'review', 'approve', 'revision', 'reject', 'reopen', 'publish', 'unpublish', 'archive', 'restore'])],
            'notes' => [Rule::requiredIf(fn () => in_array($this->input('action'), ['revision', 'reject', 'reopen', 'unpublish', 'archive'])), 'nullable', 'string', 'max:4000'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
