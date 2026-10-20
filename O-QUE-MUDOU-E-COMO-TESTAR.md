
## Lista para testar

**Menu e topo**
- [ ] A linha embaixo do logo (menu da esquerda) encontra certinho a linha do topo.
- [ ] Clicar em **Financeiro** no menu abre a página (antes dava erro).
- [ ] Clicar em **Configurações** no menu abre a página (antes dava erro).
- [ ] Existe uma **engrenagem** ao lado do sino que também abre Configurações.
- [ ] No **Dashboard**, a busca do topo ("Buscar pet ou tutor...") encontra resultados e abre a ficha.

**Configurações**
- [ ] **Trocar de conta**: entrar com outro usuário. Aparece um aviso verde "Usuário alterado para..." e o nome muda no topo.
- [ ] **Alterar minha senha**: trocar a senha, sair (trocar de conta) e entrar com a senha nova.
- [ ] **Tema escuro**: a tela fica escura em todas as páginas e as fotos dos pets continuam com a cor normal.
- [ ] **Tamanho da letra** Grande e Muito grande: o texto aumenta e a página continua rolando normal.
- [ ] **Alto contraste**, **Destacar o campo selecionado** (navegar com Tab) e **Reduzir animações**.
- [ ] **Alerta visual**: ligar a opção, fazer um pedido de agendamento pelo app. Aparece um aviso vermelho na tela.
- [ ] **Tradutor de Libras (VLibras)**: ligar a opção. Aparece o botão azul do VLibras no canto da tela.
- [ ] **Voltar ao padrão** desfaz tudo.
- [ ] Fechar o navegador e abrir de novo: as escolhas continuam lá.

**Campos com formato automático**
- [ ] Cadastro de tutor, **CPF**: ao digitar só números, aparece `000.000.000-00`. Um CPF inválido mostra um aviso ao sair do campo.
- [ ] Cadastro de tutor, **telefones**: aparece `(11) 98765-4321` no celular e `(11) 3333-4444` no fixo.
- [ ] Cadastro de tutor, **CEP**: aparece `00000-000`, e a rua, o bairro, a cidade e a UF se preenchem sozinhos (se estiverem vazios).
- [ ] Editar um tutor que já existe: CPF e telefone aparecem formatados e salvam normal.
- [ ] Cadastro de pet, **peso**: aceita vírgula (ex.: `12,5`) e salva certo.
- [ ] **Fechamento diário**, valores em R$: digitando 1-2-3-4 aparece `12,34` (da direita para a esquerda).
- [ ] **Fechamento diário (importante)**: lançar um serviço com pagamento em dinheiro, Pix e cartão.
  - O "Total da linha", o "Recebido + crédito" e o "Falta receber" calculam certo.
  - Salvar e conferir se os valores gravados estão certos.
- [ ] Fechamento diário: **Alterar pagamento** de uma linha que já existe. Os valores aparecem certos e salvam certo.
- [ ] Fechamento diário: **Adicional** e **Pagar débito**. Ao escolher "Pagar débito", o valor do serviço trava em 0,00.

**Valores que começam em 0**
- [ ] Fechamento diário → **Finalizar o dia**: as quantidades (banho, tosa...) e os valores conferidos já vêm com 0.
- [ ] Adicional novo já vem com 0,00.

**Outros**
- [ ] Cadastro de tutor: não aparece mais o texto "// Executa esta instrução..." na tela nem dentro da lista de Estados.
- [ ] Ficha do tutor: **Cadastrar novo pet** virou um botão e abre o cadastro do pet com o tutor já escolhido.
- [ ] Cards que **não** são clicáveis não "pulam" mais ao passar o mouse:
  - números do Dashboard;
  - lista de atendimentos do Dashboard;
  - cards da Agenda;
  - resumo do fechamento.
- [ ] Os cards que **são** clicáveis continuam com o efeito (fichas de tutores e de pets).

---

## Arquivos para subir no GitHub (pasta `beautypet-web`)

| Arquivo | Novo? | O que mudou |
|---|---|---|
| `pages/configuracoes.php` | **novo** | Página de Configurações (#4, #5) |
| `pages/financeiro.php` | **novo** | Página Financeiro, só consulta (#5) |
| `assets/js/mascaras.js` | **novo** | Máscaras de CPF, telefone, CEP, dinheiro e peso (#6) |
| `assets/js/preferencias.js` | **novo** | Guarda e aplica tema e acessibilidade (#4) |
| `assets/css/preferencias.css` | **novo** | Visual do tema escuro e da acessibilidade (#4) |
| `assets/css/configuracoes.css` | **novo** | Visual da página de Configurações (#4) |
| `assets/css/financeiro.css` | **novo** | Visual da página Financeiro (#5) |
| `includes/topo.php` | alterado | Engrenagem, aviso de troca de usuário, acessibilidade, carregamento das preferências (#4, #5) |
| `includes/rodape.php` | alterado | Links da busca e do sino corrigidos, carrega as máscaras, alerta visual (#4, #5, #6) |
| `pages/busca-registros.php` | alterado | Links dos resultados corrigidos (#5) |
| `pages/cadastro-tutor.php` | alterado | Comentários que apareciam na tela (#9) e máscaras (#6) |
| `pages/cadastro-pet.php` | alterado | Tutor já selecionado vindo da ficha (#10) e máscara do peso (#6) |
| `pages/ficha-tutor.php` | alterado | Botão "Cadastrar novo pet" (#10) |
| `pages/fechamento-diario.php` | alterado | Campos começando em 0 (#7) |
| `assets/css/topo-rodape.css` | alterado | Linha do logo alinhada (#11), hover da agenda (#8), campo da máscara (#6) |
| `assets/css/ficha.css` | alterado | Cor do botão "Cadastrar novo pet" (#10) |
| `assets/css/dashboard-base.css` | alterado | Hover só no card clicável (#8) |
| `assets/dashboard.css` | alterado | Hover da lista de atendimentos removido (#8) |
| `assets/css/fechamento.css` | alterado | Hover de itens não clicáveis removido (#8) |

O banco de dados (Supabase) **não** foi alterado.

---

## Para quem for mexer no código (ou pedir para uma IA)

- **Máscara num campo novo**: colocar `data-mascara="cpf"`, `"telefone"`, `"cep"`, `"dinheiro"` ou `"decimal"` no `<input>`. As instruções estão no topo do `assets/js/mascaras.js`.
- **Dinheiro e peso**: o campo original continua escondido na página, com o valor no formato `12.34`. O PHP e os cálculos do fechamento não mudaram.
- **Tema e acessibilidade**: ficam salvos no navegador (não no banco). Cada opção vira um atributo na tag `<html>`, e o visual de cada uma está em `assets/css/preferencias.css`.
- **Tema escuro**: a página inteira é invertida por um filtro. Se alguma parte ficar estranha no escuro, inclua o seletor dela na lista "NÃO INVERTER" do `preferencias.css`.
