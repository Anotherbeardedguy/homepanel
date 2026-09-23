<!DOCTYPE html>
<html lang="fi-FI">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta http-equiv="content-language" content="fi-FI">
    <title>@yield('title', 'Kotinäyttö') — Kotinäyttö</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
    <a class="skip" href="#sisalto">Siirry sisältöön</a>
    <div class="shell">
        <header class="bar">
            <a class="brand" href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('admin.chores.index') }}">Kotinäyttö</a>
            <p class="who">{{ auth()->user()->name }}</p>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Kirjaudu ulos</button>
            </form>
        </header>
        <nav class="rail" aria-label="Hallinta">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Yhteenveto</a>
                <a href="{{ route('admin.family.index') }}" @if (request()->routeIs('admin.family.*')) aria-current="page" @endif>Perhe</a>
            @endif
            <a href="{{ route('admin.chores.index') }}" @if (request()->routeIs('admin.chores.*')) aria-current="page" @endif>Kotityöt</a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.performance') }}" @if (request()->routeIs('admin.performance')) aria-current="page" @endif>Suoritus</a>
                <a href="{{ route('admin.electricity.edit') }}" @if (request()->routeIs('admin.electricity.*')) aria-current="page" @endif>Sähkö</a>
                <a href="{{ route('admin.weather.edit') }}" @if (request()->routeIs('admin.weather.*')) aria-current="page" @endif>Sää</a>
                <a href="{{ route('admin.calendar.edit') }}" @if (request()->routeIs('admin.calendar.*')) aria-current="page" @endif>Kalenteri</a>
                <a href="{{ route('admin.devices.index') }}" @if (request()->routeIs('admin.devices.*')) aria-current="page" @endif>Laitteet</a>
                <a href="{{ route('display.show') }}">Näyttö</a>
            @endif
        </nav>
        <main id="sisalto">
            @if (session('status'))
                <p class="notice" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div class="errors" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
