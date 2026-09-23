<!DOCTYPE html>
<html lang="fi-FI">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kotinäyttö</title>
    <link rel="stylesheet" href="{{ asset('css/display.css') }}?v={{ filemtime(public_path('css/display.css')) }}">
</head>
<body>
    <main class="screen" data-can-complete="{{ $state['canComplete'] ? '1' : '0' }}">
        <header class="mast">
            <p id="date-label">{{ $state['dateLabel'] }}</p>
            <p id="clock" class="clock">{{ $state['clock'] }}</p>
        </header>

        <section class="top-band">
            @php($electricity = $state['widgets']['electricity'])
            @php($weather = $state['widgets']['weather'])
            @php($calendar = $state['widgets']['calendar'])
            <article class="electricity {{ $electricity['data']['level'] ?? 'unknown' }}" id="electricity">
                <div class="electricity-head">
                    <h1>Sähkö nyt</h1>
                    <p class="state-word"><span class="lamp" aria-hidden="true"></span> <span id="electricity-state">{{ $electricity['data']['stateWord'] ?? 'Ei hintatietoa' }}</span></p>
                </div>
                <p class="advice" id="electricity-message">{{ $electricity['message'] }}</p>
                <p class="price" id="electricity-price">{{ $electricity['data']['price'] ?? '—' }}</p>
                <p class="until" id="electricity-until">{{ $electricity['data']['until'] ?? '' }}</p>
                <p class="extra" id="electricity-extra">{{ $electricity['data']['extra'] ?? '' }}</p>
            </article>
            <article class="weather" id="weather">
                <h2 id="weather-place">{{ $weather['data']['place'] ?? 'Lahti' }}</h2>
                <p class="temperature" id="weather-temp">{{ $weather['data']['temperature'] ?? '—' }}</p>
                <p id="weather-kind">{{ $weather['data']['kindLabel'] ?? '' }}</p>
                <p id="weather-message">{{ $weather['data']['description'] ?? $weather['message'] }}</p>
                <p id="weather-feels">{{ $weather['data']['feelsLike'] ?? '' }}</p>
                <p id="weather-wind">{{ $weather['data']['wind'] ?? '' }}</p>
                <p id="weather-next">{{ $weather['data']['forecastLine'] ?? '' }}</p>
                <p class="attribution" id="weather-attribution">{{ $weather['data']['attribution'] ?? '' }}</p>
            </article>
        </section>

        <section class="bottom-band">
            <article>
                <h2><button type="button" class="panel-title" id="calendar-open">Tänään</button></h2>
                <div id="calendar">
                    @if (($calendar['data']['items'] ?? []) === [])
                        <p id="calendar-message">{{ $calendar['message'] }}</p>
                    @else
                        <ul class="rows">
                            @foreach ($calendar['data']['items'] as $item)
                                <li class="event">
                                    <strong>{{ $item['time'] }}</strong>
                                    <div>
                                        <p class="event-title">{{ $item['title'] }}</p>
                                        @if ($item['place'])
                                            <p>{{ $item['place'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if ($calendar['data']['extraCount'] > 0)
                            <p>Lisäksi {{ $calendar['data']['extraCount'] }} {{ $calendar['data']['extraCount'] === 1 ? 'tapahtuma' : 'tapahtumaa' }}</p>
                        @endif
                    @endif
                </div>
            </article>
            <article>
                <h2>Kotityöt</h2>
                <div id="chores">
                    @if ($state['widgets']['chores']['status'] === 'empty')
                        <p>{{ $state['widgets']['chores']['message'] }}</p>
                    @else
                        <ul class="rows">
                            @foreach ($state['widgets']['chores']['data']['items'] as $item)
                                <li @class(['todo', 'has-member' => $item['color'], 'late' => $item['overdue']]) @if ($item['color']) style="--member: {{ $item['color'] }}" @endif>
                                    <div>
                                        <button type="button" class="chore-title" data-chore-title="{{ $item['title'] }}" data-chore-meta="{{ $item['assignee'] }} · {{ $item['dueLabel'] }}@if ($item['time']) · {{ $item['time'] }}@endif" data-chore-body="{{ $item['description'] }}">{{ $item['title'] }}</button>
                                        <p>{{ $item['assignee'] }} · {{ $item['dueLabel'] }}@if ($item['time']) · {{ $item['time'] }}@endif</p>
                                    </div>
                                    @if ($state['canComplete'])
                                        <form method="post" action="{{ route('display.chores.complete', $item['id']) }}" data-complete="1">
                                            @csrf
                                            <button type="button">Valmis</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($state['widgets']['chores']['data']['extraCount'] > 0)
                            <p>Lisäksi {{ $state['widgets']['chores']['data']['extraCount'] }} {{ $state['widgets']['chores']['data']['extraCount'] === 1 ? 'kotityö' : 'kotityötä' }}</p>
                        @endif
                    @endif
                </div>
            </article>
        </section>
        <p id="connection" class="connection" hidden>Yhteys kotipalvelimeen katkesi. Yritetään uudelleen.</p>
    </main>
    <div id="calendar-more" class="pin-overlay" hidden>
        <div class="pin-sheet calendar-sheet" role="dialog" aria-modal="true" aria-labelledby="calendar-more-title">
            <p id="calendar-more-title">Seuraavat tapahtumat</p>
            <ul id="calendar-more-list" class="rows"></ul>
            <p id="calendar-more-empty" hidden>Ei tulevia tapahtumia.</p>
            <div class="pin-actions">
                <button type="button" id="calendar-more-close">Sulje</button>
            </div>
        </div>
    </div>
    <script type="application/json" id="calendar-upcoming">@json(data_get($calendar, 'data.upcoming', []))</script>
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
    <script src="{{ asset('js/display.js') }}?v={{ filemtime(public_path('js/display.js')) }}"></script>
</body>
</html>
