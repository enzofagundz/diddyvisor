# diddyvisor

Aplicativo de divisão de contas domésticas em Laravel 13, Filament 5 e PostgreSQL.

## Desenvolvimento local

O site está configurado no lerd em **http://diddyvisor.test/app**. O envio é síncrono; não é necessário iniciar um worker de fila.

O banco local já usa PostgreSQL no serviço `lerd-postgres`, com o banco `diddyvisor`. Os testes usam `diddyvisor_testing`. A configuração padrão da aplicação e `.env.example` usam `pgsql`.

## PostgreSQL no Laravel Cloud

O CLI está instalado como dependência de desenvolvimento. Para autenticar no navegador:

```sh
./vendor/bin/cloud auth -n
```

No ambiente de produção do Laravel Cloud, anexe um banco PostgreSQL como banco padrão. O Cloud injeta as variáveis de conexão. Remova variáveis personalizadas antigas de banco que sobrescrevam os valores injetados; não copie host, usuário ou senha do lerd para produção.

Se a produção já tiver dados em outro banco, preserve um backup e migre os dados antes de trocar a conexão. `php artisan migrate --force` cria ou atualiza o esquema; não transfere dados entre bancos.

Após configurar o banco, faça um novo deploy com `php artisan migrate --force` no comando de deploy. A aplicação requer a extensão `pdo_pgsql`. A criação de recursos pagos, a troca do banco de produção e o deploy dependem da confirmação do ambiente de destino.

## Envio de e-mail com Resend

A aplicação utiliza o transporte Resend nativo do Laravel 13, com `resend/resend-php`. Convites, confirmação de e-mail e redefinição de senha usam a mesma configuração. Não há webhook ou integração paralela.

Para ativar neste ambiente, preencha `.env.lerd_override` com sua chave e um remetente de domínio verificado no Resend:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS=contas@seu-dominio.com
MAIL_FROM_NAME=DiddyVisor
```

Não compartilhe nem versione a chave. Após preencher, reaplique a configuração de ambiente pelo lerd e execute `lerd artisan config:clear`. O arquivo de override é ignorado pelo Git e impede que o lerd volte a selecionar SMTP.

Sem chave e remetente configurados, mantenha SMTP/Mailpit para testes locais. Os testes automatizados não enviam e-mails reais.

Documentação: https://laravel.com/docs/13.x/mail#resend-driver e https://resend.com/docs/send-with-laravel/.

## Assets e testes

Para compilar o tema e as fontes locais:

```sh
npm ci
npm run build
```

Para executar os testes pelo PHP do lerd:

```sh
lerd artisan test
```

Os testes usam exclusivamente `diddyvisor_testing`, separado do banco de desenvolvimento. Os testes de concorrência exigem `pcntl`, disponível no PHP 8.5 do lerd.

## Servidor MCP (Hermes Agent)

O app embute um servidor MCP em `POST /mcp` com OAuth (Passport) para o Hermes Agent operar casas, membros, contas e pagamentos. Cada pessoa autentica com a própria conta e só enxerga as casas dela; criar, alterar e excluir contas continua exigindo papel de administrador na casa.

### Conectar em produção

Cada pessoa precisa de conta verificada e participação numa casa (convite por e-mail). No Hermes, basta apontar para o endpoint — a autorização abre no navegador na primeira chamada:

```yaml
mcp_servers:
  diddyvisor:
    url: https://<app>.laravel.cloud/mcp
```

Quem preferir não usar OAuth gera um token pessoal na página **Acesso MCP** do painel (`/app/<casa>/mcp-access`) e usa:

```yaml
mcp_servers:
  diddyvisor:
    url: https://<app>.laravel.cloud/mcp
    headers:
      Authorization: "Bearer ${DIDDYVISOR_MCP_TOKEN}"
```

Revogar o token na mesma página derruba o acesso na hora.

### Desenvolvimento local (stdio)

```sh
lerd artisan mcp:start diddyvisor
```

A identidade local é fixa por `DIDDYVISOR_MCP_USER` (e-mail ou id):

```yaml
mcp_servers:
  diddyvisor:
    command: php
    args: ["artisan", "mcp:start", "diddyvisor"]
    cwd: /home/enzo/Projects/diddyvisor
```

Tools: `list_houses`, `list_members`, `list_bills`, `get_bill`, `get_monthly_summary`, `create_bill`, `set_share_payment` e `delete_bill`. Valores monetários entram como string pt-BR (`"1234,56"`); a exclusão exige `confirm=true`.

### Deploy

O Laravel Cloud tem filesystem efêmero, então as chaves e a sessão vão por variável de ambiente, e um comando roda uma vez por ambiente.

**Chaves do Passport.** `php artisan passport:keys` no deploy não persiste (e cada réplica teria chaves diferentes). Gere o par localmente e cadastre o conteúdo dos arquivos, com as quebras de linha reais:

```sh
./vendor/bin/cloud environment:variables production --action=set --key=PASSPORT_PRIVATE_KEY --value="$(cat storage/oauth-private.key)" --force
./vendor/bin/cloud environment:variables production --action=set --key=PASSPORT_PUBLIC_KEY --value="$(cat storage/oauth-public.key)" --force
```

Enviar o valor com `\n` literal pode chegar misturado (quebras reais e literais no mesmo valor) e o Passport recusa a chave com "Invalid key supplied".

**Sessão.** Ambiente novo do Laravel Cloud nasce com `SESSION_DRIVER=cookie`. A autorização OAuth guarda a requisição na sessão, que não cabe no cookie, e o autorizar devolve 403. Defina `SESSION_DRIVER=database`; a tabela `sessions` já está nas migrações.

**Token pessoal.** A página Acesso MCP gera tokens pelo Passport; crie o client uma vez por ambiente:

```sh
php artisan passport:client --personal --name=DiddyVisor --no-interaction
```

No deploy, rode `php artisan migrate --force`.

## Regras principais

Cada casa possui seus próprios membros e administradores. Membros alteram apenas seu pagamento. Valores são centavos inteiros, e as partes devem fechar o total. Qualquer pagamento marcado impede alterações financeiras e exclusão da conta. Remover um membro preserva suas dívidas e seu histórico.

`DESIGN.md` define a identidade visual; `DiddyVisor.png` é a logo original. Sem deploy ou integração bancária.
