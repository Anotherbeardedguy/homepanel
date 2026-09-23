<!DOCTYPE html>
<html lang="fi-FI">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Yhdistä näyttö</title>
    <link rel="stylesheet" href="{{ asset('css/display.css') }}?v={{ filemtime(public_path('css/display.css')) }}">
</head>
<body>
    <main class="pair">
        @if (session('status'))
            <p class="connection">{{ session('status') }}</p>
        @endif
        @if ($blocked)
            <h1>Näyttö täytyy yhdistää ilman hallinnan kirjautumista</h1>
            <p>Kirjaudu ulos tai avaa tämä osoite näyttölaitteen selaimessa.</p>
        @elseif ($code)
            <h1>Yhdistä tämä näyttö</h1>
            <p class="pair-code" id="pair-code">{{ $code }}</p>
            <p>Syötä koodi hallinnassa kohdassa Laitteet. Koodi vanhenee viidessä minuutissa.</p>
            <p id="pair-status">Odotetaan hyväksyntää.</p>
        @else
            <h1>Yhdistä tämä näyttö</h1>
            <p>Pyydä koodi ja hyväksy se hallinnassa.</p>
            <form method="post" action="{{ route('display.pair.store') }}">
                @csrf
                <button type="submit">Näytä koodi</button>
            </form>
        @endif
    </main>
    @if ($code)
        <script src="{{ asset('js/pair.js') }}?v={{ filemtime(public_path('js/pair.js')) }}"></script>
    @endif
</body>
</html>
