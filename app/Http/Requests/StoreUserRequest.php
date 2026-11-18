<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:Superadmin,Kasudin,Kasi,PJLP'],
            'seksi' => ['required', 'string'],
            'nik' => ['nullable', 'string', 'max:50'],
            'no_wa' => ['nullable', 'string', 'max:50'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'nrk' => ['nullable', 'string', 'max:50'],
            'pin' => ['nullable', 'string', 'max:50'],
            'posisi_pekerjaan' => ['nullable', 'string', 'max:255'],
            'jam_masuk' => ['nullable', 'string'],
            'jam_pulang' => ['nullable', 'string'],
            'status_user' => ['required', 'in:Aktif,Menunggu,Suspend'],
            'jenis_kelamin' => ['nullable', 'string', 'max:45'],
            'status_pernikahan' => ['nullable', 'string', 'max:45'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:100'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'mulai_bekerja' => ['nullable', 'date'],
            'nama_instansi' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap pengguna wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah terdaftar.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal terdiri dari 6 karakter.',
            'role.required' => 'Role pengguna wajib dipilih.',
            'role.in' => 'Role pengguna tidak valid.',
            'seksi.required' => 'Seksi / Penugasan wajib dipilih.',
            'status_user.required' => 'Status pengguna wajib dipilih.',
        ];
    }
}
