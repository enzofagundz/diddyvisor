<x-filament-panels::page>
    <div class="dv-stack">
        @if ($plainTextToken)
            <section class="dv-card" aria-labelledby="mcp-token-title">
                <div class="dv-card-head">
                    <div>
                        <h2 id="mcp-token-title">Token gerado</h2>
                        <p>Copie agora: ele não será exibido de novo. Use no Hermes como <code>{{ '${DIDDYVISOR_MCP_TOKEN}' }}</code> ou em clientes sem OAuth.</p>
                    </div>
                </div>
                <pre class="dv-command dv-token" tabindex="0">{{ $plainTextToken }}</pre>
                <span class="dv-hint">Clique no código para selecionar tudo e copie com Ctrl+C.</span>
            </section>
        @endif

        <section class="dv-card" aria-labelledby="mcp-guide-title">
            <div class="dv-card-head">
                <img class="dv-guide-mascot" src="{{ asset('images/diddy/diddy-agente.png') }}" alt="" width="96" height="96">
                <div>
                    <h2 id="mcp-guide-title">Conectar um agente (Hermes)</h2>
                    <p>Quatro passos entre a sua conta e o agente.</p>
                </div>
            </div>
            <ol class="dv-steps">
                <li class="dv-step">
                    <span class="dv-step-index" aria-hidden="true">1</span>
                    <p>Tenha uma conta verificada e participação em uma casa.</p>
                </li>
                <li class="dv-step">
                    <span class="dv-step-index" aria-hidden="true">2</span>
                    <div>
                        <p>Adicione o servidor no terminal:</p>
                        <pre class="dv-command">hermes mcp add diddyvisor --url "{{ url('/mcp') }}" --auth oauth</pre>
                    </div>
                </li>
                <li class="dv-step">
                    <span class="dv-step-index" aria-hidden="true">3</span>
                    <div>
                        <p>Autorize no navegador (não recarregue a página, que o pedido se perde):</p>
                        <pre class="dv-command">hermes mcp login diddyvisor</pre>
                    </div>
                </li>
                <li class="dv-step">
                    <span class="dv-step-index" aria-hidden="true">4</span>
                    <div>
                        <p>Confirme a conexão:</p>
                        <pre class="dv-command">hermes mcp test diddyvisor</pre>
                    </div>
                </li>
            </ol>
            <div class="dv-note">
                <p>Para clientes sem OAuth, gere um token abaixo e configure no <code>config.yaml</code> do Hermes com a URL <code>{{ url('/mcp') }}</code>:</p>
                <pre class="dv-command">headers: { Authorization: "Bearer {{ '${DIDDYVISOR_MCP_TOKEN}' }}" }</pre>
            </div>
        </section>

        <p class="dv-intro">Tokens da sua conta para conectar agentes ao servidor MCP. Revogar derruba o acesso imediatamente.</p>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
