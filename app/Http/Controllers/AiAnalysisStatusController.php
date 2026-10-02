<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAnalysisStatusController extends Controller
{
    public function __invoke(Request $request, string $analysis): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);
        $model = $user->aiAnalyses()->findOrFail($analysis);

        return response()->json(['status' => $model->status->value])
            ->header('Cache-Control', 'no-store');
    }
}
