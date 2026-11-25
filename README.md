# Beauty Pet

Sistema de atendimento e gestão do Beauty Pet – Centro de Estética Animal. A equipe usa o painel web para organizar fichas, agenda e caixa. Pelo aplicativo, o tutor cadastra seus pets, solicita serviços e acompanha a resposta da loja.

## Funcionalidades

| Módulo | O que permite fazer |
|---|---|
| Web · Tutores e pets | Cadastrar, pesquisar e consultar fichas de tutores e animais. Associar fotos aos pets. |
| Web · Agenda | Consultar atendimentos, definir dias, horários e vagas de banho e tosa e tratar solicitações recebidas pelo aplicativo. |
| Web · Caixa | Registrar serviços, adicionais, pacotes, pagamentos, créditos, débitos e despesas. Finalizar ou reabrir fechamentos diários e mensais. |
| Web · Gestão | Consultar entradas por forma de pagamento, despesas, resultado do mês e saldos de tutores. Acessar análises e assistente local. |
| App · Conta e pets | Criar conta, entrar, consultar perfil e cadastrar pets associados à ficha do tutor. |
| App · Serviços e agenda | Escolher pet e serviços, consultar horários, enviar uma solicitação e acompanhar seu estado. Registrar eventos pessoais na agenda. |
| App · Gastos e avisos | Registrar gastos pessoais, consultar despesas com serviços do Beauty Pet e ler notificações internas. |

## Como os dois sistemas se comunicam

O painel web renderiza as páginas em PHP e acessa o PostgreSQL por PDO. O aplicativo usa React Native, TypeScript e Expo Router. Sua camada de dados acessa o mesmo projeto Supabase por meio de `@supabase/supabase-js`. O login do tutor usa Supabase Auth, com persistência da sessão no dispositivo por AsyncStorage.

O fluxo de agendamento exemplifica a integração:

1. O app consulta o catálogo e os horários disponíveis.
2. Ao enviar o pedido, grava o agendamento e os respectivos serviços em `agendamentos` e `agendamento_itens`, com origem `MOBILE` e status inicial `SOLICITADO`.
3. A equipe responde pelo painel web. O tutor consulta a atualização no app.

A relação entre conta e ficha comercial usa `clientes.auth_user_id`. A extensão SQL inclui funções PostgreSQL, políticas de acesso por linha (RLS) e estruturas específicas do app. Eventos e gastos pessoais ficam em tabelas próprias. A consulta de gastos com serviços da loja usa a visão `vw_mobile_budget`.

## Estrutura do repositório

| Caminho | Conteúdo |
|---|---|
| `DESIGN.md` | Identidade visual e diretrizes de interface. |
| `beautypet-web/index.php` | Entrada do painel web. |
| `beautypet-web/pages/` | Cadastros, fichas, buscas, agenda, solicitações, financeiro, fechamentos e assistente. |
| `beautypet-web/config/` | Carregamento do ambiente, conexão PDO, sessão, funções compartilhadas e motor de análise. |
| `beautypet-web/includes/` | Cabeçalho e rodapé compartilhados. |
| `beautypet-web/assets/` e `beautypet-web/uploads/pets/` | CSS, JavaScript, imagens da interface e fotos de pets. |
| `beautypet-mobile/src/app/` | Rotas e telas ativas do Expo Router. |
| `beautypet-mobile/src/components/` e `src/context/` | Componentes de interface e contextos de feedback e notificações. |
| `beautypet-mobile/src/lib/` | Cliente Supabase em `supabase.ts` e operações da aplicação em `api.ts`. |
| `beautypet-mobile/supabase/02_mobile_supabase.sql` | Extensão PostgreSQL que integra o app ao banco do sistema web. |
| `beautypet-mobile/LEGADO-XAMPP/` | API PHP e scripts MySQL da implementação anterior. |
| `beautypet-mobile/DOCUMENTACAO-LINHA-A-LINHA/` | Cópias comentadas para estudo. As alterações no aplicativo devem ser feitas nos arquivos ativos de `src/`. |

