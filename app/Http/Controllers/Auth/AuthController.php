<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="Auth",
 *   description="Authentication & Authorization APIs"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *   path="/api/auth/register",
     *   tags={"Auth"},
     *   summary="Register new user",
     *   description="Register a new user and return JWT token",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password","password_confirmation"},
     *       @OA\Property(property="name", type="string", example="Test User"),
     *       @OA\Property(property="email", type="string", example="test@pos.com"),
     *       @OA\Property(property="password", type="string", example="123456"),
     *       @OA\Property(property="password_confirmation", type="string", example="123456")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Registration successful"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Assign default role
        $user->assignRole('CUSTOMER');

        // Auto login
        $token = auth('web')->login($user);

        return response()->json([
            'message' => 'Registration successful',
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('web')->factory()->getTTL() * 60,
            'user' => $user,
        ], 201);
    }

    /**
     * @OA\Post(
     *   path="/api/auth/login",
     *   tags={"Auth"},
     *   summary="Login user",
     *   description="Login with email & password and receive JWT token",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","password"},
     *       @OA\Property(property="email", type="string", example="admin@pos.com"),
     *       @OA\Property(property="password", type="string", example="admin123")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Login successful"),
     *   @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! $token = auth('web')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('web')->factory()->getTTL() * 60,
            'user' => auth('web')->user(),
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/auth/me",
     *   tags={"Auth"},
     *   summary="Get current authenticated user",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="User profile"),
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me()
    {
        return response()->json(auth('web')->user());
    }

    /**
     * @OA\Post(
     *   path="/api/auth/logout",
     *   tags={"Auth"},
     *   summary="Logout current user",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Logged out"),
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout()
    {
        auth('web')->logout();
        return response()->json(['message' => 'Logged out']);
    }
}
