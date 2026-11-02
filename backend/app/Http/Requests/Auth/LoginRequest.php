<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function authenticate(): void
    {
        $key = hash('sha256', $this->string('email')->toString().'|'.$this->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Too many login attempts. Please try again shortly.',
            ], 429, ['Retry-After' => RateLimiter::availableIn($key)]));
        }

        if (! Auth::guard('web')->attempt($this->only('email', 'password'))) {
            RateLimiter::hit($key, 60);

            throw new HttpResponseException(response()->json([
                'message' => 'The provided credentials are invalid.',
            ], 401));
        }

        RateLimiter::clear($key);
    }
}
