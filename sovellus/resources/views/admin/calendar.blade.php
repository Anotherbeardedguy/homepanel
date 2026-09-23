@extends('layouts.admin')
@section('title', 'Kalenteri')

@section('content')
    <h1>Kalenteri</h1>
    <p class="muted">Yksityinen kalenteri luetaan salaisella iCal-osoitteella. Julkinen kalenteri voidaan lisätä rajapinta-avaimella. Yksityisen tapahtuman otsikko on oletuksena ”Varattu”. Kuvauksia, osallistujia ja kokouslinkkejä ei tallenneta.</p>
    <p>Tila:
        @if (! $credentials['has_api_key'] && ! $credentials['has_ical'])
            Ei yhdistetty
        @elseif ($integration->status === 'ok')
            Yhdistetty
        @elseif ($integration->status === 'error')
            Virhe
        @elseif ($sources->contains(fn ($source) => $source->selected))
            Ei vielä synkronoitu
        @else
            Osoite tallennettu
        @endif
        @if ($integration->last_success_at)
            · viimeksi {{ $integration->last_success_at->timezone(config('homepanel.timezone'))->format('d.m.Y H.i') }}
        @endif
    </p>
    @if ($integration->last_error)
        <p class="errors">{{ $integration->last_error }}</p>
    @endif

    <form method="post" action="{{ route('admin.calendar.ical.store') }}" class="stack-form">
        @csrf
        <label for="ical_url">Salainen iCal-osoite</label>
        <input id="ical_url" name="ical_url" value="{{ old('ical_url') }}" maxlength="2000" autocomplete="off" spellcheck="false" placeholder="https://calendar.google.com/calendar/ical/…/basic.ics" required>
        <p class="muted">Google-kalenteri → kalenterin asetukset → Integroi kalenteri → Salainen osoite iCal-muodossa.</p>
        <button type="submit">Lisää yksityinen kalenteri</button>
    </form>

    <form method="post" action="{{ route('admin.calendar.update') }}" class="stack-form">
        @csrf
        @method('PUT')
        <label for="api_key">Rajapinta-avain</label>
        <input id="api_key" name="api_key" type="password" value="" maxlength="255" autocomplete="new-password" placeholder="{{ $credentials['has_api_key'] ? ($credentials['from_env'] ? 'Käytössä ympäristömuuttujasta, jätä tyhjäksi jos et vaihda' : 'Tallennettu, jätä tyhjäksi jos et vaihda') : '' }}">
        <button type="submit">Tallenna avain</button>
    </form>

    @if ($credentials['has_api_key'])
        <form method="post" action="{{ route('admin.calendar.sources.store') }}" class="stack-form">
            @csrf
            <label for="calendar_id">Julkisen kalenterin tunnus</label>
            <input id="calendar_id" name="calendar_id" value="{{ old('calendar_id') }}" maxlength="255" autocomplete="off" placeholder="perhe@group.calendar.google.com" required>
            <button type="submit">Lisää julkinen kalenteri</button>
        </form>
    @endif

    @if ($credentials['has_api_key'] || $credentials['has_ical'])
        <form method="post" action="{{ route('admin.calendar.sources') }}" class="stack-form">
            @csrf
            @method('PUT')
            <fieldset>
                <legend>Näytettävät kalenterit</legend>
                @forelse ($sources as $source)
                    <label class="check">
                        <input type="checkbox" name="calendars[]" value="{{ $source->id }}" @checked($source->selected)>
                        {{ $source->name }}
                    </label>
                @empty
                    <p>Kalenterilista on tyhjä.</p>
                @endforelse
            </fieldset>
            <label class="check">
                <input type="checkbox" name="show_private_titles" value="1" @checked(old('show_private_titles', $sources->contains(fn ($source) => $source->show_private_titles)))>
                Näytä yksityisten tapahtumien otsikot
            </label>
            <label class="check">
                <input type="checkbox" name="show_location" value="1" @checked(old('show_location', $sources->contains(fn ($source) => $source->show_location)))>
                Näytä paikka
            </label>
            <button type="submit">Tallenna valinnat</button>
        </form>

        <form method="post" action="{{ route('admin.calendar.refresh') }}">
            @csrf
            <button type="submit">Päivitä nyt</button>
        </form>
        <form method="post" action="{{ route('admin.calendar.disconnect') }}">
            @csrf
            @method('DELETE')
            <button type="submit">Irrota kalenteri</button>
        </form>
    @endif
@endsection
