<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeksiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperadmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'kode' => ['required', 'string', 'max:50', 'unique:seksi,kode'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama Seksi wajib diisi.',
            'kode.required' => 'Kode Seksi wajib diisi.',
            'kode.unique' => 'Kode Seksi sudah digunakan.',
        ];
    }
}
