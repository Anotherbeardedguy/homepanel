<?php

namespace App\Http\Controllers;

use App\Services\DisplayState;
use App\Support\PanelActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisplayController extends Controller
{
    public function __construct(private readonly DisplayState $state) {}

    public function show(Request $request): View
    {
        return view('display.show', [
            'state' => $this->state->build($this->actor($request)),
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->state->build($this->actor($request)),
            'error' => null,
        ]);
    }

    private function actor(Request $request): PanelActor
    {
        $actor = $request->attributes->get('panelActor');

        return $actor instanceof PanelActor ? $actor : new PanelActor(user: $request->user());
    }
}
