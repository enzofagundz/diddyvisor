<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Convite · DiddyVisor</title>
    @vite(['resources/css/filament/app/theme.css', 'resources/js/invitation.js'])
    @fonts
</head>
<body class="dv-invitation-page">
    <main class="dv-invitation-card">
        <img src="{{ asset('images/diddy/diddy-convite.png') }}" alt="Diddy abrindo um envelope com um convite" width="180" height="180">
        @if ($invitation)
            <h1>Uma casa, contas organizadas.</h1>
            <p>Você foi convidado para <strong>{{ $invitation->house->name }}</strong>.</p>
            <form method="post" action="{{ url('/convites/'.$invitationId.'/aceitar') }}">
                @csrf
                <button class="dv-primary" type="submit">Aceitar convite</button>
            </form>
        @else
            <h1>Vamos organizar as contas?</h1>
            <p>Continue para entrar ou criar sua conta com o e-mail convidado.</p>
            <form id="prepare-invitation" method="post" action="{{ url('/convites/'.$invitationId.'/preparar') }}">
                @csrf
                <input type="hidden" id="invitation-token" name="token">
                <button class="dv-primary" type="submit">Continuar</button>
            </form>
            <p id="missing-token" hidden>Abra o link completo recebido por e-mail.</p>
        @endif
        @if ($errors->any())
            <p role="alert">{{ $errors->first() }}</p>
        @endif
    </main>
</body>
</html>
