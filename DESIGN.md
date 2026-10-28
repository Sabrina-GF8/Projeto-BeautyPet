---
name: BeautyPet Design System
version: 1.0
description: >
  Design system do BeautyPet (Centro de Estética Animal) para web (sistema de gestão)
  e mobile (app do tutor). Tema claro e escuro. Idioma da interface: pt-BR.
colors:
  brand:
    amarelo-50:  "#FFFBEA"
    amarelo-100: "#FFF4C2"
    amarelo-200: "#FFE680"
    amarelo-300: "#FFD84D"
    amarelo-500: "#F3B000"   # primária
    amarelo-700: "#9A6F00"
    amarelo-900: "#5C4300"
  neutral:
    cinza-0:   "#FFFFFF"
    cinza-50:  "#F7F8FA"
    cinza-100: "#EEF0F3"
    cinza-200: "#E5E5E5"
    cinza-300: "#D4D4D4"
    cinza-400: "#A3A3A3"
    cinza-500: "#6B6B6B"
    cinza-700: "#444444"
    cinza-900: "#222222"
  feedback:
    sucesso: "#1E8A47"
    sucesso-claro: "#EAF8EF"
    erro: "#D92D2A"
    erro-claro: "#FDECEC"
    aviso: "#B26B00"
    aviso-claro: "#FFF4E0"
    info: "#2F5FD0"
    info-claro: "#EAF0FD"
  chart: ["#F3B000", "#2F5FD0", "#1E8A47", "#7C5CE0", "#E5664A"]
typography:
  font-family: "Poppins, system-ui, sans-serif"
  font-mono: "ui-monospace, Consolas, monospace"
  weights: [400, 500, 600]
  scale:
    xs:  { size: 12px, line-height: 16px, weight: 500 }
    sm:  { size: 14px, line-height: 22px, weight: 400 }
    md:  { size: 16px, line-height: 24px, weight: 600 }
    lg:  { size: 20px, line-height: 28px, weight: 600 }
    xl:  { size: 24px, line-height: 32px, weight: 600 }
    2xl: { size: 32px, line-height: 40px, weight: 600 }
spacing:
  base: 4px
  scale: { 1: 4px, 2: 8px, 3: 12px, 4: 16px, 6: 24px, 8: 32px, 12: 48px }
radius: { sm: 6px, md: 10px, lg: 16px, full: 999px }
shadow:
  1: "0 1px 3px rgba(0,0,0,.08)"
  2: "0 8px 24px rgba(0,0,0,.10)"
  3: "0 20px 48px rgba(0,0,0,.18)"
motion: { duration: 180ms, toast-modal-enter: 250ms }
---

# BeautyPet — DESIGN.md

Guia para qualquer pessoa (ou agente de IA) que for criar ou alterar telas do BeautyPet.
**Regra de ouro:** use sempre os tokens e componentes abaixo. Nunca invente cor, tamanho,
raio ou peso de fonte fora do sistema.

## 1. Princípios

O sistema é guiado pelas 10 heurísticas de Nielsen. Na prática:

1. **Status visível** — botões mostram "Salvando…", toasts confirmam ações, o agendamento tem indicador de etapas.
2. **Linguagem do usuário** — "Tutor", "Pet", "Banho e tosa". Nunca "registro", "ID" ou erros técnicos.
3. **Controle e liberdade** — todo modal tem "Cancelar"; exclusão oferece "Desfazer" no toast.
4. **Consistência** — uma fonte, uma paleta, um botão primário, iguais na web e no app.
5. **Prevenção de erros** — máscaras de telefone/CPF, datas passadas bloqueadas, confirmação antes de excluir.
6. **Reconhecer em vez de lembrar** — escolher o pet por foto e nome; resumo antes de confirmar agendamento.
7. **Eficiência** — busca rápida, filtros em chips, "agenda padrão" para horários fixos.
8. **Minimalismo** — 6 tamanhos de texto, bastante espaço em branco, cor só onde comunica algo.
9. **Erros claros** — mensagem embaixo do campo, em vermelho, dizendo como corrigir.
10. **Ajuda** — textos de ajuda nos campos, estados vazios explicativos, Assistente IA.

## 2. Cores

**Papéis semânticos (tema claro):**

| Token | Valor | Uso |
|---|---|---|
| `--fundo` | `cinza-50` | Fundo da página |
| `--superficie` | `cinza-0` | Cards, modais, menus |
| `--borda` | `cinza-200` | Bordas gerais |
| `--texto` | `cinza-900` | Texto principal |
| `--texto-2` | `cinza-500` | Texto secundário |
| `--primaria` | `amarelo-500` | Ação principal da tela |
| `--sobre-primaria` | `cinza-900` | Texto sobre o amarelo |

