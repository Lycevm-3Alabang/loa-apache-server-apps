<?php

namespace App\Services;

use App\Models\Activation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ActivationService
{
    public function createActivation(User $user): string
    {
        // Delete any existing activation for this user
        Activation::where('user_id', $user->id)->delete();
        
        // Generate token
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        
        // Create activation record
        $activation = Activation::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'expires_at' => now()->addHours(24),
        ]);
        
        return $rawToken;
    }
    
    public function activate(string $rawToken): User
    {
        $hashedToken = hash('sha256', $rawToken);
        
        // Find activation record
        $activation = Activation::where('token', $hashedToken)
            ->whereNull('activated_at')
            ->first();
            
        if (!$activation || $activation->isExpired()) {
            throw new \Exception('Invalid or expired token');
        }
        
        $user = User::find($activation->user_id);
        if (!$user) {
            throw new \Exception('User not found');
        }
        
        // Activate user account
        $user->update(['status' => 'active']);
        
        // Mark activation as used
        $activation->update([
            'activated_at' => now()
        ]);
        
        return $user;
    }
    
    public function resendActivation(User $user): string
    {
        // Delete any existing activation for this user
        Activation::where('user_id', $user->id)->delete();
        
        // Generate token
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        
        // Create new activation record
        $activation = Activation::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'expires_at' => now()->addHours(24),
        ]);
        
        return $rawToken;
    }
}