<x-filament-panels::page>
    @if ($plainTextToken)
        <section class="dv-summary">
            <h2>Token gerado</h2>
            <p>Copie agora: ele não será exibido de novo. Use no Hermes como <code>{{ '${DIDDYVISOR_MCP_TOKEN}' }}</code> ou em clientes sem OAuth.</p>
            <pre>{{ $plainTextToken }}</pre>
        </section>
    @endif

    <p class="dv-intro">Tokens da sua conta para conectar agentes ao servidor MCP. Revogar derruba o acesso imediatamente.</p>

    {{ $this->table }}
</x-filament-panels::page>
