<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Models\LifeEvent;
use App\Support\Payload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LifeEventController extends Controller
{
    use InteractsWithCurrentUser;

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'kind' => ['required', Rule::in(LifeEvent::KINDS)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->currentUser($request)->lifeEvents()->create(Payload::assoc($validated));

        return back()->with('status', 'Annotation added.');
    }

    public function destroy(Request $request, LifeEvent $lifeEvent): RedirectResponse
    {
        abort_unless($lifeEvent->user_id === $this->currentUser($request)->id, 403);

        $lifeEvent->delete();

        return back()->with('status', 'Annotation removed.');
    }
}
