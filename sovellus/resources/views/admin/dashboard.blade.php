@extends('layouts.admin')
@section('title', 'Yhteenveto')

@section('content')
    <h1>Yhteenveto</h1>
    <p class="lede muted">Avoimia kotitöitä tänään tai myöhässä: {{ $openChores }}. Yhdistettyjä näyttöjä: {{ $devices }}.</p>
    <ul class="status-list">
        @foreach ($integrations as $integration)
            <li>
                <a href="{{ $integration['href'] }}">{{ $integration['name'] }}</a>
                <span class="state state-{{ $integration['tone'] }}">{{ $integration['status'] }}@if ($integration['detail']) · {{ $integration['detail'] }}@endif</span>
            </li>
        @endforeach
    </ul>
    <p class="muted">Sähkö ja sää haetaan ilman avainta. Yksityinen kalenteri lisätään salaisella iCal-osoitteella. Julkinen kalenteri käyttää rajapinta-avainta.</p>
@endsection
