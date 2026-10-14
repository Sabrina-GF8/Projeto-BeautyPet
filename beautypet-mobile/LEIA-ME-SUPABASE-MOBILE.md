# Beauty Pet Mobile — Supabase

Esta versão não usa mais `fetch()` para `http://IP-DO-PC/beautypet-api/api.php`.
O aplicativo conversa diretamente com **Supabase Auth + PostgreSQL** usando `@supabase/supabase-js`.

## 1. Banco

Primeiro use o banco PostgreSQL/Supabase do WEB. Depois, no **SQL Editor** do mesmo projeto Supabase, execute:

`supabase/02_mobile_supabase.sql`

Esse arquivo:

- adiciona `auth_user_id` em `clientes`;
- vincula o cadastro do Supabase Auth à ficha do tutor;
- cria gastos pessoais, agenda pessoal e notificações do mobile;
- cria `vw_mobile_budget`, que lê os gastos reais registrados pelo WEB;
- cria políticas RLS para cada tutor enxergar apenas os próprios dados;
- mantém os agendamentos comerciais na mesma tabela `agendamentos` usada pelo WEB.

## 2. Variáveis do aplicativo

Copie `.env.example` para `.env` e preencha:

```env
EXPO_PUBLIC_SUPABASE_URL=https://SEU-PROJETO.supabase.co
EXPO_PUBLIC_SUPABASE_ANON_KEY=SUA_CHAVE_PUBLICA
```

Use somente a chave pública/anon/publishable no app. **Nunca** coloque `service_role` no React Native.

## 3. Dependências

Na pasta do projeto:

```powershell
npm.cmd install @supabase/supabase-js react-native-url-polyfill
npm.cmd install
npx.cmd expo start -c
```

## 4. Confirmação de e-mail

Para testes acadêmicos rápidos, em Supabase > Authentication você pode desativar temporariamente a exigência de confirmação de e-mail. Se ela estiver ativa, o cadastro funciona, mas o tutor precisa confirmar o e-mail antes do primeiro login.

## 5. O que continua sincronizado com o WEB

- cadastro/ficha do tutor;
- pets;
- catálogo de serviços;
- solicitações de agendamento;
- confirmação/recusa feita pelo WEB;
- agenda unificada do mobile;
- histórico de gastos no Beauty Pet;
- notificações de alteração do agendamento.

## 6. Pasta XAMPP

A pasta `xampp/beautypet-api` foi mantida somente como **referência/legado**. Esta versão do mobile não depende dela para funcionar.

## 7. Documentação linha a linha

A pasta `DOCUMENTACAO-LINHA-A-LINHA` contém uma cópia de cada arquivo TypeScript/TSX com explicação ao lado de cada linha. Ela é para estudo e apresentação; a versão que deve ser executada é a pasta `src` normal.
