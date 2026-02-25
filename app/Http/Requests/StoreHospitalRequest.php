<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHospitalRequest extends FormRequest
{
    public function authorize()
    {
        $user = $this->user();
        return $user && $user->role === 'admin';
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'specialties' => ['nullable'],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('specialties') && is_string($this->specialties)) {
            $this->merge(['specialties' => array_values(array_filter(array_map('trim', explode(',', $this->specialties))))]);
        }
    }
}
