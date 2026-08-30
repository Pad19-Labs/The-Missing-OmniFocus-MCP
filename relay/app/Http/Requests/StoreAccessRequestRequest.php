<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function normalisedEmail(): string
    {
        return mb_strtolower(trim($this->string('email')->toString()));
    }
}