Há também arquivos `.tsx` diretamente em `beautypet-mobile/`. Para trabalhar nas telas em uso, comece por `src/app/`. O ponto de entrada declarado em `package.json` é `expo-router/entry`.

## Pré-requisitos

- Git.
- PHP 8.1 ou superior com `PDO`, `pdo_pgsql`, `mbstring` e `fileinfo`. O código usa o tipo de retorno `never`, funções multibyte e identificação do tipo de arquivo em uploads.
- Node.js e npm compatíveis com o Expo SDK 57 declarado no projeto. O repositório não fixa uma versão de Node.
- Navegador para o painel. Para testar em aparelho, use um cliente Expo compatível com o SDK do projeto.
- Acesso a um projeto Supabase de desenvolvimento com o **esquema PostgreSQL do Beauty Pet web**, os catálogos de referência e um usuário interno para os testes do painel.

**Limite da instalação a partir do zero:** a branch inclui `02_mobile_supabase.sql`, mas não inclui um script PostgreSQL completo para criar a base web vazia. A primeira alteração da extensão já pressupõe a existência de `public.clientes`. Para uma instalação nova, obtenha com a equipe uma cópia de desenvolvimento do esquema web e dos dados iniciais. Os scripts MySQL da pasta de legado pertencem à arquitetura anterior.

## 1. Obter o código

```bash
git clone https://github.com/Sabrina-GF8/Projeto-BeautyPet.git
cd Projeto-BeautyPet
```

Os comandos a seguir partem da raiz do repositório, salvo indicação em contrário.

## 2. Preparar o banco de desenvolvimento

1. Use um projeto Supabase de teste com o esquema web já provisionado.
2. Confira se a base possui os cadastros e catálogos utilizados pelo projeto, incluindo `clientes`, `pets`, `especies`, `racas`, `servicos`, `agendamentos` e `agendamento_itens`.
3. Para habilitar o aplicativo, revise `beautypet-mobile/supabase/02_mobile_supabase.sql` e execute-o no SQL Editor **desse mesmo projeto de teste**. O script altera o banco, inclusive o vínculo entre contas Auth e tutores, funções, gatilhos e políticas de acesso.
4. Disponibilize uma conta interna de teste para o painel, serviços ativos e horários com vagas. O cadastro de tutor pelo aplicativo utiliza uma conta separada do Supabase Auth.

## 3. Configurar e iniciar o painel web

Copie o modelo de configuração:

```bash
cp beautypet-web/config/.env.exemplo beautypet-web/config/.env
```

No PowerShell, o equivalente é:

```powershell
Copy-Item beautypet-web/config/.env.exemplo beautypet-web/config/.env
```

Edite `beautypet-web/config/.env` e informe a URI PostgreSQL do ambiente de teste:

```dotenv
DATABASE_URL=postgresql://USUARIO:SENHA@HOST:5432/NOME_DO_BANCO
```

Use o host, a porta e o usuário fornecidos para a conexão escolhida no Supabase. A URI contém a senha do banco e deve ficar somente no servidor web. O painel utiliza PHP diretamente e dispensa uma etapa de instalação por Composer.

Verifique as extensões carregadas e inicie o servidor local:

```bash
php -m
php -S 127.0.0.1:8000 -t beautypet-web
```

Abra `http://127.0.0.1:8000/`. Para testar fotos, o processo do PHP precisa ter permissão de escrita em `beautypet-web/uploads/pets/`. Em instalações com XAMPP, confira o `php.ini` do PHP que executa o painel, especialmente a extensão `pdo_pgsql`.

## 4. Configurar e iniciar o aplicativo

Em outro terminal, a partir da raiz:

```bash
cd beautypet-mobile
cp .env.example .env
```

No PowerShell, use `Copy-Item .env.example .env`. Preencha o arquivo `.env` com os valores públicos do mesmo projeto Supabase utilizado pelo painel:

