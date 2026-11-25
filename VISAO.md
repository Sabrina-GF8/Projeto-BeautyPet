# Sistema Beauty Pet — Documento de visão do projeto

Centro Paula Souza · FATEC Antônio Russo · AMS – Análise e Desenvolvimento de Sistemas

Amanda Berardi Ferreira · Bruna Luíza Fortes Marques · Henrique de Oliveira Medeiros · Luiza Lima Cremonezi · Pedro Henrique Ferreira da Silva · Sabrina Franca Costa

São Caetano do Sul, 2026

## 1 Introdução

### 1.1 Objetivo e escopo

Este documento descreve os processos do Beauty Pet que motivaram o desenvolvimento de um sistema web para a equipe e de um aplicativo para os tutores. Orienta a equipe do projeto e os proprietários sobre o que a versão analisada contempla, como verificar sua adequação ao trabalho diário e quais decisões ainda dependem do estabelecimento.

### 1.2 Termos utilizados

Tutor é a pessoa responsável pelo pet e titular da ficha comercial. Solicitação é o pedido de horário enviado pelo aplicativo. Agendamento confirmado é o compromisso aceito pela equipe. Atendimento é o serviço prestado ao animal. Débito é um valor pendente do tutor. Crédito é um saldo disponível a seu favor. Fechamento é a conferência e consolidação dos lançamentos de um período.

### 1.3 Organização e fontes

As seções 2 e 3 apresentam o problema e as pessoas envolvidas. As seções 4 a 6 descrevem a solução, seus recursos e limites. As seções 7 a 10 estabelecem critérios de qualidade, prioridades e requisitos técnicos e documentais. A seção 11 registra atributos dos recursos e próximos passos. A revisão considera a primeira versão do Grupo 3, sua devolutiva docente e a implementação identificada nas referências.

## 2 Posicionamento

### 2.1 Oportunidade de negócio

No levantamento inicial, o Beauty Pet oferecia banho, tosa, creche e hotelzinho e realizava vendas de produtos em menor escala. A equipe registrava informações em planilhas de Excel. O conhecimento necessário para operá-las se concentrava em uma funcionária. Sua ausência dificultava consultar o que havia sido combinado com o tutor e conferir pagamentos.

### 2.2 Declaração do problema

Ao receber um pedido, a equipe precisa identificar o animal, localizar o cadastro do responsável e conferir se há vaga. Depois do serviço, precisa relacionar o atendimento ao valor cobrado, à forma de pagamento e a eventual saldo pendente. Se essas informações ficam dispersas, a funcionária do caixa precisa reconstruir o atendimento para explicar uma cobrança. O tutor também depende da equipe para saber se o horário foi aceito.

### 2.3 Posição do produto

Para o Beauty Pet, a solução conecta recepção, prestação do serviço e caixa por meio de fichas e agendamentos compartilhados. O tutor envia o pedido pelo aplicativo. A equipe decide sobre o horário no painel e registra os valores efetivamente movimentados. Espera-se reduzir a redigitação e facilitar a conferência. A dimensão do ganho depende de medição durante a operação.

## 3 Partes interessadas e usuários

### 3.1 Perfil do estabelecimento

O levantamento descreve dois proprietários e cinco funcionárias. A unidade de análise é o próprio Beauty Pet. Para caracterizar sua reputação, os proprietários podem identificar os motivos pelos quais os clientes retornam e confrontá-los com relatos dos tutores. O material disponível não estabelece um resultado para essa avaliação.

### 3.2 Papéis, necessidades e ambiente

Os proprietários decidem sobre regras de funcionamento, vagas e fechamento do caixa. Precisam acompanhar receitas, despesas e pendências. A recepção consulta fichas e responde aos pedidos em computador, muitas vezes enquanto conversa com o tutor. Quem executa o serviço informa o animal atendido, o serviço realizado e os adicionais para que o caixa registre a cobrança correta.

Os tutores usam o celular para identificar seus pets, solicitar horários e consultar respostas. A equipe acadêmica mantém os dois sistemas e acompanha a homologação. Fornecedores, parceiros e prestadores externos são possíveis interessados indiretos. Seu envolvimento e eventual necessidade de acesso dependem de levantamento com o estabelecimento.

### 3.3 Alternativas e concorrência

A planilha já era conhecida pela equipe, mas exigia conferência manual entre cadastro, agenda e caixa. O papel permite anotar rapidamente, mas dificulta recuperar o histórico. Um sistema pronto exige comparar aderência ao fluxo da loja e custo recorrente. A solução própria permite adaptar a rotina de solicitação e fechamento e exige manutenção contínua.

A comparação comercial é outra questão. Para entender como o Beauty Pet se diferencia, o levantamento com os proprietários deve identificar os estabelecimentos que os tutores também procuram e comparar canais de reserva, confirmação de horários e comunicação de preços. Os documentos analisados não identificam concorrentes locais pelo nome.

