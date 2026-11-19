<?php

namespace App\Http\Requests;

use App\Support\ReferenceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-reference') ?? false;
    }

    public function rules(): array
    {
        $definition = ReferenceCatalog::get($this->route('entity'));
        $rules = ['lock_version' => [$this->isMethod('post') ? 'nullable' : 'required', 'integer', 'min:0']];
        foreach ($definition['fields'] as $name => $field) {
            $rules[$name] = explode('|', $field[2]);
            if (isset($field['unique'])) {
                $rules[$name][] = Rule::unique($field['unique'], $name)->ignore($this->route('id'));
            }
        }
        if ($this->route('entity') === 'penulis') {
            $rules['dosen_id'][] = Rule::requiredIf($this->input('jenis_penulis') === 'dosen');
            $rules['dosen_id'][] = Rule::unique('penulis', 'dosen_id')->ignore($this->route('id'));
            if ($this->input('jenis_penulis') !== 'dosen') {
                $rules['dosen_id'][] = 'prohibited';
            }
        }

        return $rules;
    }
}