```dotenv
EXPO_PUBLIC_SUPABASE_URL=https://SEU-PROJETO.supabase.co
EXPO_PUBLIC_SUPABASE_ANON_KEY=SUA_CHAVE_PUBLICA
```

As variáveis `EXPO_PUBLIC_*` são distribuídas com o aplicativo. Use a chave pública `anon` ou `publishable`. A senha do PostgreSQL e a chave `service_role` são credenciais de servidor.

Instale as dependências e inicie o Expo:

```bash
npm install
npm start
```

Abra o projeto em um aparelho ou emulador compatível. Para testar a interface do aplicativo no navegador, execute `npm run web` dentro de `beautypet-mobile`. Esse comando abre a **versão web do aplicativo do tutor**. O painel administrativo continua disponível no endereço do servidor PHP.

Outros comandos declarados no projeto:

```bash
npm run android
npm run ios
npm run lint
```

Para abrir o simulador iOS local, é necessário um ambiente macOS com Xcode. Se alterar o `.env` e o Expo continuar usando os valores antigos, reinicie com `npx expo start --clear`.

## 5. Testar localmente

Use registros fictícios no banco de desenvolvimento. O `package.json` oferece lint e não declara um script `npm test`. Os cenários abaixo verificam o comportamento da aplicação e a integração entre os dois clientes.

| Cenário | Passos | Resultado esperado |
|---|---|---|
| Cadastro e vínculo | No app, crie um tutor de teste com e-mail exclusivo. Confirme o e-mail se o projeto exigir. Entre, cadastre um pet e procure a ficha no painel. | O pet aparece associado ao tutor correto nos dois sistemas. |
| Solicitação de banho | No web, configure uma vaga futura de banho e tosa. No app, escolha o pet, um serviço disponível e esse horário. Envie a solicitação. | O painel recebe o pedido com o mesmo pet e horário. O estado inicial é `SOLICITADO`. |
| Resposta da equipe | Confirme a solicitação no painel e volte à agenda e às notificações do app. | O tutor consegue consultar a decisão da equipe. |
| Financeiro do estabelecimento | Em uma data de teste, registre no fechamento diário um serviço de R$ 50,00 e o respectivo pagamento. Compare os totais antes e depois no resumo do mesmo mês. | As entradas aumentam R$ 50,00, e o recebimento aparece na forma de pagamento utilizada. |
| Gasto pessoal do tutor | No app, registre R$ 20,00 de ração como despesa pessoal e consulte o resumo do mesmo mês. Confira também o caixa web. | O gasto aparece no controle pessoal. O lançamento não altera o caixa da loja. |

Para verificar a sintaxe de todos os arquivos PHP em um terminal Bash, execute na raiz:

```bash
find beautypet-web -name '*.php' -exec php -l {} \;
```

## Diagnóstico rápido

| Sintoma | O que conferir |
|---|---|
| Painel informa banco não configurado | Verifique se a cópia se chama `beautypet-web/config/.env` e se contém `DATABASE_URL`. |
| PHP informa ausência de driver PostgreSQL | Execute `php -m`, habilite `pdo_pgsql` no PHP em uso e reinicie o servidor. |
| O SQL mobile falha ao acessar `public.clientes` | Confira se o projeto selecionado contém o esquema web de base antes de aplicar a extensão mobile. |
| App abre, mas o pedido não aparece no painel esperado | Confira se a URL pública usada pelo app e a conexão PostgreSQL do painel pertencem ao mesmo projeto Supabase. |
| Nenhum horário aparece para banho e tosa | Confira o serviço selecionado, a data, as vagas da agenda-padrão e a ocupação existente. |

## Documentação do projeto

- [Visão do projeto](VISAO.md)
- [Design system](DESIGN.md)
- [Integração web e mobile](beautypet-mobile/SINCRONIA-WEB-MOBILE.md)
- [Configuração do Supabase para o aplicativo](beautypet-mobile/LEIA-ME-SUPABASE-MOBILE.md)
