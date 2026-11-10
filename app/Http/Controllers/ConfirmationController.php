<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\OccurrenceConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfirmationController extends Controller
{
    /**
     * Toggle social confirmation ("like") for an occurrence.
     */
    public function toggle(Request $request, Occurrence $occurrence): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($occurrence, $user) {
            $existing = OccurrenceConfirmation::where('occurrence_id', $occurrence->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $occurrence->decrement('confirmations_count');
                $hasConfirmed = false;
                $message = 'Confirmação removida / Confirmation removed.';
            } else {
                OccurrenceConfirmation::create([
                    'occurrence_id' => $occurrence->id,
                    'user_id' => $user->id,
                ]);
                $occurrence->increment('confirmations_count');
                $hasConfirmed = true;
                $message = 'Ocorrência confirmada com sucesso / Occurrence confirmed successfully!';
            }

            $occurrence->refresh();

            return response()->json([
                'success' => true,
                'message' => $message,
                'has_confirmed' => $hasConfirmed,
                'confirmations_count' => $occurrence->confirmations_count,
            ]);
        });
    }
}
