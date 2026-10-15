<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authenticate($request, 'user');
    }

    public function adminLogin(LoginRequest $request): JsonResponse
    {
        return $this->authenticate($request, 'admin');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['user' => (new UserResource($request->user()))->resolve($request)],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        } else {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['data' => null]);
    }

    private function authenticate(LoginRequest $request, string $role): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || $user->role !== $role || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        $userData = (new UserResource($user))->resolve($request);

        if ($request->expectsJson() && ! $request->hasSession()) {
            $token = $user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;

            return response()->json(['data' => ['user' => $userData, 'token' => $token]]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => ['user' => $userData]]);
    }
}
