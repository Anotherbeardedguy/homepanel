<!DOCTYPE html>
<html lang="fi-FI">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ei oikeutta</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="login-body">
    <main class="login-card">
        <h1>Ei oikeutta</h1>
        <p>{{ $exception->getMessage() ?: 'Tähän näkymään ei ole oikeutta.' }}</p>
        <p><a href="{{ url('/') }}">Palaa alkuun</a></p>
    </main>
</body>
</html>
