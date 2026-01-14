<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="User",
 *   description="User profile (self) and Admin user management"
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/users/me",
     *   tags={"User"},
     *   summary="Get current authenticated user profile",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me()
    {
        return response()->json(
            auth('web')->user()->load('roles:id,name')
        );
    }

    /**
     * @OA\Put(
     *   path="/api/users/me",
     *   tags={"User"},
     *   summary="Update current user profile",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", example="New Name"),
     *       @OA\Property(property="email", type="string", example="new@email.com"),
     *       @OA\Property(property="password", type="string", example="123456"),
     *       @OA\Property(property="password_confirmation", type="string", example="123456")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Profile updated"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateMe(Request $request)
    {
        $user = auth('web')->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'min:6', 'confirmed'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated',
            'user' => $user->fresh()->load('roles:id,name'),
        ]);
    }

    // =========================
    // ADMIN USER MANAGEMENT CRUD
    // =========================

    /**
     * @OA\Get(
     *   path="/api/admin/users",
     *   tags={"User"},
     *   summary="Admin: List users (paginated)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)")
     * )
     */
    public function index()
    {
        return response()->json(
            User::with('roles:id,name')
                ->latest()
                ->paginate(20)
        );
    }

    /**
     * @OA\Post(
     *   path="/api/admin/users",
     *   tags={"User"},
     *   summary="Admin: Create user and assign role(s)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password","roles"},
     *       @OA\Property(property="name", type="string", example="Staff User"),
     *       @OA\Property(property="email", type="string", example="staff@pos.com"),
     *       @OA\Property(property="password", type="string", example="123456"),
     *       @OA\Property(
     *         property="roles",
     *         type="array",
     *         @OA\Items(type="string", example="STAFF")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=201, description="User created"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(['ADMIN', 'STAFF', 'CUSTOMER'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles']);

        return response()->json([
            'message' => 'User created',
            'user' => $user->load('roles:id,name'),
        ], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/admin/users/{id}",
     *   tags={"User"},
     *   summary="Admin: Show user by ID",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer", example=2)
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $user = User::with('roles:id,name')->findOrFail($id);
        return response()->json($user);
    }

    /**
     * @OA\Put(
     *   path="/api/admin/users/{id}",
     *   tags={"User"},
     *   summary="Admin: Update user basic info (name/email/password optional)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer", example=2)
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", example="Updated User"),
     *       @OA\Property(property="email", type="string", example="updated@email.com"),
     *       @OA\Property(property="password", type="string", example="123456"),
     *       @OA\Property(property="password_confirmation", type="string", example="123456")
     *     )
     *   ),
     *   @OA\Response(response=200, description="User updated"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)"),
     *   @OA\Response(response=404, description="Not found"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'min:6', 'confirmed'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated',
            'user' => $user->fresh()->load('roles:id,name'),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/admin/users/{id}",
     *   tags={"User"},
     *   summary="Admin: Delete user",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer", example=2)
     *   ),
     *   @OA\Response(response=200, description="User deleted"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)"),
     *   @OA\Response(response=404, description="Not found"),
     *   @OA\Response(response=422, description="Cannot delete yourself")
     * )
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if (auth('web')->id() === $user->id) {
            return response()->json(['message' => "You can't delete yourself"], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }

    /**
     * @OA\Put(
     *   path="/api/admin/users/{id}/roles",
     *   tags={"User"},
     *   summary="Admin: Assign role(s) to user",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer", example=2)
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"roles"},
     *       @OA\Property(
     *         property="roles",
     *         type="array",
     *         @OA\Items(type="string", example="STAFF")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=200, description="Roles updated"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)"),
     *   @OA\Response(response=404, description="Not found"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function syncRoles(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(['ADMIN', 'STAFF', 'CUSTOMER'])],
        ]);

        $user->syncRoles($data['roles']);

        return response()->json([
            'message' => 'Roles updated',
            'user' => $user->fresh()->load('roles:id,name'),
        ]);
    }
}
