# Beauty Pet — Sincronia WEB + Mobile no Supabase

## Regra adotada
O banco do WEB continua sendo a fonte de verdade. O mobile usa o mesmo PostgreSQL do Supabase e não cria uma segunda cópia de clientes, pets, serviços ou agendamentos.

## Tutor e autenticação
- A ficha comercial continua na tabela `clientes`.
- O login do aplicativo usa **Supabase Auth**.
- `clientes.auth_user_id` liga a conta autenticada à ficha do tutor.
- Se já existir um cliente com o mesmo e-mail, o gatilho do Supabase vincula a nova conta à ficha existente.
- Se ainda não existir, é criada uma ficha simplificada do tutor.
- A senha fica somente no Supabase Auth; não é gravada em `clientes`.

## Pets
- A fonte é a tabela `pets` do WEB.
- O RLS permite ao tutor autenticado ler e cadastrar somente pets da própria ficha.
- Espécies e raças continuam vindo do catálogo central do WEB.

## Serviços
- O catálogo vem de `servicos` + `categorias_servico`.
- Banho, Banho e Tosa e Tosa Higiênica continuam separados.
- Os IDs enviados pelo agendamento são os IDs reais do cadastro do WEB.

## Agendamento
- O mobile grava nas mesmas tabelas usadas pelo WEB:
  - `agendamentos`
  - `agendamento_itens`
- Solicitações mobile entram com `origem = 'MOBILE'` e `status = 'SOLICITADO'`.
- O WEB continua confirmando, recusando e gerenciando a solicitação.
- A disponibilidade de banho/tosa é calculada por uma função PostgreSQL segura, que retorna somente os horários livres sem expor os agendamentos de outros tutores.

## Agenda do mobile
A tela Agenda é unificada apenas na apresentação:
- eventos pessoais vêm de `eventos_agenda_mobile`;
- solicitações/agendamentos do Beauty Pet vêm de `agendamentos`.
As tabelas continuam separadas no banco.

## Financeiro
### Financeiro pessoal
- `gastos_pessoais_mobile`
- `categorias_gasto_mobile`
- é privado do tutor e não interfere no fechamento do Beauty Pet.

### Gastos Beauty Pet
- a subtela lê `vw_mobile_budget`;
- a view usa o histórico financeiro real criado pelo WEB;
- mostra pet, serviço, data e valor;
- não duplica nenhum lançamento.

## Notificações
- `notificacoes_mobile` pertence ao tutor autenticado.
- mudanças de status de uma solicitação do mobile são transformadas em notificações no app.

## Segurança
- O app usa apenas a chave pública/anon do Supabase.
- A chave `service_role` não deve existir no React Native.
- Row Level Security limita os dados do tutor.
- O WEB pode continuar usando conexão direta PostgreSQL conforme configurado no projeto WEB Supabase.

## Legado XAMPP
A API PHP antiga e os SQLs MySQL ficaram em `LEGADO-XAMPP` somente para referência. O mobile Supabase não depende mais do IP do computador, Apache ou MySQL local.
