<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthUserResource;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request): AuthUserResource
    {
        $request->authenticate();
        $request->session()->regenerate();

        return new AuthUserResource($request->user('web'));
    }
}
