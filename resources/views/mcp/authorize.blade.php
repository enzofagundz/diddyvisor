<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autorizar acesso — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f6f4f2; color: #3b2f28;
        }
        .card {
            width: 100%; max-width: 420px; background: #fff; border: 1px solid #e3dcd6; border-radius: 12px;
            box-shadow: 0 6px 24px rgba(59, 47, 40, .08); padding: 28px 24px;
        }
        .logo { height: 56px; margin: 0 auto 12px; display: block; }
        .hero { height: 148px; margin: 4px auto 0; display: block; }
        h1 { font-size: 20px; margin: 0 0 6px; text-align: center; }
        .muted { color: #765C4E; font-size: 14px; text-align: center; margin: 0; }
        .section { margin-top: 20px; }
        .section > strong { display: block; margin-bottom: 8px; }
        .box { background: #f6f4f2; border: 1px solid #e3dcd6; border-radius: 8px; padding: 12px 14px; font-size: 14px; }
        ul { margin: 0; padding-left: 18px; }
        li { font-size: 14px; color: #765C4E; margin: 4px 0; }
        .actions { display: flex; gap: 12px; margin-top: 24px; }
        .actions form { flex: 1; }
        button {
            width: 100%; border: 0; border-radius: 8px; padding: 12px 16px; font-size: 14px; font-weight: 600;
            cursor: pointer; font-family: inherit;
        }
        .approve { background: #C92332; color: #fff; }
        .approve:hover { background: #a91b28; }
        .cancel { background: #fff; color: #765C4E; border: 1px solid #d8cfc8; }
        .cancel:hover { background: #f1ece8; }
    </style>
</head>
<body>
<div class="card">
    <img class="logo" src="{{ asset('images/diddy/diddy-avatar.png') }}" alt="{{ config('app.name') }}">

    <h1>Autorizar {{ $client->name }}</h1>
    <p class="muted">Esta aplicação poderá acessar suas casas, contas e pagamentos pelo servidor MCP.</p>

    <img class="hero" src="{{ asset('images/diddy/diddy-agente.png') }}" alt="">

    <div class="section">
        <div class="box">
            <strong>Conectado como</strong>
            {{ $user->email }}
        </div>
    </div>

    @if (count($scopes) > 0)
        <div class="section">
            <strong>Permissões</strong>
            <ul>
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="cancel">Cancelar</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="approve">Autorizar</button>
        </form>
    </div>
</div>
</body>
</html>
