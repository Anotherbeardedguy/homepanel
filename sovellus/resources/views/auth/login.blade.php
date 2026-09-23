<!DOCTYPE html>
<html lang="fi-FI">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta http-equiv="content-language" content="fi-FI">
    <title>Kirjaudu — Kotinäyttö</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body class="login-body">
    <main class="gate">
        <div class="gate-aside">
            <h1>Kotinäyttö</h1>
            <p>Hallitse kotitöitä, sähköä, säätä ja kalenteria kotiverkossa.</p>
        </div>
        <div class="gate-main">
            <h2>Kirjaudu</h2>
            @if ($errors->any())
                <div class="errors" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            <form method="post" action="{{ route('login') }}">
                @csrf
                <label for="username">Kirjautumistunnus</label>
                <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
                <label for="password">Salasana</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Kirjaudu</button>
            </form>
        </div>
    </main>
</body>
</html>
