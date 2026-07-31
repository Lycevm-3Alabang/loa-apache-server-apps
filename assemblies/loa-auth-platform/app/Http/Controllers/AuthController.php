<?php

namespace App\Http\Controllers;

use App\Services\IdentityService;
use App\Services\PasswordResetNotificationService;
use App\Services\JWTService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: "LOA Auth API",
    version: "1.0.0",
    description: "JWT authentication and user management for LOA platform",
    contact: new OA\Contact(name: "LOA Dev Team")
)]
#[OA\Tag(name: "Auth", description: "Authentication endpoints")]
#[OA\Tag(name: "Users", description: "User management (admin)")]
#[OA\Schema(
    schema: "TokenPair",
    properties: [
        new OA\Property(property: "access_token", type: "string", description: "JWT access token"),
        new OA\Property(property: "refresh_token", type: "string", description: "JWT refresh token"),
        new OA\Property(property: "token_type", type: "string", example: "Bearer"),
        new OA\Property(property: "expires_in", type: "integer", description: "Access token TTL in seconds"),
    ]
)]
#[OA\Schema(
    schema: "User",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "email", type: "string", format: "email"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "status", type: "string", enum: ["active", "locked", "disabled"]),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "Error",
    properties: [
        new OA\Property(property: "message", type: "string"),
    ]
)]
#[OA\Schema(
    schema: "ValidationErrors",
    properties: [
        new OA\Property(property: "errors", type: "object"),
    ]
)]
class AuthController extends Controller
{
    private IdentityService $identity;
    private JWTService $jwt;
    private PasswordResetNotificationService $passwordResetNotifications;

    public function __construct(
        IdentityService $identity,
        JWTService $jwt,
        PasswordResetNotificationService $passwordResetNotifications,
    )
    {
        $this->identity = $identity;
        $this->jwt = $jwt;
        $this->passwordResetNotifications = $passwordResetNotifications;
    }

    #[OA\Post(
        path: "/api/v1/auth/register",
        tags: ["Auth"],
        summary: "Register new user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password", "name"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", minLength: 8, example: "Password123"),
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "User created successfully", content: new OA\JsonContent(ref: "#/components/schemas/User")),
            new OA\Response(response: 409, description: "Email already registered", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
        ]
    )]
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/'],
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->identity->register(
                $request->input('email'),
                $request->input('password'),
                $request->input('name')
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ], 201);
    }

    #[OA\Post(
        path: "/api/v1/auth/login",
        tags: ["Auth"],
        summary: "Authenticate user and return tokens",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "string", format: "password"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Login successful", content: new OA\JsonContent(ref: "#/components/schemas/TokenPair")),
            new OA\Response(response: 401, description: "Invalid credentials", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 403, description: "Account is disabled", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
            new OA\Response(response: 423, description: "Account is locked", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
        ]
    )]
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $tokens = $this->identity->login(
                $request->input('email'),
                $request->input('password'),
                $request->ip()
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Account is locked') {
                return response()->json(['message' => 'Account is locked'], 423);
            }

            if ($e->getMessage() === 'Account is disabled') {
                return response()->json(['message' => 'Account is disabled'], 403);
            }

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json($tokens);
    }

    #[OA\Post(
        path: "/api/v1/auth/refresh",
        tags: ["Auth"],
        summary: "Rotate refresh token and get new token pair",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["refresh_token"],
                properties: [
                    new OA\Property(property: "refresh_token", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Token pair refreshed", content: new OA\JsonContent(ref: "#/components/schemas/TokenPair")),
            new OA\Response(response: 401, description: "Invalid refresh token", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
        ]
    )]
    public function refresh(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $tokens = $this->identity->refresh($request->input('refresh_token'));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        return response()->json($tokens);
    }

    #[OA\Post(
        path: "/api/v1/auth/logout",
        tags: ["Auth"],
        summary: "Revoke refresh token",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["refresh_token"],
                properties: [
                    new OA\Property(property: "refresh_token", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: "Logged out successfully"),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
        ]
    )]
    public function logout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->identity->logout($request->input('refresh_token'));

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: "/api/v1/auth/me",
        tags: ["Auth"],
        summary: "Get current user profile",
        responses: [
            new OA\Response(
                response: 200,
                description: "User profile with groups and permissions",
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
        ]
    )]
    public function me(Request $request)
    {
        $user = $request->user();
        $claims = $request->attributes->get('jwt_claims', []);

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'status' => $user->status,
            'groups' => $claims['groups'] ?? [],
            'permissions' => $claims['permissions'] ?? [],
            'created_at' => $user->created_at,
        ]);
    }

    #[OA\Put(
        path: "/api/v1/auth/password",
        tags: ["Auth"],
        summary: "Change password",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["old_password", "new_password"],
                properties: [
                    new OA\Property(property: "old_password", type: "string", format: "password"),
                    new OA\Property(property: "new_password", type: "string", format: "password", minLength: 8),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password updated", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string", example: "Password updated")])),
            new OA\Response(response: 400, description: "Current password incorrect", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
        ]
    )]
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->identity->updatePassword(
                $request->user()->id,
                $request->input('old_password'),
                $request->input('new_password')
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Password updated']);
    }

    #[OA\Post(
        path: "/api/v1/auth/password/forgot",
        tags: ["Auth"],
        summary: "Request password reset link",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Reset link sent (if email exists)", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string")])),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
        ]
    )]
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->passwordResetNotifications->sendForgotPasswordLink($request->input('email'));

        return response()->json(['message' => 'If the email exists, a reset link has been sent']);
    }

    #[OA\Post(
        path: "/api/v1/auth/password/change-request",
        tags: ["Auth"],
        summary: "Send a password change link to the authenticated user",
        responses: [
            new OA\Response(response: 204, description: "Change password link sent"),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
        ]
    )]
    public function changePasswordRequest(Request $request)
    {
        $this->passwordResetNotifications->sendChangePasswordLink($request->user());

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/api/v1/auth/password/reset",
        tags: ["Auth"],
        summary: "Reset password with token",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["token", "password"],
                properties: [
                    new OA\Property(property: "token", type: "string"),
                    new OA\Property(property: "password", type: "string", format: "password", minLength: 8),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password reset successfully", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string")])),
            new OA\Response(response: 400, description: "Invalid or expired token", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
        ]
    )]
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->identity->resetPassword(
                $request->input('token'),
                $request->input('password')
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Password reset successfully']);
    }

    #[OA\Get(
        path: "/api/v1/auth/verify",
        tags: ["Auth"],
        summary: "Validate access token",
        responses: [
            new OA\Response(
                response: 200,
                description: "Token is valid",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "valid", type: "boolean", example: true),
                        new OA\Property(property: "sub", type: "string", format: "uuid"),
                        new OA\Property(property: "email", type: "string", format: "email"),
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "groups", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string")),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Invalid or missing token",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "valid", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string"),
                    ]
                )
            ),
        ]
    )]
    public function verify(Request $request)
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json(['message' => 'Missing bearer token'], 401);
        }

        $claims = $this->jwt->validate($token);

        if (!$claims || ($claims['type'] ?? '') !== 'access') {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired token'], 401);
        }

        return response()->json([
            'valid' => true,
            'sub' => $claims['sub'],
            'email' => $claims['email'] ?? null,
            'name' => $claims['name'] ?? null,
            'groups' => $claims['groups'] ?? [],
            'permissions' => $claims['permissions'] ?? [],
        ]);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (!$header || !preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
