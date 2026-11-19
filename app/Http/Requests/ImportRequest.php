<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-reference') ?? false;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::in(['publikasi', 'buku', 'hki'])], 'file' => ['required', 'file', 'extensions:csv,xlsx', 'max:5120']];
    }
}
