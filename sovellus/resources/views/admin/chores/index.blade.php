@extends('layouts.admin')
@section('title', 'Kotityöt')

@section('content')
    <div class="row-head">
        <h1>Kotityöt</h1>
        @if (auth()->user()->isAdmin())
            <a class="button" href="{{ route('admin.chores.create') }}">Lisää kotityö</a>
        @endif
    </div>
    @if ($chores->isEmpty())
        <p class="empty">Ei kotitöitä.</p>
    @else
        <ul class="cards">
            @foreach ($chores as $chore)
                <li>
                    <div>
                        <div class="title-row">
                            <button type="button" class="chore-title" data-chore-title="{{ $chore->title }}" data-chore-meta="{{ $chore->assigneeLabel() }}" data-chore-body="{{ $chore->description }}">{{ $chore->title }}</button>
                            @if (auth()->user()->isAdmin() && $chore->active)
                                <div class="actions">
                                    <a href="{{ route('admin.chores.edit', $chore) }}">Muokkaa</a>
                                    <form method="post" action="{{ route('admin.chores.archive', $chore) }}">
                                        @csrf
                                        <button type="submit">Arkistoi</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        <p class="muted">
                            {{ $chore->assigneeLabel() }}
                            @if ($chore->reward_eur)
                                · {{ str_replace('.', ',', $chore->reward_eur) }} €
                            @endif
                            · {{ $chore->recurrence->label() }}
                            · {{ $chore->active ? 'Aktiivinen' : 'Arkistoitu' }}
                        </p>
                        @foreach ($chore->occurrences as $occurrence)
                            <form method="post" action="{{ route('admin.chores.complete', $occurrence) }}" @class(['inline', 'late' => $occurrence->due_date->toDateString() < \App\Support\LocalClock::today()]) data-ask-pin="1">
                                @csrf
                                <span>@if ($occurrence->assigneeMember?->color)<span class="swatch" style="background: {{ $occurrence->assigneeMember->color }}"></span>@endif{{ $occurrence->assignee_member_id ? ($occurrence->assigneeMember->display_name ?? 'Kuka ehtii') : 'Kuka ehtii' }}</span>
                                <span>{{ $occurrence->due_date->format('d.m.Y') }}</span>
                                @if ($occurrence->due_date->toDateString() < \App\Support\LocalClock::today())
                                    <span>Myöhässä</span>
                                @endif
                                <button class="commit" type="submit">Valmis</button>
                            </form>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    <div id="chore-info" class="pin-overlay" hidden>
        <div class="pin-sheet" role="dialog" aria-modal="true" aria-labelledby="chore-info-title">
            <p id="chore-info-title"></p>
            <p id="chore-info-meta"></p>
            <p id="chore-info-body"></p>
            <div class="pin-actions">
                <button type="button" id="chore-info-close">Sulje</button>
            </div>
        </div>
    </div>
    <div id="pin-overlay" class="pin-overlay" hidden>
        <div class="pin-sheet" role="dialog" aria-modal="true" aria-labelledby="pin-prompt">
            <p id="pin-chore" hidden></p>
            <p id="pin-prompt">Anna PIN</p>
            <input id="pin-input" type="tel" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" aria-label="PIN">
            <p id="pin-error" class="pin-error" hidden></p>
            <div class="pin-actions">
                <button type="button" id="pin-cancel">Peruuta</button>
                <button type="button" id="pin-confirm">Valmis</button>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/admin-pin.js') }}?v={{ filemtime(public_path('js/admin-pin.js')) }}"></script>
@endsection
