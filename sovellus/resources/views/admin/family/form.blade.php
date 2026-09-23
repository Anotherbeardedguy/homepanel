@extends('layouts.admin')
@section('title', $member->exists ? 'Muokkaa henkilöä' : 'Uusi henkilö')

@section('content')
    <h1>{{ $member->exists ? 'Muokkaa henkilöä' : 'Uusi henkilö' }}</h1>
    <form method="post" action="{{ $member->exists ? route('admin.family.update', $member) : route('admin.family.store') }}" class="stack-form">
        @csrf
        @if ($member->exists)
            @method('PUT')
        @endif
        <label for="display_name">Näyttönimi</label>
        <input id="display_name" name="display_name" value="{{ old('display_name', $member->display_name) }}" required maxlength="80">

        @php($selectedColor = strtoupper((string) old('color', $member->color ?: \App\Models\FamilyMember::COLORS[0])))
        @php($palette = \App\Models\FamilyMember::allowedColors($member->color))
        <fieldset class="color-picker">
            <legend id="color-legend">Tunnisteväri</legend>
            <p class="color-preview-row">
                <span id="color-preview" class="color-preview" style="background: {{ $selectedColor }}"></span>
                <span id="color-preview-name">{{ \App\Models\FamilyMember::COLOR_LABELS[$selectedColor] ?? 'Nykyinen väri' }}</span>
            </p>
            <div class="color-choices" role="radiogroup" aria-labelledby="color-legend">
                @foreach ($palette as $color)
                    @php($label = \App\Models\FamilyMember::COLOR_LABELS[$color] ?? 'Nykyinen väri')
                    <label class="color-choice">
                        <input type="radio" name="color" value="{{ $color }}" data-label="{{ $label }}" @checked($selectedColor === $color) @required($loop->first)>
                        <span class="swatch" style="background: {{ $color }}"></span>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <script>
            (function () {
                var preview = document.getElementById('color-preview');
                var name = document.getElementById('color-preview-name');
                document.querySelectorAll('input[name="color"]').forEach(function (input) {
                    input.addEventListener('change', function () {
                        preview.style.background = input.value;
                        name.textContent = input.getAttribute('data-label') || '';
                    });
                });
            }());
        </script>

        <label class="check">
            <input type="checkbox" name="active" value="1" @checked(old('active', $member->active))>
            Näkyy valinnoissa
        </label>

        <h2>PIN</h2>
        <p class="muted">Neljä numeroa. Vanhaa PIN-koodia ei voi näyttää. Jätä kenttä tyhjäksi, jos et vaihda sitä.</p>
        <label for="pin">PIN</label>
        <input id="pin" name="pin" type="tel" inputmode="numeric" maxlength="4" pattern="[0-9]*" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" @if (! $member->exists || ! $member->hasPin()) required @endif>

        <h2>Kirjautuminen</h2>
        <p class="muted">Vapaaehtoinen. Lapselle riittää näyttönimi ilman tunnusta.</p>
        <label for="username">Kirjautumistunnus</label>
        <input id="username" name="username" value="{{ old('username', $member->user->username ?? '') }}" autocomplete="off" maxlength="40">
        <label for="password">Salasana</label>
        <input id="password" name="password" type="password" autocomplete="new-password">
        <p class="muted">Jätä salasana tyhjäksi, jos et vaihda sitä.</p>

        <button type="submit">Tallenna</button>
    </form>

    @if ($member->exists)
        <h2>Poissaolot</h2>
        <p class="muted">Viikonloppu on lauantai ja sunnuntai. Joka toinen viikko alkaa aloituspäivän viikosta.</p>
        <ul class="cards">
            @forelse ($member->absences as $absence)
                <li>
                    <div>
                        <strong>{{ $absence->pattern->label() }}</strong>
                        <p class="muted">{{ $absence->starts_on->format('d.m.Y') }}@if ($absence->ends_on) – {{ $absence->ends_on->format('d.m.Y') }}@endif</p>
                    </div>
                    <form method="post" action="{{ route('admin.family.absences.destroy', [$member, $absence]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Poista</button>
                    </form>
                </li>
            @empty
                <li>Ei poissaoloja.</li>
            @endforelse
        </ul>
        <form method="post" action="{{ route('admin.family.absences.store', $member) }}" class="stack-form">
            @csrf
            <label for="pattern">Tapa</label>
            <select id="pattern" name="pattern">
                @foreach (\App\Enums\AbsencePattern::cases() as $pattern)
                    <option value="{{ $pattern->value }}">{{ $pattern->label() }}</option>
                @endforeach
            </select>
            <label for="starts_on">Alkaa</label>
            <input id="starts_on" name="starts_on" type="date" required>
            <label for="ends_on">Päättyy, vain aikavälillä</label>
            <input id="ends_on" name="ends_on" type="date">
            <button type="submit">Lisää poissaolo</button>
        </form>
    @endif
@endsection
