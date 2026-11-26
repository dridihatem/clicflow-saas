<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->isPureClient()) {
            $this->request->remove('default_currency');

            return;
        }

        if ($this->input('default_currency') === '') {
            $this->merge(['default_currency' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'string', Rule::in(config('flowdesk.locales', ['fr', 'en', 'es', 'ar']))],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];

        if (! $this->isPureClient()) {
            $rules['default_currency'] = ['nullable', 'string', 'size:3', flowdesk_currency_rule()];
        }

        return $rules;
    }

    private function isPureClient(): bool
    {
        $user = $this->user();

        return $user
            && $user->hasRole('client')
            && ! $user->hasAnyRole(['company_admin', 'team_member', 'business_provider']);
    }
}
