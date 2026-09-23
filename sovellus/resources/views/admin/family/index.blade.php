@extends('layouts.admin')
@section('title', 'Perhe')

@section('content')
    <div class="row-head">
        <h1>Perhe</h1>
        <a class="button" href="{{ route('admin.family.create') }}">Lisää henkilö</a>
    </div>
    @if ($members->isEmpty())
        <p class="empty">Ei perheenjäseniä. Lisää nimet, jotka näkyvät kotitöissä.</p>
    @else
        <ul class="cards">
            @foreach ($members as $member)
                <li>
                    <span class="swatch" style="background: {{ $member->color }}"></span>
                    <div>
                        <strong>{{ $member->display_name }}</strong>
                        <p class="muted">
                            {{ $member->active ? 'Aktiivinen' : 'Piilotettu' }}
                            · {{ $member->hasPin() ? 'PIN asetettu' : 'PIN puuttuu' }}
                            @if ($member->user)
                                · tunnus {{ $member->user->username }}
                            @else
                                · ei kirjautumista
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('admin.family.edit', $member) }}">Muokkaa</a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
