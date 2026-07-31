<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\IdentityService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private IdentityService $identity;
    private AuthorizationService $authorization;

    public function __construct(IdentityService $identity, AuthorizationService $authorization)
    {
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function index(Request $request)
    {
        $users = User::orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'status' => $user->status,
                'created_at' => $user->created_at,
            ]);

        return response()->json(['data' => $users]);
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = $this->identity->getUser($id);
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'status' => $user->status,
            'groups' => $this->authorization->getGroups($user->id),
            'permissions' => $this->authorization->getPermissions($user->id),
            'created_at' => $user->created_at,
        ]);
    }
}
