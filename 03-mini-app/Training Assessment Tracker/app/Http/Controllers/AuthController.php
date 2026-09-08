<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a team member.
     *
     * The role is NOT accepted from the request. Day 8's version took an
     * optional `role` field, which let anyone self-register as the privileged
     * role — the whole authorisation model would have been one payload field
     * away from being bypassed. Administrators are seeded, never registered.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Member,
        ]);

        return response()->json([
            'data' => $this->userPayload($user),
            'token' => $user->createToken('api-token')->plainTextToken,
        ], 201);
    }

    /**
     * Issue a token.
     *
     * The failure message is identical whether the email is unknown or the
     * password is wrong, so the endpoint cannot be used to enumerate accounts.
     * Rate limited to 5 attempts per minute in routes/api.php.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw new InvalidCredentialsException;
        }

        return response()->json([
            'data' => $this->userPayload($user),
            'token' => $user->createToken('api-token')->plainTextToken,
        ]);
    }

    /**
     * Revoke only the token that made this request.
     *
     * Day 8 left open why Sanctum keeps earlier tokens alive on re-login. The
     * decision taken here, deliberately: one device signing out must not sign
     * the user out everywhere, so logout deletes the current token and nothing
     * else.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    /** Who am I, what may I do, and do I hold a plan of my own. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('developmentPlan');

        return response()->json([
            'data' => $this->userPayload($user) + [
                'development_plan_id' => $user->developmentPlan?->id,
                'development_plan_status' => $user->developmentPlan?->status->value,
            ],
        ]);
    }

    /**
     * The single shape every auth response uses. The token is returned once, at
     * issue, and never appears in this payload or in any error response.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}
