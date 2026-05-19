<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserAccentRequest;
use App\Support\UserAccentColor;
use Illuminate\Http\JsonResponse;

class UserAccentController extends Controller
{
    public function update(UpdateUserAccentRequest $request): JsonResponse
    {
        $user = $request->user();
        $accent = UserAccentColor::resolve($request->validated('accent_color'));

        $user->accent_color = $accent === UserAccentColor::DEFAULT ? null : $accent;
        $user->save();

        return response()->json([
            'accent_color' => $accent,
        ]);
    }
}
