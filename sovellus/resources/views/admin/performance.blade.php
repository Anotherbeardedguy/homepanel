@extends('layouts.admin')
@section('title', 'Suoritus')

@section('content')
    <h1>Suoritus</h1>
    <p class="muted">Viikko on maanantaista sunnuntaihin. Avoin vuoro ei kerrytä korvausta. Myöhässä tehty työ kerryttää koko korvauksen. PIN-virheet eivät muuta summaa.</p>

    <ul class="cards">
        @foreach ($rows as $row)
            <li>
                <div>
                    <strong>{{ $row['name'] }}</strong>
                    <div class="stats">
                        <span>Avoinna nyt <b>{{ $row['open'] }}</b></span>
                        <span>Viikko <b>{{ $row['week']['done'] }}</b> ajoissa, <b>{{ $row['week']['late'] }}</b> myöhässä, <b>{{ $row['week']['earned'] }} €</b></span>
                        <span>Kuukausi <b>{{ $row['month']['done'] }}</b> ajoissa, <b>{{ $row['month']['late'] }}</b> myöhässä, <b>{{ $row['month']['earned'] }} €</b></span>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    <h2>Tehdyt</h2>
    <ul class="cards">
        @forelse ($history as $item)
            <li>
                <div>
                    <strong>{{ $item->chore->title }}</strong>
                    <p class="muted">
                        {{ $item->completedByMember->display_name ?? 'Tekijä puuttuu' }}
                        · erä {{ $item->due_date->format('d.m.Y') }}
                        · tehty {{ $item->completed_at->timezone(config('homepanel.timezone'))->format('d.m.Y H.i') }}
                        @if ($item->completed_at->timezone(config('homepanel.timezone'))->toDateString() > $item->due_date->toDateString())
                            · myöhässä
                        @endif
                        @if ($item->earned_eur)
                            · {{ str_replace('.', ',', $item->earned_eur) }} €
                        @endif
                    </p>
                    <form method="post" action="{{ route('admin.chores.reopen', $item) }}" class="inline">
                        @csrf
                        <input name="pin" type="tel" inputmode="numeric" maxlength="4" pattern="[0-9]*" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" required aria-label="Tekijän PIN">
                        <button type="submit">Peru</button>
                    </form>
                </div>
            </li>
        @empty
            <li>Ei kuitattuja kotitöitä.</li>
        @endforelse
    </ul>

    <h2>PIN-tapahtumat</h2>
    <ul class="cards">
        @forelse ($misconduct as $event)
            <li>
                <div>
                    <strong>{{ $event->kind === 'locked' ? 'Lukitus' : 'Väärä PIN' }}</strong>
                    <p class="muted">
                        {{ $event->member->display_name ?? 'Tekijä ei tiedossa' }}
                        · {{ $event->occurrence->chore->title ?? 'Kotityö' }}
                        · {{ $event->created_at->timezone(config('homepanel.timezone'))->format('d.m.Y H.i') }}
                    </p>
                </div>
            </li>
        @empty
            <li>Ei PIN-tapahtumia.</li>
        @endforelse
    </ul>
@endsection