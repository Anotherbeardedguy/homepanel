<?php

namespace App\Http\Controllers;

use App\Models\ChoreOccurrence;
use App\Services\ChoreCompletion;
use App\Services\ChoreOccurrenceService;
use App\Support\PanelActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChoreActionController extends Controller
{
    public function __construct(
        private readonly ChoreCompletion $completion,
        private readonly ChoreOccurrenceService $occurrences,
    ) {}

    public function complete(Request $request, ChoreOccurrence $occurrence): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);
        if (! $actor->canCompleteChores()) {
            abort(403, 'Tätä näyttöä ei ole sallittu kuittaamaan kotitöitä.');
        }

        $pin = $this->pin($request);
        $this->completion->complete($occurrence, $actor, $pin);
        $this->occurrences->sync($occurrence->chore);

        return $this->respond($request, 'Kotityö kuitattu.');
    }

    public function reopen(Request $request, ChoreOccurrence $occurrence): JsonResponse|RedirectResponse
    {
        $pin = $this->pin($request);
        $this->completion->reopen($occurrence, $this->actor($request), $pin);
        $this->occurrences->sync($occurrence->chore);

        return $this->respond($request, 'Kuittaus peruttu.');
    }

    private function pin(Request $request): string
    {
        $data = $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ], [
            'pin.required' => 'Anna nelinumeroinen PIN.',
            'pin.regex' => 'Anna nelinumeroinen PIN.',
        ]);

        return $data['pin'];
    }

    private function actor(Request $request): PanelActor
    {
        $actor = $request->attributes->get('panelActor');

        if ($actor instanceof PanelActor) {
            return $actor;
        }

        return new PanelActor(user: $request->user());
    }

    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => ['message' => $message],
                'error' => null,
            ]);
        }

        return back()->with('status', $message);
    }
}
