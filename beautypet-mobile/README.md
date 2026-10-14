# Beauty Pet Mobile

Aplicativo mobile do projeto **Beauty Pet**, desenvolvido em React Native com Expo e Expo Router, com integração ao Supabase.

## Estrutura principal

```text
beautypet-app/
├── assets/                 # imagens e recursos visuais
├── src/
│   ├── app/                # telas e rotas do Expo Router
│   ├── assets/             # imagens usadas pelas telas
│   ├── components/         # componentes reutilizáveis
│   ├── context/            # contextos da aplicação
│   └── lib/                # integração com Supabase e funções de acesso a dados
├── supabase/               # SQL complementar do mobile / políticas RLS
├── .env.example            # modelo das variáveis de ambiente
├── app.json                # configuração do Expo
├── package.json            # dependências e scripts
└── tsconfig.json           # configuração TypeScript
```

## Antes de iniciar

Tenha instalado:

- Node.js
- npm
- Expo / Expo Go ou Android Emulator

Instale as dependências:

```bash
npm install
```

## Configuração do Supabase

Crie um arquivo `.env` na raiz do projeto usando `.env.example` como modelo:

```env
EXPO_PUBLIC_SUPABASE_URL=https://SEU-PROJETO.supabase.co
EXPO_PUBLIC_SUPABASE_ANON_KEY=SUA_CHAVE_PUBLICA_AQUI
```

O arquivo `.env` está no `.gitignore` e **não deve ser enviado ao GitHub**.

Nunca coloque no aplicativo a chave `service_role`, senha do PostgreSQL ou outras credenciais administrativas.

## Executar o projeto

```bash
npx expo start
```

Para abrir diretamente no Android:

```bash
npm run android
```

As telas do projeto ficam em `src/app/`.

## Primeiro envio ao GitHub

Depois de criar um repositório vazio no GitHub, execute na pasta do projeto:

```bash
git init
git add .
git commit -m "Projeto inicial Beauty Pet Mobile"
git branch -M main
git remote add origin URL_DO_REPOSITORIO
git push -u origin main
```

## Segurança

Antes de cada `git push`, confirme que `.env`, senhas, chaves administrativas e dados pessoais reais não foram adicionados ao repositório.

O projeto utiliza a chave pública do Supabase no aplicativo e a proteção dos dados deve ser complementada pelas políticas de **Row Level Security (RLS)** no Supabase.

## Variáveis de ambiente

O arquivo `.env` é local e está bloqueado pelo `.gitignore`. No mobile Expo, configure apenas a URL pública do projeto e a chave pública/anon (ou publishable) do Supabase. **Não coloque `DATABASE_URL`, senha do PostgreSQL ou `service_role` dentro do aplicativo.**

```env
EXPO_PUBLIC_SUPABASE_URL=https://SEU-PROJETO.supabase.co
EXPO_PUBLIC_SUPABASE_ANON_KEY=SUA_CHAVE_PUBLICA
```

Depois de alterar o `.env`, reinicie o Expo com `npx expo start --clear`.
