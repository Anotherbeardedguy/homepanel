@extends('layouts.admin')
@section('title', 'Sää')

@section('content')
    <h1>Sää</h1>
    <p class="muted">Lähde: Ilmatieteen laitos, avoin data, lisenssi CC BY 4.0. Avainta ei tarvita. Sateen todennäköisyyttä ei näytetä, jos aineisto ei anna sitä.</p>
    <p>Tila: {{ $integration->status === 'ok' ? 'Yhdistetty' : ($integration->status === 'error' ? 'Virhe' : 'Ei haettu') }}
        @if ($integration->last_success_at)
            · viimeksi {{ $integration->last_success_at->timezone(config('homepanel.timezone'))->format('d.m.Y H.i') }}
        @endif
    </p>
    @if ($integration->last_error)
        <p class="errors">{{ $integration->last_error }}</p>
    @endif
    @if ($snapshot)
        <p>Näytön paikka viime haulla: {{ $snapshot->place }}.</p>
    @endif

    <form method="post" action="{{ route('admin.weather.refresh') }}">
        @csrf
        <button type="submit">Päivitä nyt</button>
    </form>

    <form method="post" action="{{ route('admin.weather.update') }}" class="stack-form">
        @csrf
        @method('PUT')
        <label for="place">Paikkakunta</label>
        <input id="place" name="place" value="{{ old('place', $settings->place) }}" required maxlength="80">
        <button type="submit">Tallenna</button>
    </form>
@endsection
