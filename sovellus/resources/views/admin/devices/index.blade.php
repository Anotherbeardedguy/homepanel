@extends('layouts.admin')
@section('title', 'Laitteet')

@section('content')
    <h1>Laitteet</h1>
    <p class="muted">Avaa näyttölaitteella sivu Näyttö ilman kirjautumista. Se näyttää koodin, jonka syötät tähän.</p>
    <form method="post" action="{{ route('admin.devices.approve') }}" class="stack-form">
        @csrf
        <label for="code">Koodi</label>
        <input id="code" name="code" value="{{ old('code') }}" autocomplete="off" required>
        <label for="name">Laitteen nimi</label>
        <input id="name" name="name" value="{{ old('name') }}" required maxlength="80">
        <label class="check">
            <input type="checkbox" name="can_complete" value="1" @checked(old('can_complete'))>
            Salli kotitöiden kuittaus tältä näytöltä
        </label>
        <button type="submit">Yhdistä</button>
    </form>

    <h2>Yhdistetyt näytöt</h2>
    @if ($devices->isEmpty())
        <p class="empty">Ei laitteita. Yhdistä näyttö yllä olevalla koodilla.</p>
    @else
        <ul class="cards">
            @foreach ($devices as $device)
                <li>
                    <div>
                        <strong>{{ $device->name }}</strong>
                        <p class="muted">
                            {{ $device->revoked_at ? 'Peruttu '.$device->revoked_at->timezone(config('homepanel.timezone'))->format('d.m.Y H.i') : 'Käytössä' }}
                            · kuittaus {{ $device->can_complete ? 'sallittu' : 'estetty' }}
                            @if ($device->last_seen_at)
                                · nähty {{ $device->last_seen_at->timezone(config('homepanel.timezone'))->format('d.m.Y H.i') }}
                            @endif
                        </p>
                    </div>
                    @if ($device->revoked_at === null)
                        <form method="post" action="{{ route('admin.devices.revoke', $device) }}">
                            @csrf
                            <button type="submit">Peru käyttöoikeus</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