## 4 Visão geral do produto

### 4.1 Perspectiva e integração

O painel web usa PHP e acessa o PostgreSQL por PDO. O aplicativo usa React Native, TypeScript e Expo Router e acessa o mesmo projeto Supabase. O cadastro do tutor no aplicativo usa Supabase Auth. Um gatilho vincula a conta à ficha comercial pelo e-mail ou cria uma ficha simplificada. A qualidade do e-mail cadastrado é, portanto, uma dependência para aproveitar fichas existentes.

### 4.2 Condições de operação

A equipe precisa de navegador, conexão com o banco e acesso ao painel. O tutor precisa de dispositivo compatível e conexão para consultar ou enviar dados. O catálogo de serviços e a disponibilidade devem estar preenchidos para que um pedido de banho ou tosa possa percorrer o fluxo completo.

### 4.3 Custo, licenciamento e instalação

O projeto foi desenvolvido em contexto acadêmico. Para implantação, devem ser estimados hospedagem PHP, banco e armazenamento, cópias de segurança, suporte e eventual distribuição do aplicativo. O custo depende de volume, retenção e canal de distribuição. A equipe deve apresentar essa estimativa aos proprietários antes de assumir compromissos de operação.

A pasta mobile contém um arquivo de licença MIT com aviso de autoria da Expo. Essa indicação deve ser lida no contexto dos componentes distribuídos. Uma licença geral para todo o produto e as condições de manutenção e entrega ao cliente precisam ser formalizadas. A instalação requer o esquema PostgreSQL do web e, depois, sua extensão mobile.

## 5 Características do produto

### 5.1 Cadastros e fichas

O painel permite cadastrar, localizar e consultar tutores e pets, associar o animal ao responsável e manter sua foto. O aplicativo permite que o tutor consulte o perfil e cadastre seus animais. A associação entre ficha comercial, conta e pet sustenta a consulta do histórico e a identificação de quem solicitou o serviço.

### 5.2 Agenda e registro de serviços

A administração configura dias, horários e número de vagas da agenda-padrão de banho e tosa. O tutor escolhe pet, serviços e horário disponível. O pedido entra como SOLICITADO, com origem MOBILE. A equipe pode confirmar, recusar, cancelar ou reagendar. O aplicativo permite acompanhar o estado do pedido. A agenda do tutor também exibe eventos pessoais, armazenados separadamente dos agendamentos comerciais.

### 5.3 Caixa, entradas, saídas e saldos

No fechamento diário, a equipe registra serviços, adicionais, pacotes, pagamentos e despesas de caixa. Pode conferir créditos e débitos do tutor e finalizar ou reabrir o dia. O fechamento mensal consolida os registros por competência e permite finalizar ou reabrir o mês. A tela Financeiro reúne entradas por forma de pagamento, despesas, resultado e saldos para consulta.

O tutor pode consultar os gastos com serviços do Beauty Pet, identificando o pet e o serviço no histórico. Também pode registrar despesas pessoais por categoria, como ração comprada fora da loja. Esses lançamentos pessoais não movimentam o caixa do estabelecimento.

### 5.4 Comunicação, acesso e apoio à gestão

O aplicativo mantém notificações internas sobre solicitações e permite marcá-las como lidas. O painel concentra a operação da equipe e registra autoria em operações como a resposta a pedidos. Há páginas de análise e assistente com processamento local em PHP. O aplicativo usa autenticação do Supabase e regras no banco para delimitar o acesso do tutor.

## 6 Restrições e limites do escopo

O cálculo de vagas identificado atende à agenda de banho e tosa. Creche e hotelzinho fazem parte do negócio, mas a versão analisada não demonstra controle especializado de ocupação, diárias, entrada e saída de hóspedes. Estoque e comissões permanecem propostas. Avisos internos já existem. Mensagens automáticas por WhatsApp, e-mail ou push para lembrar horários ficam fora do fluxo documentado.

A solução depende de serviços de rede e de um banco corretamente provisionado. A API PHP e os scripts MySQL guardados na pasta de legado não representam o fluxo atual do aplicativo. A implantação em outros estabelecimentos fica fora do objetivo deste projeto.

## 7 Faixas de qualidade e critérios de aceite

**Desempenho e disponibilidade.** A visão inicial estabelece até três segundos para operações usuais e disponibilidade durante o expediente. Na homologação, medir busca de ficha, consulta de vagas e envio de pedido, registrando dispositivo, rede, volume da base e tempo observado. A meta ainda requer medição.

**Usabilidade.** Solicitar a uma funcionária que localize um pet e responda a um pedido, e a um tutor que identifique se ele foi aceito. Registrar conclusão, necessidade de ajuda e confusão entre os estados solicitado e confirmado.

