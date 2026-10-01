@props([
    'code',
    'title',
    'message',
    'image',
    'alt' => '',
])

<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · DiddyVisor</title>
    <link rel="icon" href="{{ asset('images/diddy/favicon.ico') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #FFF8EE; color: #3B241A;
        }
        .card { width: 100%; max-width: 440px; text-align: center; }
        .art { height: 240px; width: auto; display: block; margin: 0 auto 8px; }
        .code { margin: 0; font-size: 13px; font-weight: 700; letter-spacing: .08em; color: #765C4E; font-variant-numeric: tabular-nums; }
        h1 { font-size: 24px; margin: 8px 0 12px; }
        p { margin: 0 0 24px; line-height: 1.6; color: #765C4E; }
        .primary {
            display: inline-block; background: #C92332; color: #fff; text-decoration: none;
            padding: 12px 24px; border-radius: 8px; font-weight: 600; min-height: 44px;
        }
        .primary:hover { background: #A51B28; }
        .primary:focus-visible { outline: 2px solid #3B241A; outline-offset: 3px; }
    </style>
</head>
<body>
    <main class="card">
        <img class="art" src="{{ asset($image) }}" alt="{{ $alt }}" width="240" height="240">
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a class="primary" href="{{ url('/app') }}">Voltar para as contas</a>
    </main>
</body>
</html>