**Uso de cada cor**

- `amarelo-50` fundos de destaque · `100` seleção e chips ativos · `200` hover suave · `300` foco e gráficos · `500` primária/marca · `700` texto amarelo em fundo claro · `900` texto sobre amarelo claro.
- `cinza-200` bordas · `300` bordas de campo · `400` placeholder e ícones · `500` texto secundário · `700` texto forte · `900` texto principal.
- `sucesso` confirmado/positivo · `erro` erro e exclusão · `aviso` pendente/atenção · `info` informação e links. Cada um tem versão `-claro` para fundos.
- Gráficos: usar a ordem `#F3B000`, `#2F5FD0`, `#1E8A47`, `#7C5CE0`, `#E5664A`; a série principal é sempre a amarela.

**Tema escuro** (`[data-theme="dark"]` ou `prefers-color-scheme: dark`):
`--fundo #141518`, `--superficie #1D1F23`, `--borda #2E3137`, `--texto #F2F2F2`, `--texto-2 #A9ACB3`,
`cinza-50 #1A1C20`, `cinza-100 #26282D`, `erro-claro #3A1D1D`, `sucesso-claro #16301F`,
`aviso-claro #3A2C10`, `info-claro #1A2440`, `amarelo-50 #2E2710`, `amarelo-100 #3B3212`.
Textos amarelos (eyebrow, avatar, botão contorno) usam `amarelo-300` no escuro.

**Contraste (WCAG)**

| Combinação | Contraste | Resultado |
|---|---|---|
| `#222` em `#F3B000` | 9,2:1 | AA/AAA |
| `#6B6B6B` em branco | 5,3:1 | AA (texto secundário) |
| `#999` em branco | 2,8:1 | Reprovado — não usar |
| Branco em `#F3B000` | 2,0:1 | Reprovado — não usar |

**Faça:** amarelo para a ação principal (uma por tela) com texto `#222`.
**Evite:** texto branco sobre amarelo, vermelho como cor de destaque, tons novos fora da paleta.

## 3. Tipografia

- **Principal:** Poppins (Google Fonts), pesos 400, 500 e 600, em web e mobile.
- **Monoespaçada:** `ui-monospace, Consolas`, peso 400. Só para códigos (AG-0042, PET-0107), CPF e números que alinham em coluna.

| Estilo | Tamanho/linha | Peso | Token | Uso |
|---|---|---|---|---|
| H1 | 32/40 | 600 | `--t-2xl` | Título da página |
| H2 | 24/32 | 600 | `--t-xl` | Título de seção |
| H3 | 20/28 | 600 | `--t-lg` | Título de card/modal |
| H4 | 16/24 | 600 | `--t-md` | Subtítulo |
| Parágrafo | 14/22 | 400 | `--t-sm` | Texto padrão, rótulos, botões (no mobile o parágrafo usa 16px) |
| Legenda | 12/16 | 500 | `--t-xs` | Datas, ajuda, badges |

Regras: um único H1 por página · texto corrido nunca abaixo de 14px (legenda mínima 12px) ·
contraste mínimo 4,5:1 · títulos sem ponto final e só com a primeira letra maiúscula ·
600 para títulos, 500 para rótulos e botões, 400 para texto corrido · altura de linha 1.5.
**Evite** tamanhos fora da escala (9, 11, 13, 17, 19, 21, 27px…) e o peso 700.
O eyebrow é 12px, 600, maiúsculas, `letter-spacing .08em`, cor `amarelo-700`.

## 4. Espaçamento e grid

Tudo em múltiplos de 4px.

| Token | Valor | Uso |
|---|---|---|
| `--e-1` | 4px | Ícone + texto |
| `--e-2` | 8px | Entre itens próximos |
| `--e-3` | 12px | Dentro de campos |
| `--e-4` | 16px | Padding de cards |
| `--e-6` | 24px | Entre blocos |
| `--e-8` | 32px | Margem da página |
| `--e-12` | 48px | Entre seções |

- **Web:** menu lateral 75px (expande a 260px), cabeçalho 75px, margem 32px, grid de 12 colunas com gap 24px.
- **Mobile:** margem lateral 16px, 4 colunas, gap 16px, barra de abas inferior.
- **Alvo de toque:** mínimo 44×44px em qualquer elemento clicável no app.
- **Responsivo (≤860px):** grids viram 1 coluna, tabelas rolam na horizontal, menu lateral vira botão "☰ Menu".

