<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Notifications\AccountSetupNotification;

class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $oldEmail = $user->email;
        $data = $request->validated();

        if (strcasecmp($oldEmail, $data['email']) !== 0) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh()->only(['id', 'name', 'email', 'phone', 'status']),
        ]);
    }
}
