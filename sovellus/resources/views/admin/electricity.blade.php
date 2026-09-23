@extends('layouts.admin')
@section('title', 'Sähkö')

@section('content')
    <h1>Sähkö</h1>
    <p class="muted">Lähde on api.spot-hinta.fi. Hinnat ovat 15 minuutin jaksoja. Huomisen hinnat tulevat mukaan, kun ne on julkaistu. Avainta ei tarvita.</p>
    <p>Tila: {{ $integration->status === 'ok' ? 'Yhdistetty' : ($integration->status === 'error' ? 'Virhe' : 'Ei haettu') }}
        @if ($integration->last_success_at)
            · viimeksi {{ $integration->last_success_at->timezone(config('homepanel.timezone'))->format('d.m.Y H.i') }}
        @endif
    </p>
    @if ($integration->last_error)
        <p class="errors">{{ $integration->last_error }}</p>
    @endif
    @if ($widget['data']['price'] ?? null)
        <p>Näytöllä nyt: {{ $widget['data']['stateWord'] }}, {{ $widget['data']['price'] }} ({{ $widget['data']['basis'] }}).</p>
    @endif

    <form method="post" action="{{ route('admin.electricity.refresh') }}">
        @csrf
        <button type="submit">Päivitä nyt</button>
    </form>

    <form method="post" action="{{ route('admin.electricity.update') }}" class="stack-form">
        @csrf
        @method('PUT')
        <label for="price_basis">Näytettävä hinta</label>
        <select id="price_basis" name="price_basis">
            @foreach (\App\Enums\PriceBasis::cases() as $basis)
                <option value="{{ $basis->value }}" @selected(old('price_basis', $settings->price_basis->value) === $basis->value)>{{ $basis->label() }}</option>
            @endforeach
        </select>
        <label for="green_below">Vihreä alle, snt/kWh</label>
        <input id="green_below" name="green_below" value="{{ old('green_below', $settings->green_below) }}" required inputmode="decimal">
        <label for="red_from">Punainen alkaen, snt/kWh</label>
        <input id="red_from" name="red_from" value="{{ old('red_from', $settings->red_from) }}" required inputmode="decimal">
        <label for="message_green">Vihreä ohje</label>
        <input id="message_green" name="message_green" value="{{ old('message_green', $settings->message_green) }}" required maxlength="180">
        <label for="message_yellow">Keltainen ohje</label>
        <input id="message_yellow" name="message_yellow" value="{{ old('message_yellow', $settings->message_yellow) }}" required maxlength="180">
        <label for="message_red">Punainen ohje</label>
        <input id="message_red" name="message_red" value="{{ old('message_red', $settings->message_red) }}" required maxlength="180">
        <label for="message_red_extra">Punaisen lisärivi</label>
        <input id="message_red_extra" name="message_red_extra" value="{{ old('message_red_extra', $settings->message_red_extra) }}" maxlength="180">
        <label for="message_unknown">Puuttuvan hinnan teksti</label>
        <input id="message_unknown" name="message_unknown" value="{{ old('message_unknown', $settings->message_unknown) }}" required maxlength="180">
        <button type="submit">Tallenna</button>
    </form>
@endsection
