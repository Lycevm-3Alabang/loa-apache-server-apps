<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\IdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    private IdentityService $identity;
    private AuthorizationService $authorization;

    public function __construct(IdentityService $identity, AuthorizationService $authorization)
    {
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    #[OA\Get(
        path: "/api/v1/users",
        tags: ["Users"],
        summary: "List all users",
        responses: [
            new OA\Response(
                response: 200,
                description: "List of users",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/User")),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 403, description: "Forbidden - requires users.view permission", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
        ]
    )]
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

    #[OA\Get(
        path: "/api/v1/users/{id}",
        tags: ["Users"],
        summary: "Get user by ID",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "User details with groups and permissions",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "id", type: "string", format: "uuid"),
                        new OA\Property(property: "email", type: "string", format: "email"),
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "status", type: "string"),
                        new OA\Property(property: "groups", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "created_at", type: "string", format: "date-time"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 403, description: "Forbidden - requires users.view permission", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 404, description: "User not found", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
        ]
    )]
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

    #[OA\Patch(
        path: "/api/v1/users/{id}/status",
        tags: ["Users"],
        summary: "Enable or disable user account",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["active", "disabled"]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "User status updated", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string")])),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 403, description: "Forbidden - requires users.manage permission", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 404, description: "User not found", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
        ]
    )]
    public function updateStatus(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,disabled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->identity->setUserStatus($id, $request->input('status'));
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json(['message' => 'User status updated']);
    }
}
