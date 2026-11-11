<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Input;
use App\Support\Username;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => Username::normalize(Input::text($this->input('username')))]);
        }
        if ($this->has('website')) {
            $w = trim(Input::text($this->input('website')));
            if ($w !== '' && ! preg_match('#^https?://#i', $w)) {
                $w = 'https://'.$w;
            }
            $this->merge(['website' => $w]);
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
        $this->merge(['show_owner' => $this->boolean('show_owner', ! $this->has('_profile_form'))]);
    }

    public function messages(): array
    {
        return [
            'current_password.required' => __('Enter your current password to change your email address. If you signed up with Google and never set a password, use "Forgot your password" first.'),
            'current_password.current_password' => __('The password is not correct. If you signed up with Google and never set a password, use "Forgot your password" first.'),
        ];
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'bail',
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            // Moving the account's address needs the current password; the Google link does not vouch for the new address.
            'current_password' => [
                Rule::requiredIf(fn () => is_string($this->input('email')) && mb_strtolower($this->input('email')) !== mb_strtolower((string) $user->email)),
                'nullable', 'string', 'current_password',
            ],
            'username' => ['sometimes', 'required', 'string', 'max:30', function (string $attr, mixed $value, \Closure $fail) use ($user) {
                if ($why = Username::problem((string) $value, $user)) {
                    $fail($why);
                }
            }],
            'bio' => ['sometimes', 'nullable', 'string', 'max:300'],
            'website' => ['sometimes', 'nullable', 'string', 'max:200', 'url:http,https'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'show_owner' => ['sometimes', 'boolean'],
        ];
    }
}
