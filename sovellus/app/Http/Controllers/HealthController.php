<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['status' => 'live'],
            'error' => null,
        ]);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $ready = Schema::hasTable('migrations') && Schema::hasTable('chores');
        } catch (Throwable) {
            $ready = false;
        }

        if (! $ready) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'not_ready', 'message' => 'Palvelu ei ole valmis.'],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => ['database' => 'ok'],
            'error' => null,
        ]);
    }
}