## 5. Raio, sombra e movimento

- **Raio:** `sm 6px` badges internos e tooltips · `md 10px` botões, campos, alertas · `lg 16px` cards e modais · `full` chips, avatares, pílulas. Não usar outros valores.
- **Elevação:** nível 0 fundo/tabelas · nível 1 `--s-1` cards · nível 2 `--s-2` menus e toasts · nível 3 `--s-3` modais.
- **Movimento:** 180ms em hover e foco; 250ms em entrada de toasts e modais. Nada de animação decorativa longa.
- **Foco visível:** `outline: 3px solid amarelo-300; outline-offset: 2px`.

## 6. Ícones

Estilo outline, 24px, traço 2px, pontas e cantos arredondados (`stroke-linecap/linejoin: round`).
Biblioteca sugerida: **Lucide** (web e React Native). Ícone sozinho só quando o significado é óbvio;
nos demais casos, ícone + texto. Ícones do menu: Início, Agenda, Tutores, Pets, Solicitações, Financeiro.

## 7. Componentes

### Botões
- Altura padrão **44px**, padding horizontal 24px, raio `md`, peso 500, texto 14px.
- Tamanhos: pequeno 32px (12px, padding 12) · padrão 44px · grande 52px (16px, padding 32).
- Variações: **primária** (fundo `--primaria`, texto `#222`, hover `#E0A300`) · **secundária** (superfície, borda `cinza-300`) · **contorno** (borda `amarelo-500`, texto `amarelo-700`) · **fantasma** (transparente) · **perigo** (fundo `erro`, texto branco, hover `#B82422`).
- Estados: padrão, hover, foco (anel amarelo), carregando (spinner 16px + "Salvando…"), desabilitado (opacity .45).
- `:active` desloca 1px para baixo.
- Só **um botão primário** por tela ou modal. Em telas de entrada (login, cadastro, rodapé do app) ocupa a largura toda.
- Primária à direita no desktop, em cima no mobile.
- Verbos claros: "Salvar", "Agendar", "Excluir pet". **Evite** "OK", "Enviar" genérico e dois primários lado a lado.

### Formulários
- Rótulo **sempre visível** acima do campo (14px, 500); obrigatório marcado com `*` vermelho. Placeholder não substitui rótulo.
- Input: altura 44px, borda `cinza-300`, raio `md`, padding 12px. Foco: borda `amarelo-500` + `box-shadow: 0 0 0 3px rgba(243,176,0,.25)`.
- Texto de ajuda 12px em `--texto-2`.
- Estados: **erro** (borda `erro` + mensagem embaixo dizendo como corrigir) · **ok** (borda `sucesso`) · desabilitado.
- Textarea: min 88px. Select: sem aparência nativa, seta própria.
- Com ícone à esquerda em e-mail, senha e busca; senha tem botão "Mostrar/Ocultar".
- Checkbox 18px e switch 44×24px, ambos com `accent`/fundo `amarelo-500` quando ativos.
- Validação ao sair do campo; máscaras de telefone e CPF. Telefone: `(11) 98765-4321`.
- **Evite** erro só com mudança de cor da borda.

### Badges, chips e alertas
- **Badge:** 24px, pílula, 12px/500, com bolinha de 6px à esquerda. Sempre **cor + texto**, nunca só cor.
  Confirmado = ok · Pendente = aviso · Em atendimento = info · Cancelado = erro · Rascunho = neutro.
- **Chip (filtro):** 32px, pílula; ativo (`aria-pressed`) com fundo `amarelo-100` e borda `amarelo-500`.
- **Alerta:** raio `md`, padding 16px, borda esquerda de 4px na cor do tipo (sucesso, aviso, erro, info) com fundo `-claro`. Título em negrito + descrição.

### Cards e KPIs
- **Card:** superfície, borda `--borda`, raio `lg`, padding 16px, sombra `--s-1`, gap 12px. Topo com avatar 44px (fundo `amarelo-100`, inicial em `amarelo-900`), meta em 12px `--texto-2`, ações no rodapé.
- **KPI:** superfície, raio `lg`, legenda 12px, valor 24px/600, variação 12px em `sucesso`. Ex.: "Atendimentos hoje 12", "Faturamento do mês R$ 8.430".
- **Skeleton:** gradiente `cinza-100 → cinza-50 → cinza-100`, 1,2s, raio `sm`.

