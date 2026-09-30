# DiddyVisor — Design System

## Direção visual

O DiddyVisor deve utilizar como referência estrutural uma estética de **produto financeiro ilustrado**, combinando a clareza de um aplicativo de finanças com uma identidade visual própria, descontraída e memorável.

A referência principal é o estilo **Kikin**, especialmente:

* composição editorial;
* uso de ilustrações como parte da interface;
* fundos claros e quentes;
* paleta limitada;
* cards e blocos de conteúdo bem definidos;
* tipografia expressiva nos títulos;
* interface simples e funcional;
* elementos gráficos que reforçam a identidade da marca.

**Não copiar a identidade visual, ilustrações, componentes ou elementos proprietários da referência.** A referência serve apenas como direção estética.

---

## Identidade do DiddyVisor

O mascote do DiddyVisor é um **macaco cartunesco original**, utilizado como elemento central da identidade da aplicação.

O personagem deve transmitir:

* organização;
* controle das contas;
* praticidade;
* personalidade;
* leveza;
* confiança.

O mascote pode aparecer em:

* dashboard;
* estados vazios;
* onboarding;
* mensagens de sucesso;
* páginas de erro;
* elementos promocionais;
* ilustrações contextuais.

O mascote **não deve dominar a interface**. A aplicação continua sendo um produto de organização financeira, e não um jogo.

---

## Paleta

A identidade deve partir das cores do mascote, evitando reproduzir diretamente a paleta da referência.

### Cores principais

* **Vermelho** — identidade principal e ações primárias.
* **Creme** — background principal e superfícies.
* **Marrom escuro** — textos, bordas e elementos estruturais.
* **Amarelo** — destaque e elementos secundários.
* **Verde** — exclusivamente associado a pagamentos concluídos ou estados positivos.

### Estados

**Pago**

* Verde

**Pendente**

* Vermelho ou laranja

**Informativo**

* Marrom/tons neutros

**Atenção**

* Amarelo

As cores de estado devem possuir significado consistente e não devem ser utilizadas apenas como decoração.

---

## Fundos

Priorizar backgrounds **claros, quentes e levemente cremosos**.

Evitar:

* branco puro como background predominante;
* gradientes excessivos;
* backgrounds muito escuros;
* efeitos de glassmorphism;
* excesso de sombras.

As superfícies podem utilizar branco ou tons próximos ao creme para criar hierarquia visual.

---

## Cards

Cards devem possuir aparência simples e sólida.

Características:

* bordas arredondadas moderadas;
* bordas sutis;
* sombras discretas;
* espaçamento interno generoso;
* hierarquia tipográfica clara;
* poucos elementos decorativos.

Cards não devem parecer componentes genéricos de um dashboard administrativo.

---

## Dashboard

O dashboard deve apresentar as informações financeiras de forma imediatamente compreensível.

Prioridade visual:

1. mês atual;
2. total de contas;
3. valor total;
4. contas pendentes;
5. contas pagas;
6. lista das próximas contas.

A interface pode utilizar ilustrações e elementos gráficos do mascote para quebrar a aparência excessivamente administrativa.

---

## Planilha de contas

A tabela é o elemento funcional principal do produto.

Ela deve manter aparência de **planilha financeira simples**, mas com tratamento visual consistente com a identidade do DiddyVisor.

Colunas:

```text
Conta
Vencimento
Valor total
[Membro]
[Membro] pagou?
Status
```

Checkboxes de pagamento devem ser visualmente claros e fáceis de utilizar.

O status deve ser imediatamente identificável:

```text
Pago
Pendente
```

Evitar transformar cada linha da tabela em um card excessivamente decorado.

---

## Navegação mensal

Os meses devem ser apresentados em abas ou navegação horizontal.

Estrutura:

```text
Set  Out  Nov  Dez  Jan  Fev  Mar  Abr ...
```

O mês atual deve possuir destaque visual evidente.

A navegação deve permitir acesso ao:

* mês atual;
* próximos 12 meses.

---

## Tipografia

A tipografia deve equilibrar **personalidade nos títulos** com **legibilidade na interface**.

### Títulos

Podem utilizar uma fonte com personalidade e formas arredondadas, reforçando o caráter da marca.

### Interface

Utilizar uma fonte sans-serif altamente legível para:

* tabelas;
* valores;
* formulários;
* botões;
* labels;
* mensagens de estado.

Evitar utilizar fontes decorativas em grandes quantidades de texto.

---

## Ilustrações

As ilustrações devem seguir uma linguagem visual consistente:

* cartoon moderno;
* contornos bem definidos;
* formas arredondadas;
* paleta limitada;
* sombras discretas;
* aparência vetorial;
* expressões simples e legíveis.

O mascote pode interagir visualmente com objetos relacionados ao produto:

* contas;
* calendário;
* checklist;
* carteira;
* celular;
* boletos;
* moedas;
* gráficos.

Evitar representações excessivamente infantis.

---

## Iconografia

Utilizar ícones simples, com traços consistentes e aparência arredondada.

Ícones devem complementar a interface, não competir com o mascote.

Preferir:

* check;
* calendário;
* carteira;
* usuários;
* casa;
* conta;
* gráfico;
* sino;
* configurações.

---

## Botões

Botões primários devem utilizar o **vermelho da identidade**.

Características:

* formato arredondado;
* peso tipográfico médio/forte;
* altura confortável;
* estados `hover`, `focus`, `active` e `disabled` claramente definidos.

Evitar excesso de botões preenchidos na mesma tela.

---

## Tom visual

O DiddyVisor deve parecer:

> **um aplicativo financeiro doméstico com personalidade de marca, e não uma planilha corporativa.**

A interface deve combinar:

**Organização + Finanças + Personalidade + Simplicidade**

O objetivo é que o usuário reconheça o DiddyVisor visualmente mesmo sem o nome da aplicação estar presente.
