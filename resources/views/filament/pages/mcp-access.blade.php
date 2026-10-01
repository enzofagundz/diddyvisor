<x-filament-panels::page>
    @if ($plainTextToken)
        <section class="dv-summary">
            <h2>Token gerado</h2>
            <p>Copie agora: ele não será exibido de novo. Use no Hermes como <code>{{ '${DIDDYVISOR_MCP_TOKEN}' }}</code> ou em clientes sem OAuth.</p>
            <pre>{{ $plainTextToken }}</pre>
        </section>
    @endif

    <section class="dv-summary">
        <h2>Conectar um agente (Hermes)</h2>
        <ol>
            <li>Tenha uma conta verificada e participação em uma casa.</li>
            <li>Adicione o servidor no terminal:
                <pre>hermes mcp add diddyvisor --url "{{ url('/mcp') }}" --auth oauth</pre>
            </li>
            <li>Autorize no navegador (não recarregue a página, que o pedido se perde):
                <pre>hermes mcp login diddyvisor</pre>
            </li>
            <li>Confirme a conexão:
                <pre>hermes mcp test diddyvisor</pre>
            </li>
        </ol>
        <p>Para clientes sem OAuth, gere um token abaixo e configure no <code>config.yaml</code> do Hermes:
            <code>{{ 'headers: { Authorization: "Bearer ${DIDDYVISOR_MCP_TOKEN}" }' }}</code>, com a URL <code>{{ url('/mcp') }}</code>.
        </p>
    </section>

    <p class="dv-intro">Tokens da sua conta para conectar agentes ao servidor MCP. Revogar derruba o acesso imediatamente.</p>

    {{ $this->table }}
</x-filament-panels::page>