### Navegação
- **Menu lateral (web):** fundo branco, recolhido a 75px (só ícones), abre a 240–260px no hover (sombra `--s-2`). Item de 40px; ativo com fundo `amarelo-100` e barra de 3px `amarelo-500` à esquerda. Mesma ordem de itens na web e no app.
- **App:** barra de abas inferior.
- **Abas:** trilho `cinza-100`, raio `md`; aba selecionada com fundo superfície, peso 500 e `--s-1`.
- **Etapas (stepper de agendamento):** círculo 28px; feito = `sucesso` com ✓; atual = `--primaria`; futuro = `cinza-100`; linha de 32×2px entre etapas. Etapas: Pet → Serviço → Data e hora → Resumo.

### Tabelas e listas
Cabeçalho 12px/500 em `--texto-2` com fundo `cinza-50`; linhas de 48px; células com padding 12×16; hover `cinza-50`;
valores monetários **alinhados à direita** (`R$ 1.234,56`); raio `lg`, borda `--borda`; envolver em container com `overflow-x:auto`.

### Estado vazio
Borda tracejada 2px, raio `lg`, centralizado, ícone, título, explicação curta e **uma ação** ("Cadastrar pet").

### Modais e toasts
- **Modal:** só para decisões que bloqueiam o fluxo (ex.: excluir). Véu `rgba(0,0,0,.45)`, caixa de até 420px, raio `lg`, padding 24px, `--s-3`. Botões à direita: Cancelar + ação. Fecha com Esc e clique no véu; foco inicial em "Cancelar".
- **Toast:** para confirmar sucesso; fundo `cinza-900`, texto branco, raio `md`, `--s-2`, canto inferior direito, some em ~4s e pode ter ação "Desfazer" (em `amarelo-300`).

## 8. Padrões de fluxo

- **Toda tela tem 4 estados:** carregando (skeleton) · vazio (explica e oferece ação) · erro (diz o que fazer) · com dados.
- **Ação destrutiva:** botão vermelho → modal que cita o nome do item → toast com "Desfazer".
- **Formulário longo:** dividir em etapas, salvar progresso e mostrar resumo antes de confirmar.
- **Formatos:** data `30/10/2026` · hora `14:00` · dinheiro `R$ 1.234,56` · telefone `(11) 98765-4321`.

## 9. Escrita (UX writing)

Tom próximo e simples, tratando por "você". Frases curtas. Maiúscula só na primeira palavra ("Novo agendamento").

| Em vez de | Escreva |
|---|---|
| "Erro 500 / SQLSTATE…" | "Não conseguimos salvar agora. Tente de novo em instantes." |
| "Registro inserido com sucesso" | "Pet Thor cadastrado!" |
| "Tem certeza?" | "Excluir o pet Thor? O histórico dele também será apagado." |
| "Campo inválido" | "O telefone precisa ter DDD + 9 dígitos." |
| "Nenhum resultado" | "Nenhum tutor com 'Ana'. Confira o nome ou cadastre um novo." |

## 10. Acessibilidade

Contraste ≥ 4,5:1 · foco visível em todo elemento interativo · nunca comunicar só por cor ·
alvos de toque ≥ 44px · modo escuro testado em cada componente novo · usar `aria-current`, `aria-selected`,
`aria-pressed` e `aria-checked` nos componentes de estado.

## 11. Tela Home do app (padronização pendente)

A Home atual (`src/app/home.tsx`) usa fundo amarelo, saudação, carrossel de promoções, card de próximo
agendamento, botão de largura total e menu inferior. Ajustes a aplicar para entrar no sistema:

- Títulos em Poppins 700 → **600**.
- Tamanhos 28, 21, 13 e 11px → **32/24, 20, 14 e 12px** da escala.
- Botão "Agendar serviço" roxo `#5D57FB` → **cor primária** (`#F3B000`, texto `#222`).
- Raios 22, 18 e 17px → **16px (lg) e 10px (md)**.
- Texto do menu inferior 11px → **12px**.
- Vermelho no sino, em "Beauty Pet" e no item ativo do menu → **amarelo da marca** (vermelho fica só para erros e para a logo).

## 12. Faça / Evite (resumo)

**Faça:** usar tokens (`var(--primaria)`, `theme.cores`) · um primário por tela · rótulo visível nos campos ·
erro que explica a solução · status com cor + texto · primeira palavra maiúscula nos títulos.

**Evite:** cores, tamanhos ou raios soltos · peso 700 · texto abaixo de 12px · texto branco sobre amarelo ·
botões "OK" genéricos · ícones sem rótulo em ações importantes · menus que mudam de ordem entre telas.