**Consistência.** Em base de teste, registrar atendimento de R$ 50,00 e pagamento integral. Conferir o valor no dia e no mês. Registrar depois uma despesa pessoal de R$ 20,00 no aplicativo e verificar que o caixa da loja conserva o total anterior.

**Privacidade e recuperação.** Em homologação, verificar com duas contas de teste se cada tutor visualiza somente seus dados. Conferir a recuperação de uma falha de conexão e a restauração de uma cópia do banco antes de adotar o sistema como registro principal.

## 8 Precedência e prioridade

A identificação do tutor e do pet precede a solicitação. O catálogo e a configuração de vagas precedem a escolha do horário. O registro dos serviços e pagamentos alimenta o fechamento diário, que sustenta a conferência mensal. Esses encadeamentos definem a ordem de homologação.

A prioridade operacional é validar cadastro, agenda, resposta ao tutor e caixa. Em seguida, conferir histórico, avisos e análises. Estoque, comissões e gestão especializada de hospedagem dependem de decisão dos proprietários sobre benefício, frequência de uso e custo.

## 9 Outros requisitos do produto

### 9.1 Ambiente e configuração

O web requer PHP 8.1 ou superior, PDO com driver PostgreSQL e configuração da variável DATABASE_URL no servidor. O aplicativo usa a URL pública e a chave pública do projeto Supabase. A senha de conexão do painel deve permanecer no servidor. O tratamento de fotos exige diretório de upload com permissão adequada.

### 9.2 Implantação e manutenção

A extensão PostgreSQL do mobile pressupõe que o esquema web já exista. O repositório analisado não fornece um script PostgreSQL completo para criar essa base do zero. Para reproduzir o ambiente, a equipe deve disponibilizar esquema, catálogos e dados fictícios de teste, documentar sua versão e validar o procedimento em uma base separada.

### 9.3 Padrões de uso

Datas, horários e valores devem permitir conferência pela equipe no contexto brasileiro. As telas precisam identificar o pet, o tutor e o estado do pedido antes da confirmação. O uso de um vocabulário comum entre app e painel deve ser verificado com os usuários. Disponibilidade e desempenho serão avaliados segundo a seção 7.

## 10 Requisitos de documentação

O README deve reunir pré-requisitos, configuração dos ambientes web e mobile, preparação do banco e um teste integrado. As notas de versão devem indicar alterações que afetam os usuários e as dependências de atualização. Os documentos de integração e o design system do repositório apoiam a manutenção.

Para a equipe da loja, o guia de operação deve explicar como confirmar ou recusar pedidos, corrigir lançamentos e conferir saldos. Para o tutor, a ajuda deve esclarecer quando a reserva passa a estar confirmada e como distinguir gastos pessoais dos serviços da loja. Esses materiais e as mensagens da interface devem ser validados durante os testes de uso.

## 11 Atributos dos recursos e próximos passos

### 11.1 Situação, benefício e risco

Cadastros, agenda, solicitações e fechamentos possuem implementação identificada na versão analisada. São prioritários porque uma ficha incorreta compromete o pedido, e um pagamento incorreto altera o saldo. O financeiro exige maior esforço de validação devido às combinações de pagamento, créditos, débitos e reabertura de períodos.

Agenda pessoal, gastos do tutor, notificações internas e análises também possuem implementação. A homologação deve verificar o benefício de cada recurso para seu usuário. Estoque, comissões e módulos específicos de hospedagem permanecem sem compromisso de lançamento. Sua estabilidade e seu esforço só poderão ser estimados depois do levantamento das regras com os proprietários.

### 11.2 Responsáveis e sequência de validação

A equipe acadêmica prepara o ambiente e registra resultados por versão. A recepção valida cadastro e agenda. Os responsáveis pelo caixa conferem lançamentos e fechamentos. Os tutores de teste verificam a compreensão do pedido e da resposta. Cabe aos proprietários aprovar a aderência dos fluxos e priorizar mudanças.

O próximo ciclo deve reproduzir o banco em ambiente de teste e acompanhar um atendimento completo, incluindo pelo menos um reagendamento e um pagamento parcial. Em seguida, a equipe deve comparar o tempo de consulta e o retrabalho com o processo em planilhas, apresentar os custos previstos e registrar com o cliente a decisão sobre a implantação.

## Referências

GRUPO 3. Documento de Visão do Projeto: Sistema Beauty Pet. São Caetano do Sul: FATEC Antônio Russo, 2026. Primeira versão com devolutiva docente.

GRUPO 3. Projeto-BeautyPet. Código-fonte e documentação de integração. GitHub, 2026. Versão analisada: commit 98064d1. Disponível em: https://github.com/Sabrina-GF8/Projeto-BeautyPet/tree/98064d1. Acesso em: 24 nov. 2026.
