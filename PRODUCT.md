# DiddyVisor

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 13, Filament 5, Livewire 4 e PostgreSQL via lerd. Entrega local, sem deploy.

## Users

Moradores de casas compartilhadas que consultam sua parte de contas e declaram quitação. Administradores gerenciam contas, membros e convites por casa.

## Product Purpose

Substituir uma planilha de divisão de contas domésticas por um aplicativo com login, histórico e permissões. A tarefa central é consultar a competência mensal, conferir a parte individual e marcar pagamento integral.

## Capabilities and Constraints

Usuários participam de várias casas. Navegação exibe mês atual e próximos 12, com histórico editável. Valores são centavos inteiros; divisão igual aceita ajustes. Estados são Pendente, Parcial e Pago. Saída preserva dívidas e histórico. Sem pagamentos parciais, movimentação bancária, comprovantes, recorrência automática ou sincronização em tempo real.

## Brand Commitments

Nome DiddyVisor, logo oficial `DiddyVisor.png` e identidade definida em `DESIGN.md`. Aplicativo financeiro doméstico com personalidade, sem aparência de jogo. Verde reservado a pagamentos concluídos e estados positivos.

## Evidence on Hand

Discovery, especificação aprovada e plano técnico em `/home/enzo/.opencode/plan/diddyvisor-spec.md` e `diddyvisor-technical-plan.md`. Referência funcional em `/home/enzo/Projects/planilha`; referência técnica em `/home/enzo/Projects/app-do-bruno`.

## Accessibility & Inclusion

Português brasileiro, BRL, America/Sao_Paulo. Teclado, contraste WCAG AA, zoom de 200% e tabela rolável localmente no celular. Cor nunca comunica estado sozinha.
