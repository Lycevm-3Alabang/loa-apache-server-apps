<?php

namespace App\Http\Controllers;

use App\Services\IdentityService;
use App\Services\JWTService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private IdentityService $identity;
    private JWTService $jwt;

    public function __construct(IdentityService $identity, JWTService $jwt)
    {
        $this->identity = $identity;
        $this->jwt = $jwt;
    }

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

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json($tokens);
    }

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

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->identity->requestPasswordReset($request->input('email'));

        return response()->json(['message' => 'If the email exists, a reset link has been sent']);
    }

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
