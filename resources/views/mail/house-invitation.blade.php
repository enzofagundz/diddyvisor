<x-mail::message>
<img src="{{ asset('images/diddy/diddy-email.png') }}" alt="DiddyVisor" style="display: block; width: 100%; max-width: 480px; height: auto; margin: 0 auto 24px;">

# Você recebeu um convite!

Participe de {{ $houseName }} para organizar as contas da casa.

Este convite vale por sete dias e só pode ser aceito pelo e-mail convidado.

<x-mail::button :url="$url">Ver convite</x-mail::button>

DiddyVisor
</x-mail::message>
