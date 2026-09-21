<?php
/**
 * ARQUIVO: config/assistente.php
 * RESPONSABILIDADE: Entender a pergunta digitada pela atendente e responder usando os dados do sistema.
 *
 * Este arquivo e o "Assistente" da aba do Dashboard. Ele funciona em tres passos:
 *   1. normaliza o texto da pergunta (tira acento, pontuacao e letra maiuscula);
 *   2. compara o texto com um catalogo de assuntos conhecidos e escolhe o mais provavel;
 *   3. chama a funcao de config/analise.php que responde aquele assunto e escreve a resposta.
 *
 * REGRA DE SEGURANCA IMPORTANTE:
 * o texto digitado NUNCA vira SQL. Ele serve apenas para escolher qual funcao sera chamada.
 * Todas as consultas continuam sendo as mesmas de config/analise.php, ja preparadas e
 * parametrizadas. Nao existe nenhuma montagem de consulta com o que o usuario escreveu.
 *
 * NAO HA NENHUMA CHAMADA A SERVICO EXTERNO. Assim como config/ia.php, tudo acontece dentro
 * do PHP, sem internet, sem cadastro em lugar nenhum e sem nenhum custo.
 *
 * DEPENDENCIAS (carregadas por quem usa este arquivo, na ordem):
 *   config/config.php    - conexao com o Supabase e funcoes de formatacao
 *   config/analise.php   - consultas que respondem cada assunto
 *   config/ia-motor.php  - algoritmos usados pelas perguntas sobre padroes e promocoes
 *   config/ia.php        - interpretacao usada pelas perguntas sobre padroes e promocoes
 */

// Quantidade maxima de linhas que uma resposta em lista mostra de uma vez.
// Mantido baixo de proposito: a atendente precisa de uma resposta curta, nao de um relatorio.
const ASSISTENTE_LIMITE_LISTA = 5;

// Pontos que uma pergunta ganha ao citar o assunto principal de um item do catalogo.
const ASSISTENTE_PESO_ASSUNTO = 10;

// Pontos extras que a pergunta ganha ao citar tambem o periodo (hoje, semana, mes).
const ASSISTENTE_PESO_REFINO = 8;

// Desconto aplicado ao item que espera um periodo, quando a pergunta nao citou periodo nenhum.
const ASSISTENTE_DESCONTO_SEM_REFINO = 5;


/* ============================================================================
 * PREPARACAO DO TEXTO
 * ==========================================================================*/

/**
 * Deixa a pergunta em um formato unico para comparacao: minuscula, sem acento e sem pontuacao.
 * Isso faz "Quanto FATURAMOS hoje?" e "quanto faturamos hoje" chegarem iguais na comparacao.
 */
function assistenteNormalizar(?string $texto): string
{
    // Passa tudo para minusculo respeitando os caracteres acentuados do portugues.
    // A extensao mbstring nem sempre esta ligada no servidor, por isso o sistema
    // confere antes de usar, do mesmo jeito que config/config.php ja faz.
    $texto = function_exists('mb_strtolower')
        ? mb_strtolower(trim((string) $texto), 'UTF-8')
        : strtolower(trim((string) $texto));

    // Troca cada letra acentuada pela sua versao simples, para que o acento nunca atrapalhe.
    $comAcento = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ'];
    $semAcento = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'];
    $texto = str_replace($comAcento, $semAcento, $texto);

    // Troca qualquer pontuacao por espaco, para que "hoje?" e "hoje" sejam a mesma palavra.
    $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);

    // Junta os espacos repetidos em um so, deixando a frase pronta para a comparacao.
    $texto = preg_replace('/\s+/', ' ', (string) $texto);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return trim((string) $texto);
}

/**
 * Conta quantos termos da lista aparecem dentro da pergunta ja normalizada.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function assistenteContarTermos(string $pergunta, array $termos): int
{
    // Armazena em "encontrados" quantos termos da lista existem na pergunta.
    $encontrados = 0;

    // Percorre cada termo da lista e verifica se ele aparece na frase.
    foreach ($termos as $termo) {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($termo !== '' && str_contains($pergunta, $termo)) {
            $encontrados++;
        }
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $encontrados;
}


/* ============================================================================
 * CATALOGO DE ASSUNTOS
 *
 * Cada item descreve um assunto que o assistente sabe responder:
 *   chave   - identificador usado para escolher a funcao que responde;
 *   titulo  - nome curto do assunto, mostrado acima da resposta;
 *   exemplo - pergunta modelo, usada nas sugestoes e na resposta de ajuda;
 *   termos  - palavras que identificam o ASSUNTO da pergunta;
 *   refino  - palavras que identificam o PERIODO, quando o assunto tem mais de um;
 *   padrao  - marca o item escolhido quando a pergunta cita o assunto mas nao o periodo.
 *
 * Para ensinar uma pergunta nova ao assistente basta acrescentar um item aqui
 * e criar o caso correspondente em assistenteResponder().
 * ==========================================================================*/

/**
 * Devolve a lista completa de assuntos que o assistente sabe responder.
 */
function assistenteCatalogo(): array
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return [
        [
            'chave'   => 'faturamento-hoje',
            'titulo'  => 'Faturamento do dia',
            'exemplo' => 'Quanto faturamos hoje?',
            'termos'  => ['faturamento', 'faturou', 'faturamos', 'faturei', 'fatura', 'caixa', 'receita', 'arrecad', 'quanto entrou', 'quanto ganhamos', 'quanto vendemos', 'total do dia'],
            'refino'  => ['hoje', 'hj', 'agora', 'do dia', 'no dia'],
            'padrao'  => true,
        ],
        [
            'chave'   => 'faturamento-semana',
            'titulo'  => 'Faturamento dos últimos 7 dias',
            'exemplo' => 'Como foi o faturamento da semana?',
            'termos'  => ['faturamento', 'faturou', 'faturamos', 'faturei', 'fatura', 'caixa', 'receita', 'arrecad', 'quanto entrou', 'quanto ganhamos', 'quanto vendemos'],
            'refino'  => ['semana', 'ultimos dias', 'ultimos 7', '7 dias', 'sete dias', 'semanal'],
            'padrao'  => false,
        ],
        [
            'chave'   => 'faturamento-mes',
            'titulo'  => 'Faturamento mês a mês',
            'exemplo' => 'E o faturamento do mês?',
            'termos'  => ['faturamento', 'faturou', 'faturamos', 'faturei', 'fatura', 'caixa', 'receita', 'arrecad', 'quanto entrou', 'quanto ganhamos', 'quanto vendemos'],
            'refino'  => ['mes', 'meses', 'mensal', 'ano', 'semestre'],
            'padrao'  => false,
        ],
        [
            'chave'   => 'servicos-do-dia',
            'titulo'  => 'Serviços realizados hoje',
            'exemplo' => 'Quantos banhos e tosas saíram hoje?',
            'termos'  => ['banho', 'tosa', 'quantos pets', 'pets atendidos', 'pets hoje', 'quantos atendimentos', 'quantos animais'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'servico-mais-procurado',
            'titulo'  => 'Serviço mais procurado',
            'exemplo' => 'Qual é o serviço mais procurado?',
            'termos'  => ['servico mais', 'servicos mais', 'mais procurado', 'mais vendido', 'mais pedido', 'mais realizado', 'campeao', 'que mais sai', 'mais sai'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'servico-menos-procurado',
            'titulo'  => 'Serviços parados',
            'exemplo' => 'Qual serviço está encalhado?',
            'termos'  => ['servico menos', 'servicos menos', 'menos procurado', 'menos vendido', 'menos pedido', 'encalhado', 'parado', 'nao sai', 'ninguem pede', 'menos sai'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'pets-sem-retorno',
            'titulo'  => 'Pets sem retornar',
            'exemplo' => 'Quais pets estão sumidos?',
            'termos'  => ['sumido', 'sumidos', 'sem retorno', 'nao voltou', 'nao voltaram', 'nao volta', 'afastado', 'afastados', 'perdemos', 'sem aparecer', 'faz tempo'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'clientes-em-queda',
            'titulo'  => 'Tutores vindo menos',
            'exemplo' => 'Quem está vindo menos que antes?',
            'termos'  => ['vindo menos', 'vem menos', 'reduziu', 'diminuiu', 'caiu a frequencia', 'frequencia', 'menos vezes', 'espacando', 'esfriando'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'dia-fraco',
            'titulo'  => 'Dias da semana mais fracos',
            'exemplo' => 'Qual o dia mais fraco da semana?',
            'termos'  => ['dia mais fraco', 'dia fraco', 'dia mais parado', 'dia parado', 'dia mais vazio', 'pior dia', 'melhor dia', 'dia da semana', 'dias da semana', 'dia mais cheio', 'dia mais movimentado'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'horario-fraco',
            'titulo'  => 'Horários mais vazios',
            'exemplo' => 'Que horário fica vazio?',
            'termos'  => ['horario', 'horarios', 'que horas', 'hora mais', 'hora vazia', 'periodo vazio', 'manha ou tarde', 'janela'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'formas-pagamento',
            'titulo'  => 'Formas de pagamento',
            'exemplo' => 'Como o pessoal está pagando?',
            'termos'  => ['pagamento', 'pagando', 'pagam', 'pix', 'cartao', 'dinheiro', 'forma de pag', 'como pagam'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'agenda-hoje',
            'titulo'  => 'Agenda de hoje',
            'exemplo' => 'O que tem na agenda de hoje?',
            'termos'  => ['agenda', 'agendado', 'agendados', 'marcado', 'marcados', 'quem vem', 'proximos atendimentos', 'atendimentos de hoje'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'agendamentos-pendentes',
            'titulo'  => 'Solicitações em aberto',
            'exemplo' => 'Tem solicitação esperando resposta?',
            'termos'  => ['pendente', 'pendentes', 'solicitacao', 'solicitacoes', 'esperando', 'aguardando', 'em aberto', 'para responder', 'para confirmar'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'promocoes',
            'titulo'  => 'Sugestões de promoção',
            'exemplo' => 'Que promoção eu posso fazer?',
            'termos'  => ['promocao', 'promocoes', 'desconto', 'campanha', 'oferta', 'o que fazer', 'como atrair', 'atrair cliente', 'sugestao', 'sugere'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'padroes',
            'titulo'  => 'Padrões encontrados',
            'exemplo' => 'O que a análise encontrou?',
            'termos'  => ['padrao', 'padroes', 'analise encontrou', 'o que a ia', 'insight', 'descobriu', 'conclusao', 'interpretacao', 'diagnostico'],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            // Os dois assuntos abaixo nascem do que a pergunta cita (um pet, uma data, um horario),
            // e nao de palavras fixas. Por isso a lista de termos fica vazia: quem escolhe estes
            // assuntos e a funcao assistenteRespostaEspecifica(), antes da comparacao com o catalogo.
            // Eles continuam aqui para aparecer nas sugestoes e na resposta de ajuda.
            'chave'   => 'pet-especifico',
            'titulo'  => 'Situação de um pet',
            'exemplo' => 'Tem algum atendimento pendente para a Mel?',
            'termos'  => [],
            'refino'  => [],
            'padrao'  => true,
            // O exemplo depende do nome de um pet desta casa, que pode nao existir.
            // Por isso ele aparece na lista do que da para perguntar, mas nao vira atalho.
            'exemplo_generico' => false,
        ],
        [
            'chave'   => 'atendimentos-por-data',
            'titulo'  => 'Quem veio em um dia',
            'exemplo' => 'Quais cachorros vieram na quinta-feira da semana passada de manhã?',
            'termos'  => [],
            'refino'  => [],
            'padrao'  => true,
        ],
        [
            'chave'   => 'ajuda',
            'titulo'  => 'O que eu sei responder',
            'exemplo' => 'O que posso te perguntar?',
            'termos'  => ['ajuda', 'posso perguntar', 'posso te perguntar', 'voce sabe', 'voce faz', 'voce responde', 'como funciona', 'me ajuda', 'socorro', 'exemplos', 'comandos'],
            'refino'  => [],
            'padrao'  => true,
        ],
    ];
}

/**
 * Compara a pergunta com o catalogo e devolve a chave do assunto mais provavel.
 * Devolve null quando nenhum assunto foi reconhecido, para que o assistente avise em vez de chutar.
 */
function assistenteIdentificarAssunto(string $perguntaNormalizada): ?string
{
    // Guarda a melhor pontuacao encontrada ate o momento e a chave correspondente.
    $melhorPontuacao = 0;
    $melhorChave = null;

    // Percorre cada assunto do catalogo calculando o quanto ele combina com a pergunta.
    foreach (assistenteCatalogo() as $assunto) {
        // Conta quantas palavras do assunto principal aparecem na pergunta.
        $acertosAssunto = assistenteContarTermos($perguntaNormalizada, $assunto['termos']);

        // Sem nenhuma palavra do assunto a pergunta nao fala deste tema, entao ele e descartado.
        if ($acertosAssunto === 0) {
            continue;
        }

        // Comeca a pontuacao pelo peso do assunto reconhecido.
        $pontuacao = $acertosAssunto * ASSISTENTE_PESO_ASSUNTO;

        // Conta quantas palavras de periodo aparecem, quando este assunto espera um periodo.
        $acertosRefino = assistenteContarTermos($perguntaNormalizada, $assunto['refino']);

        // Verifica a condição abaixo antes de executar este bloco.
        if ($acertosRefino > 0) {
            // A pergunta citou o periodo, entao este item ganha a preferencia sobre os irmaos.
            $pontuacao += $acertosRefino * ASSISTENTE_PESO_REFINO;
        } elseif ($assunto['refino'] && !$assunto['padrao']) {
            // O item esperava um periodo que nao foi citado, entao perde para o item padrao.
            $pontuacao -= ASSISTENTE_DESCONTO_SEM_REFINO;
        }

        // Guarda este item quando ele combinou melhor do que todos os anteriores.
        if ($pontuacao > $melhorPontuacao) {
            $melhorPontuacao = $pontuacao;
            $melhorChave = $assunto['chave'];
        }
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $melhorChave;
}

/**
 * Devolve os dados de um assunto do catalogo a partir da sua chave.
 */
function assistenteAssuntoPorChave(string $chave): array
{
    // Percorre o catalogo procurando o assunto pedido.
    foreach (assistenteCatalogo() as $assunto) {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($assunto['chave'] === $chave) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return $assunto;
        }
    }

    // Devolve um assunto vazio quando a chave nao existe, para o codigo seguir sem erro.
    return ['chave' => $chave, 'titulo' => 'Resposta', 'exemplo' => '', 'termos' => [], 'refino' => [], 'padrao' => true];
}

/**
 * Monta uma lista curta de perguntas de exemplo, usada abaixo de cada resposta.
 * Evita repetir a pergunta que acabou de ser respondida.
 */
function assistenteSugestoes(?string $chaveAtual = null, int $quantidade = 4): array
{
    // Prepara a lista que recebera os exemplos escolhidos.
    $sugestoes = [];

    // Percorre o catalogo recolhendo os exemplos de assuntos diferentes do que ja foi respondido.
    foreach (assistenteCatalogo() as $assunto) {
        // Pula o assunto que acabou de ser respondido e o item de ajuda.
        if ($assunto['chave'] === $chaveAtual || $assunto['chave'] === 'ajuda') {
            continue;
        }

        // Pula os exemplos que so funcionam com um nome real desta casa.
        if (($assunto['exemplo_generico'] ?? true) === false) {
            continue;
        }

        $sugestoes[] = $assunto['exemplo'];
    }

    // Embaralha para que a atendente conheca aos poucos tudo o que pode perguntar.
    shuffle($sugestoes);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return array_slice($sugestoes, 0, $quantidade);
}


/* ============================================================================
 * MONTAGEM DAS RESPOSTAS
 *
 * Toda resposta sai no mesmo formato, para que a tela saiba desenhar sem
 * precisar conhecer o assunto:
 *   titulo    - nome curto do assunto
 *   resposta  - a frase principal, que ja responde a pergunta
 *   itens     - lista opcional de linhas com rotulo e valor
 *   sugestoes - proximas perguntas possiveis
 *
 * A resposta fala do que foi perguntado e mais nada. De onde saiu cada numero,
 * qual consulta foi usada e o que o sistema conseguiu ou nao entender sao
 * assuntos do codigo, e por isso ficam nos comentarios, nunca na tela.
 * ==========================================================================*/

/**
 * Padroniza o formato de qualquer resposta do assistente.
 */
function assistenteMontarResposta(string $chave, string $resposta, array $itens): array
{
    // Busca o titulo do assunto respondido no catalogo.
    $assunto = assistenteAssuntoPorChave($chave);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return [
        'chave'     => $chave,
        'titulo'    => $assunto['titulo'],
        'resposta'  => $resposta,
        'itens'     => $itens,
        'sugestoes' => assistenteSugestoes($chave),
    ];
}

/**
 * Reduz um nome completo ao primeiro nome, para nao expor dados pessoais na tela do assistente.
 * Segue a mesma regra ja adotada em config/ia.php.
 */
function assistenteNomeCurto(?string $nome): string
{
    // Separa o nome pelos espacos e devolve apenas a primeira parte.
    $partes = preg_split('/\s+/', trim((string) $nome)) ?: [];

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $partes[0] ?? 'Tutor';
}


/* ============================================================================
 * PONTO DE ENTRADA
 * ==========================================================================*/

/**
 * Recebe a pergunta digitada, descobre o assunto e devolve a resposta pronta para a tela.
 * E a unica funcao que a pagina precisa chamar.
 */
function assistenteResponder(PDO $pdo, string $pergunta): array
{
    // Prepara o texto para comparacao.
    $normalizada = assistenteNormalizar($pergunta);

    // Avisa quando a atendente enviou a pergunta em branco.
    if ($normalizada === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return [
            'chave'     => 'vazio',
            'titulo'    => 'Pergunta em branco',
            'resposta'  => 'Escreva a sua pergunta que eu consulto os dados do Beauty Pet para você.',
            'itens'     => [],
            'sugestoes' => assistenteSugestoes(null),
        ];
    }

    // Antes de comparar com o catalogo, verifica se a pergunta cita algo especifico:
    // o nome de um pet, uma data ou um horario. Quando cita, a resposta e montada sob medida.
    $especifica = assistenteRespostaEspecifica($pdo, $normalizada);

    // Devolve a resposta especifica assim que ela existe.
    if ($especifica !== null) {
        return $especifica;
    }

    // Descobre sobre qual assunto a pergunta fala.
    $chave = assistenteIdentificarAssunto($normalizada);

    // Avisa com honestidade quando nao reconheceu o assunto, em vez de inventar uma resposta.
    if ($chave === null) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return [
            'chave'     => 'nao-entendi',
            'titulo'    => 'Não entendi a pergunta',
            'resposta'  => 'Sobre isso eu não sei responder. Pode me perguntar sobre os pets, a agenda, os serviços, os pagamentos e o faturamento do Beauty Pet.',
            'itens'     => [],
            'sugestoes' => assistenteSugestoes(null, 5),
        ];
    }

    // Guarda a data de hoje, usada pelas perguntas sobre o dia atual.
    $hoje = date('Y-m-d');

    // Encaminha a pergunta para a funcao que sabe responder aquele assunto.
    return match ($chave) {
        'faturamento-hoje'        => assistenteFaturamentoHoje($pdo, $hoje),
        'faturamento-semana'      => assistenteFaturamentoSemana($pdo),
        'faturamento-mes'         => assistenteFaturamentoMes($pdo),
        'servicos-do-dia'         => assistenteServicosDoDia($pdo, $hoje),
        'servico-mais-procurado'  => assistenteServicoMaisProcurado($pdo),
        'servico-menos-procurado' => assistenteServicoMenosProcurado($pdo),
        'pets-sem-retorno'        => assistentePetsSemRetorno($pdo),
        'clientes-em-queda'       => assistenteClientesEmQueda($pdo),
        'dia-fraco'               => assistenteDiaFraco($pdo),
        'horario-fraco'           => assistenteHorarioFraco($pdo),
        'formas-pagamento'        => assistenteFormasPagamento($pdo),
        'agenda-hoje'             => assistenteAgendaHoje($pdo, $hoje),
        'agendamentos-pendentes'  => assistenteAgendamentosPendentes($pdo, $hoje),
        'promocoes'               => assistentePromocoes($pdo),
        'padroes'                 => assistentePadroes($pdo),
        'ajuda'                   => assistenteAjuda(),
        default                   => assistenteAjuda(),
    };
}


/* ============================================================================
 * PERGUNTAS QUE CITAM UM PET, UM DIA OU UM HORARIO
 *
 * O catalogo acima reconhece assuntos por palavras fixas. Este bloco cuida do
 * que muda a cada pergunta: o nome de um pet, uma data ("quinta-feira da semana
 * passada"), uma faixa de horario ("de manha", "as 14h") e a situacao do
 * atendimento ("pendente", "confirmado").
 *
 * REGRA DE SEGURANCA: nenhum pedaco do texto digitado entra em uma consulta.
 * O texto e usado apenas para ESCOLHER valores que o proprio sistema conhece:
 * o id de um pet que veio do banco, uma data calculada em PHP e situacoes de
 * uma lista fixa. Todos chegam ao banco como parametros vinculados.
 * ==========================================================================*/

// Situacoes de agendamento que o sistema entende quando a pergunta cita como esta o atendimento.
const ASSISTENTE_SITUACOES = [
    'SOLICITADO' => ['pendente', 'pendentes', 'em aberto', 'esperando', 'aguardando', 'nao respondido', 'sem resposta', 'solicitado', 'solicitacao'],
    'CONFIRMADO' => ['confirmado', 'confirmada', 'confirmados', 'marcado', 'marcada', 'marcados', 'agendado', 'agendada'],
    'CONCLUIDO'  => ['concluido', 'concluida', 'finalizado', 'finalizada', 'ja veio', 'ja foi', 'atendido', 'atendida'],
    'CANCELADO'  => ['cancelado', 'cancelada', 'cancelados', 'desmarcado', 'desmarcada'],
    'RECUSADO'   => ['recusado', 'recusada', 'negado', 'negada'],
];

// Dias da semana no mesmo numero usado pelo PHP e pelo Postgres (0 = domingo).
const ASSISTENTE_DIAS_SEMANA = [
    'domingo' => 0,
    'segunda' => 1,
    'terca'   => 2,
    'quarta'  => 3,
    'quinta'  => 4,
    'sexta'   => 5,
    'sabado'  => 6,
];

/**
 * Procura, dentro da pergunta, o nome de algum pet cadastrado.
 * A comparacao e feita entre palavras inteiras, para que "Mel" nao seja encontrado em "melhor".
 * Devolve todos os pets que atendem pelo nome citado, porque a casa pode ter pets homonimos.
 */
function assistenteProcurarPets(PDO $pdo, string $perguntaNormalizada): array
{
    // Traz os pets ativos junto com o tutor, para poder diferenciar nomes repetidos.
    $stmt = $pdo->query(
        'SELECT p.id, p.nome, c.nome AS tutor
         FROM pets p
         JOIN clientes c ON c.id = p.cliente_id
         WHERE p.ativo = TRUE
         ORDER BY p.nome'
    );

    // Guarda os pets citados junto com o tamanho do nome reconhecido.
    $encontrados = [];
    $maiorNome = 0;

    // Percorre os pets comparando cada nome com o texto da pergunta.
    foreach ($stmt->fetchAll() as $pet) {
        // Deixa o nome no mesmo formato da pergunta, sem acento e em minusculas.
        $nome = assistenteNormalizar($pet['nome']);

        // Nomes muito curtos sao ignorados porque cabem dentro de palavras comuns.
        if (strlen($nome) < 3) {
            continue;
        }

        // Exige o nome inteiro, entre limites de palavra.
        if (!preg_match('/\b' . preg_quote($nome, '/') . '\b/', $perguntaNormalizada)) {
            continue;
        }

        // Guarda o pet junto com o tamanho do nome reconhecido, usado no desempate.
        $encontrados[] = ['pet' => $pet, 'tamanho' => strlen($nome)];
        $maiorNome = max($maiorNome, strlen($nome));
    }

    // Fica apenas com o nome mais longo encontrado, porque ele e o mais especifico da frase.
    $escolhidos = [];
    // Percorre os pets reconhecidos separando os que tem o nome mais longo.
    foreach ($encontrados as $item) {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($item['tamanho'] === $maiorNome) {
            $escolhidos[] = $item['pet'];
        }
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $escolhidos;
}

/**
 * Transforma uma data em um par pronto para uso: o valor que vai para o banco e o texto da resposta.
 */
function assistenteDescreverData(DateTime $data): array
{
    // Descobre o nome do dia da semana, ja em portugues, para escrever a resposta.
    $nomeDia = analiseNomeDiaSemana((int) $data->format('w'));

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return [
        'data'      => $data->format('Y-m-d'),
        'descricao' => $nomeDia . ', ' . $data->format('d/m/Y'),
    ];
}

/**
 * Descobre de que dia a pergunta fala e devolve a data junto com o jeito de escreve-la na resposta.
 * Entende "hoje", "ontem", "anteontem", "amanha", dias da semana com "passada" ou "que vem",
 * "quinta-feira da semana passada" e datas escritas em numeros.
 */
function assistenteExtrairData(string $perguntaNormalizada): ?array
{
    // Parte sempre do dia de hoje, sem hora, para que as contas de dias fiquem exatas.
    $hoje = new DateTime('today');

    // Percorre os dias da semana procurando algum citado na pergunta.
    foreach (ASSISTENTE_DIAS_SEMANA as $nomeDia => $numeroDia) {
        // Segue para o proximo quando este dia da semana nao aparece na frase.
        if (!str_contains($perguntaNormalizada, $nomeDia)) {
            continue;
        }

        // Guarda a data de hoje para receber o deslocamento calculado abaixo.
        $data = clone $hoje;

        // Descobre quantas semanas para tras a pergunta pediu, quando pediu.
        $semanasAtras = 0;
        // Verifica a condição abaixo antes de executar este bloco.
        if (str_contains($perguntaNormalizada, 'semana retrasada')) {
            $semanasAtras = 2;
        } elseif (str_contains($perguntaNormalizada, 'semana passada')) {
            $semanasAtras = 1;
        }

        // Guarda o numero do dia de hoje, usado nas duas contas abaixo.
        $diaDeHoje = (int) $hoje->format('w');

        // "quinta-feira da semana passada" cai dentro daquela semana, contada de segunda a domingo.
        if ($semanasAtras > 0) {
            // Volta ate a segunda-feira da semana atual.
            $data->modify('-' . ($diaDeHoje === 0 ? 6 : $diaDeHoje - 1) . ' days');

            // Recua a quantidade de semanas pedida e avanca ate o dia escolhido.
            $data->modify('-' . (7 * $semanasAtras) . ' days');
            $data->modify('+' . ($numeroDia === 0 ? 6 : $numeroDia - 1) . ' days');

            // Devolve o resultado calculado para o ponto que chamou esta função.
            return assistenteDescreverData($data);
        }

        // "quinta que vem" olha para a frente; os demais casos olham para tras.
        $paraFrente = str_contains($perguntaNormalizada, 'que vem')
            || str_contains($perguntaNormalizada, 'proxima')
            || str_contains($perguntaNormalizada, 'proximo');

        // "quinta passada" dita numa quinta-feira fala da quinta anterior, e nao do dia de hoje.
        $paraTras = str_contains($perguntaNormalizada, 'passad')
            || str_contains($perguntaNormalizada, 'ultima')
            || str_contains($perguntaNormalizada, 'ultimo');

        // Calcula a distancia em dias entre hoje e o dia da semana citado.
        $diferenca = $numeroDia - $diaDeHoje;

        // Verifica a condição abaixo antes de executar este bloco.
        if ($paraFrente) {
            // Sempre um dia ainda por vir.
            $data->modify('+' . ($diferenca <= 0 ? $diferenca + 7 : $diferenca) . ' days');
        } elseif ($diferenca > 0 || ($diferenca === 0 && $paraTras)) {
            // Volta uma semana quando o dia citado ainda nao chegou, ou quando a pergunta
            // pediu o dia anterior com todas as letras.
            $data->modify(($diferenca - 7) . ' days');
        } else {
            // Fica no dia mais recente que ja passou, que pode ser o proprio dia de hoje.
            $data->modify($diferenca . ' days');
        }

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteDescreverData($data);
    }

    // Trata as palavras que apontam um dia sozinhas, da mais especifica para a mais simples.
    if (str_contains($perguntaNormalizada, 'anteontem')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteDescreverData((clone $hoje)->modify('-2 days'));
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($perguntaNormalizada, 'depois de amanha')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteDescreverData((clone $hoje)->modify('+2 days'));
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($perguntaNormalizada, 'ontem')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteDescreverData((clone $hoje)->modify('-1 day'));
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($perguntaNormalizada, 'amanha')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteDescreverData((clone $hoje)->modify('+1 day'));
    }

    // Le datas escritas em numeros. A pontuacao ja virou espaco na preparacao do texto,
    // entao "12/09" chega como "12 09". A palavra "dia" antes do numero evita confundir com horario.
    if (preg_match('/\bdia (\d{1,2})(?: (\d{1,2}))?(?: (\d{4}))?\b/', $perguntaNormalizada, $partes)
        || preg_match('/\b(\d{1,2}) (\d{1,2}) (\d{4})\b/', $perguntaNormalizada, $partes)) {
        // Completa o mes e o ano com o periodo atual quando a pergunta nao os citou.
        $diaCitado = (int) $partes[1];
        $mesCitado = isset($partes[2]) && $partes[2] !== '' ? (int) $partes[2] : (int) $hoje->format('n');
        $anoCitado = isset($partes[3]) && $partes[3] !== '' ? (int) $partes[3] : (int) $hoje->format('Y');

        // Confere se a data existe de verdade antes de usa-la.
        if (checkdate($mesCitado, $diaCitado, $anoCitado)) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return assistenteDescreverData(new DateTime(sprintf('%04d-%02d-%02d', $anoCitado, $mesCitado, $diaCitado)));
        }
    }

    // "hoje" fica por ultimo para nao atropelar as expressoes acima.
    if (str_contains($perguntaNormalizada, 'hoje') || str_contains($perguntaNormalizada, ' hj')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteDescreverData($hoje);
    }

    // Devolve vazio quando a pergunta nao citou nenhum dia.
    return null;
}

/**
 * Descobre de que horario a pergunta fala e devolve a faixa de horas correspondente.
 * Entende "de manha", "a tarde", "a noite", "as 14h", "14h30", "entre 9 e 12" e "das 9 as 12".
 */
function assistenteExtrairFaixaHoraria(string $perguntaNormalizada): ?array
{
    // Faixa escrita com comeco e fim: "entre 9 e 12", "das 9 as 12", "de 14 as 16".
    if (preg_match('/\b(?:entre|das|de) (\d{1,2})\s*(?:h|horas?)? ?(?:e|as|ate) (\d{1,2})\b/', $perguntaNormalizada, $partes)) {
        // Guarda as duas pontas ja como numero de hora.
        $inicio = (int) $partes[1];
        $fim = (int) $partes[2];

        // Usa a faixa apenas quando as horas existem e estao na ordem certa.
        if ($fim <= 24 && $inicio < $fim) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return [
                'inicio'    => $inicio,
                'fim'       => $fim,
                'descricao' => sprintf('das %dh às %dh', $inicio, $fim),
            ];
        }
    }

    // Horario pontual: "as 14", "as 14 30", "14h", "14h30", "14 horas".
    if (preg_match('/\bas (\d{1,2})(?: (\d{2}))?\b/', $perguntaNormalizada, $partes)
        || preg_match('/\b(\d{1,2}) ?h(?:oras?)? ?(\d{2})?\b/', $perguntaNormalizada, $partes)) {
        // Guarda a hora citada; os minutos nao entram no filtro, que trabalha por hora cheia.
        $hora = (int) $partes[1];

        // Usa a hora apenas quando ela existe no relogio.
        if ($hora <= 23) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return [
                'inicio'    => $hora,
                'fim'       => $hora + 1,
                'descricao' => sprintf('das %dh às %dh', $hora, $hora + 1),
            ];
        }
    }

    // Periodos do dia, na linguagem do balcao.
    if (str_contains($perguntaNormalizada, 'manha')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return ['inicio' => 6, 'fim' => 12, 'descricao' => 'de manhã'];
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($perguntaNormalizada, 'tarde')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return ['inicio' => 12, 'fim' => 18, 'descricao' => 'à tarde'];
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($perguntaNormalizada, 'noite')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return ['inicio' => 18, 'fim' => 24, 'descricao' => 'à noite'];
    }

    // Devolve vazio quando a pergunta nao citou horario.
    return null;
}

/**
 * Descobre se a pergunta fala de uma situacao de atendimento e devolve o codigo correspondente.
 */
function assistenteExtrairSituacao(string $perguntaNormalizada): ?array
{
    // Percorre as situacoes conhecidas procurando alguma citada na pergunta.
    foreach (ASSISTENTE_SITUACOES as $codigo => $termos) {
        // Segue para a proxima quando nenhuma palavra desta situacao aparece.
        if (assistenteContarTermos($perguntaNormalizada, $termos) === 0) {
            continue;
        }

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return [
            'codigo'    => $codigo,
            'descricao' => function_exists('mb_strtolower')
                ? mb_strtolower(nomeStatusAgendamento($codigo), 'UTF-8')
                : strtolower(nomeStatusAgendamento($codigo)),
        ];
    }

    // Devolve vazio quando a pergunta nao citou nenhuma situacao.
    return null;
}

/**
 * Decide se a pergunta pede uma resposta sob medida e, quando pede, ja a monta.
 * Devolve vazio quando a pergunta e de um assunto geral, que o catalogo resolve melhor.
 */
function assistenteRespostaEspecifica(PDO $pdo, string $perguntaNormalizada): ?array
{
    // Descobre o que a pergunta cita de concreto.
    $pets = assistenteProcurarPets($pdo, $perguntaNormalizada);
    $data = assistenteExtrairData($perguntaNormalizada);
    $faixa = assistenteExtrairFaixaHoraria($perguntaNormalizada);
    $situacao = assistenteExtrairSituacao($perguntaNormalizada);

    // Quando a pergunta cita um pet, a resposta e sobre ele.
    if ($pets) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteSituacaoDoPet($pdo, $pets, $situacao, $data);
    }

    // Confere se a pergunta fala de atendimentos, e nao de dinheiro ou de servicos em geral.
    $falaDeAtendimento = assistenteContarTermos($perguntaNormalizada, [
        'cachorro', 'cachorros', 'cachorra', 'pet', 'pets', 'animal', 'animais',
        'veio', 'vieram', 'atendimento', 'atendimentos', 'atendemos', 'agenda', 'agendado',
        'agendados', 'marcado', 'marcados', 'quem', 'clientes',
    ]) > 0;

    // Confere se a pergunta pede nomes, e nao uma contagem.
    $pedeNomes = assistenteContarTermos($perguntaNormalizada, [
        'quais', 'quem', 'lista', 'listar', 'nomes',
    ]) > 0;

    // Responde por dia quando a pergunta fala de atendimento e aponta um dia ou um horario.
    if ($falaDeAtendimento && ($data !== null || $faixa !== null)) {
        // Sem dia citado, a pergunta com horario fala do dia de hoje.
        $dataAlvo = $data ?? assistenteDescreverData(new DateTime('today'));

        // Perguntas sobre hoje so entram aqui quando pedem os nomes ou citam horario, para que
        // "quantos banhos sairam hoje" continue sendo respondida pelo assunto de servicos do dia.
        if ($dataAlvo['data'] === date('Y-m-d') && !$pedeNomes && $faixa === null) {
            // Devolve vazio para que a pergunta siga o caminho normal do catalogo.
            return null;
        }

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteAtendimentosDaData($pdo, $dataAlvo, $faixa, $situacao);
    }

    // Devolve vazio para que a pergunta siga o caminho normal do catalogo.
    return null;
}

/**
 * Responde o que um pet tem marcado. Quando a pergunta cita uma situacao ("pendente")
 * ou um dia, a resposta ja vem recortada por eles.
 */
function assistenteSituacaoDoPet(PDO $pdo, array $pets, ?array $situacao, ?array $data): array
{
    // Com nomes repetidos na base, pergunta de quem se trata em vez de escolher por conta propria.
    if (count($pets) > 1) {
        // Monta uma linha por pet, identificando cada um pelo tutor.
        $itens = [];
        // Percorre os pets homonimos para montar a lista de escolha.
        foreach (array_slice($pets, 0, ASSISTENTE_LIMITE_LISTA) as $petHomonimo) {
            $itens[] = [
                'rotulo' => $petHomonimo['nome'],
                'valor'  => 'tutor ' . assistenteNomeCurto($petHomonimo['tutor']),
            ];
        }

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'pet-especifico',
            sprintf('Tem mais de um pet chamado %s. De qual deles você está falando?', $pets[0]['nome']),
            $itens
        );
    }

    // Trabalha com o unico pet reconhecido na pergunta.
    $pet = $pets[0];

    // Monta as condicoes da consulta conforme os filtros que a pergunta trouxe.
    $condicoes = ['a.pet_id = :pet'];
    $parametros = [':pet' => (int) $pet['id']];

    // A situacao vem de uma lista fixa do sistema, nunca do texto digitado.
    if ($situacao !== null) {
        $condicoes[] = 'a.status = :situacao';
        $parametros[':situacao'] = $situacao['codigo'];
    }

    // A data ja foi calculada em PHP e chega ao banco como parametro.
    if ($data !== null) {
        $condicoes[] = 'a.data_hora_inicio::date = :data';
        $parametros[':data'] = $data['data'];
    }

    // Busca os atendimentos do pet, do mais recente para o mais antigo, com os servicos de cada um.
    $stmt = $pdo->prepare(
        "SELECT
            a.data_hora_inicio,
            a.status,
            COALESCE(itens.servicos, 'Serviço não informado') AS servicos
         FROM agendamentos a
         LEFT JOIN (
            SELECT
                ai.agendamento_id,
                STRING_AGG(s.nome, ' + ' ORDER BY s.nome) AS servicos
            FROM agendamento_itens ai
            JOIN servicos s ON s.id = ai.servico_id
            GROUP BY ai.agendamento_id
         ) itens ON itens.agendamento_id = a.id
         WHERE " . implode(' AND ', $condicoes) . "
         ORDER BY a.data_hora_inicio DESC
         LIMIT 20"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute($parametros);
    // Armazena a coleção "atendimentos" carregada para ser percorrida e exibida a seguir.
    $atendimentos = $stmt->fetchAll();

    // Monta os pedacos que descrevem o recorte pedido, para a frase sair natural.
    $comSituacao = $situacao !== null ? ' ' . $situacao['descricao'] : '';
    $comData = $data !== null ? ' em ' . $data['descricao'] : '';

    // Responde com clareza quando nao ha nada marcado dentro do recorte pedido.
    if (!$atendimentos) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'pet-especifico',
            sprintf('%s não tem nenhum atendimento%s%s.', $pet['nome'], $comSituacao, $comData),
            []
        );
    }

    // Monta a lista com a data, o horario, os servicos e a situacao de cada atendimento.
    $itens = [];
    // Percorre os atendimentos transformando cada um em uma linha da resposta.
    foreach (array_slice($atendimentos, 0, ASSISTENTE_LIMITE_LISTA) as $atendimento) {
        $itens[] = [
            'rotulo' => formatarDataHoraAgenda($atendimento['data_hora_inicio']) . ' · ' . $atendimento['servicos'],
            'valor'  => nomeStatusAgendamento($atendimento['status']),
        ];
    }

    // Escreve a frase principal a partir do atendimento mais recente.
    $primeiro = $atendimentos[0];

    // Escreve a quantidade do jeito que se fala, sem o "1" solto na frente da frase.
    $quantidade = count($atendimentos) === 1
        ? 'um atendimento'
        : count($atendimentos) . ' atendimentos';

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'pet-especifico',
        sprintf(
            '%s tem %s%s%s. O mais recente é %s, de %s.',
            $pet['nome'],
            $quantidade,
            $comSituacao,
            $comData,
            formatarDataHoraAgenda($primeiro['data_hora_inicio']),
            $primeiro['servicos']
        ),
        $itens
    );
}

/**
 * Responde quais pets estiveram no Beauty Pet em um dia, com a opcao de recortar por horario.
 */
function assistenteAtendimentosDaData(PDO $pdo, array $data, ?array $faixa, ?array $situacao): array
{
    // Monta as condicoes da consulta conforme os filtros que a pergunta trouxe.
    $condicoes = ['a.data_hora_inicio::date = :data'];
    $parametros = [':data' => $data['data']];

    // Com uma situacao citada, a resposta olha so para ela; sem citar, mostra quem realmente veio.
    if ($situacao !== null) {
        $condicoes[] = 'a.status = :situacao';
        $parametros[':situacao'] = $situacao['codigo'];
    } else {
        $condicoes[] = "a.status NOT IN ('RECUSADO', 'CANCELADO')";
    }

    // A faixa de horario entra como dois numeros calculados em PHP.
    if ($faixa !== null) {
        $condicoes[] = 'EXTRACT(HOUR FROM a.data_hora_inicio) >= :horaInicio';
        $condicoes[] = 'EXTRACT(HOUR FROM a.data_hora_inicio) < :horaFim';
        $parametros[':horaInicio'] = $faixa['inicio'];
        $parametros[':horaFim'] = $faixa['fim'];
    }

    // Busca os pets do dia na ordem em que foram atendidos.
    $stmt = $pdo->prepare(
        "SELECT
            p.nome AS pet,
            c.nome AS tutor,
            a.data_hora_inicio,
            a.status,
            COALESCE(itens.servicos, 'Serviço não informado') AS servicos
         FROM agendamentos a
         JOIN pets p ON p.id = a.pet_id
         JOIN clientes c ON c.id = p.cliente_id
         LEFT JOIN (
            SELECT
                ai.agendamento_id,
                STRING_AGG(s.nome, ' + ' ORDER BY s.nome) AS servicos
            FROM agendamento_itens ai
            JOIN servicos s ON s.id = ai.servico_id
            GROUP BY ai.agendamento_id
         ) itens ON itens.agendamento_id = a.id
         WHERE " . implode(' AND ', $condicoes) . "
         ORDER BY a.data_hora_inicio
         LIMIT 30"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute($parametros);
    // Armazena a coleção "atendimentos" carregada para ser percorrida e exibida a seguir.
    $atendimentos = $stmt->fetchAll();

    // Monta os pedacos que descrevem o recorte pedido, para a frase sair natural.
    $comHorario = $faixa !== null ? ' ' . $faixa['descricao'] : '';
    $comSituacao = $situacao !== null ? ' com atendimento ' . $situacao['descricao'] : '';

    // Responde com clareza quando ninguem passou pelo recorte pedido.
    if (!$atendimentos) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'atendimentos-por-data',
            sprintf('Nenhum pet%s em %s%s.', $comSituacao, $data['descricao'], $comHorario),
            []
        );
    }

    // Monta a lista com o horario, o pet e o servico de cada atendimento.
    $itens = [];
    // Percorre os atendimentos transformando cada um em uma linha da resposta.
    foreach ($atendimentos as $atendimento) {
        $itens[] = [
            'rotulo' => formatarHoraAgenda($atendimento['data_hora_inicio']) . ' · ' . $atendimento['pet']
                . ' (' . assistenteNomeCurto($atendimento['tutor']) . ')',
            'valor'  => $atendimento['servicos'],
        ];
    }

    // Junta os nomes na frase principal, que e o que a pergunta quer saber.
    $nomes = array_map(
        static fn(array $linha): string => $linha['pet'],
        array_slice($atendimentos, 0, ASSISTENTE_LIMITE_LISTA)
    );

    // Escreve "e" antes do ultimo nome, do jeito que se fala.
    $listaNomes = count($nomes) > 1
        ? implode(', ', array_slice($nomes, 0, -1)) . ' e ' . end($nomes)
        : $nomes[0];

    // Avisa quantos pets ficaram fora da frase, porque eles continuam na lista abaixo dela.
    if (count($atendimentos) > count($nomes)) {
        $listaNomes .= sprintf(' e mais %d', count($atendimentos) - count($nomes));
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'atendimentos-por-data',
        sprintf('Em %s%s%s: %s.', $data['descricao'], $comHorario, $comSituacao, $listaNomes),
        $itens
    );
}

/* ============================================================================
 * RESPOSTAS SOBRE DINHEIRO
 * ==========================================================================*/

/**
 * Responde quanto o Beauty Pet faturou no dia de hoje.
 */
function assistenteFaturamentoHoje(PDO $pdo, string $hoje): array
{
    // Pede os indicadores do dia, os mesmos que alimentam os cards do topo do Dashboard.
    $resumo = resumoDoDia($pdo, $hoje);

    // Escreve a frase principal conforme houve ou nao movimento no dia.
    $texto = $resumo['faturamento'] > 0
        ? sprintf(
            'Hoje o Beauty Pet já faturou %s, com %d pet(s) atendido(s).',
            formatarMoeda($resumo['faturamento']),
            $resumo['pets_atendidos']
        )
        : 'Ainda não há faturamento lançado para hoje. Assim que o fechamento do dia for registrado, o valor aparece aqui.';

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'faturamento-hoje',
        $texto,
        [
            ['rotulo' => 'Faturamento', 'valor' => formatarMoeda($resumo['faturamento'])],
            ['rotulo' => 'Pets atendidos', 'valor' => (string) $resumo['pets_atendidos']],
            ['rotulo' => 'Banhos', 'valor' => (string) $resumo['banhos']],
            ['rotulo' => 'Tosas', 'valor' => (string) $resumo['tosas']],
        ]
    );
}

/**
 * Responde como foi o faturamento nos ultimos sete dias.
 */
function assistenteFaturamentoSemana(PDO $pdo): array
{
    // Pede a mesma serie que alimenta o grafico de barras do Dashboard.
    $serie = faturamentoUltimosDias($pdo, 7);

    // Soma os sete dias para dar o total da semana.
    $total = array_sum(array_column($serie, 'total'));

    // Descobre o melhor dia da semana para citar na resposta.
    $melhor = $serie ? $serie[0] : null;
    // Percorre a serie comparando cada dia com o melhor encontrado ate aqui.
    foreach ($serie as $dia) {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($melhor !== null && $dia['total'] > $melhor['total']) {
            $melhor = $dia;
        }
    }

    // Escreve a frase principal com o total e o destaque da semana.
    $texto = $total > 0
        ? sprintf(
            'Nos últimos 7 dias o Beauty Pet faturou %s, uma média de %s por dia. O melhor dia foi %s, com %s.',
            formatarMoeda($total),
            formatarMoeda($total / 7),
            $melhor['rotulo'] ?? '-',
            formatarMoeda($melhor['total'] ?? 0)
        )
        : 'Não há faturamento registrado nos últimos 7 dias.';

    // Monta a lista dia a dia para quem quiser conferir.
    $itens = [];
    // Percorre a serie transformando cada dia em uma linha da resposta.
    foreach ($serie as $dia) {
        $itens[] = ['rotulo' => $dia['rotulo'], 'valor' => formatarMoeda($dia['total'])];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'faturamento-semana',
        $texto,
        $itens
    );
}

/**
 * Responde como o faturamento vem evoluindo mes a mes.
 */
function assistenteFaturamentoMes(PDO $pdo): array
{
    // Pede a mesma serie que alimenta o grafico de linha do Dashboard.
    $serie = evolucaoMensal($pdo, 6);

    // Separa o mes atual e o anterior para poder comparar os dois.
    $mesAtual = $serie ? end($serie) : null;
    $mesAnterior = count($serie) > 1 ? $serie[count($serie) - 2] : null;

    // Escreve a frase principal comparando o mes corrente com o anterior.
    if ($mesAtual === null) {
        $texto = 'Ainda não há faturamento registrado nos últimos meses.';
    } elseif ($mesAnterior !== null && $mesAnterior['total'] > 0) {
        // Calcula a variacao percentual entre os dois meses.
        $variacao = (($mesAtual['total'] - $mesAnterior['total']) / $mesAnterior['total']) * 100;

        $texto = sprintf(
            'Em %s o faturamento está em %s. Em %s foi %s, ou seja, %s de %.1f%%.',
            $mesAtual['rotulo'],
            formatarMoeda($mesAtual['total']),
            $mesAnterior['rotulo'],
            formatarMoeda($mesAnterior['total']),
            $variacao >= 0 ? 'uma alta' : 'uma queda',
            abs($variacao)
        );
    } else {
        $texto = sprintf(
            'Em %s o faturamento está em %s. Ainda não há mês anterior com movimento para comparar.',
            $mesAtual['rotulo'],
            formatarMoeda($mesAtual['total'])
        );
    }

    // Monta a lista mes a mes.
    $itens = [];
    // Percorre a serie transformando cada mes em uma linha da resposta.
    foreach ($serie as $mes) {
        $itens[] = ['rotulo' => $mes['rotulo'], 'valor' => formatarMoeda($mes['total'])];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'faturamento-mes',
        $texto,
        $itens
    );
}


/* ============================================================================
 * RESPOSTAS SOBRE SERVICOS
 * ==========================================================================*/

/**
 * Responde quantos banhos, tosas e pets passaram pelo Beauty Pet hoje.
 */
function assistenteServicosDoDia(PDO $pdo, string $hoje): array
{
    // Pede os indicadores do dia.
    $resumo = resumoDoDia($pdo, $hoje);

    // Escreve a frase principal com as contagens do dia.
    $texto = ($resumo['banhos'] + $resumo['tosas']) > 0
        ? sprintf(
            'Hoje saíram %d banho(s) e %d tosa(s), atendendo %d pet(s) no total.',
            $resumo['banhos'],
            $resumo['tosas'],
            $resumo['pets_atendidos']
        )
        : 'Ainda não há banho nem tosa lançado no fechamento de hoje.';

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'servicos-do-dia',
        $texto,
        [
            ['rotulo' => 'Banhos', 'valor' => (string) $resumo['banhos']],
            ['rotulo' => 'Tosas', 'valor' => (string) $resumo['tosas']],
            ['rotulo' => 'Pets atendidos', 'valor' => (string) $resumo['pets_atendidos']],
        ]
    );
}

/**
 * Responde qual servico foi o mais procurado na janela padrao de analise.
 */
function assistenteServicoMaisProcurado(PDO $pdo): array
{
    // Pede a lista completa de servicos, ordenada do mais para o menos procurado.
    $servicos = servicosMaisEMenosProcurados($pdo);

    // Avisa quando ainda nao ha servico lancado no periodo.
    if (!$servicos) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'servico-mais-procurado',
            'Ainda não há serviços lançados no período analisado.',
            []
        );
    }

    // Guarda o primeiro da lista, que e o mais procurado.
    $campeao = $servicos[0];

    // Monta a lista com os primeiros colocados.
    $itens = [];
    // Percorre os primeiros servicos transformando cada um em uma linha da resposta.
    foreach (array_slice($servicos, 0, ASSISTENTE_LIMITE_LISTA) as $servico) {
        $itens[] = [
            'rotulo' => $servico['servico'],
            'valor'  => (int) $servico['quantidade'] . 'x · ' . formatarMoeda($servico['faturamento']),
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'servico-mais-procurado',
        sprintf(
            'O serviço mais procurado é %s, com %d realização(ões) nos últimos %d dias, somando %s.',
            $campeao['servico'],
            (int) $campeao['quantidade'],
            ANALISE_JANELA_PADRAO,
            formatarMoeda($campeao['faturamento'])
        ),
        $itens
    );
}

/**
 * Responde quais servicos estao parados, ou seja, no fim do ranking de procura.
 */
function assistenteServicoMenosProcurado(PDO $pdo): array
{
    // Pede a lista completa de servicos ativos.
    $servicos = servicosMaisEMenosProcurados($pdo);

    // Avisa quando ainda nao ha servico cadastrado para comparar.
    if (!$servicos) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'servico-menos-procurado',
            'Ainda não há serviços ativos cadastrados para comparar.',
            []
        );
    }

    // Vira a lista para comecar pelo servico menos procurado.
    $parados = array_slice(array_reverse($servicos), 0, ASSISTENTE_LIMITE_LISTA);

    // Conta quantos servicos ativos nao tiveram nenhum lancamento no periodo.
    $semNenhum = count(array_filter(
        $servicos,
        static fn(array $servico): bool => (int) $servico['quantidade'] === 0
    ));

    // Escreve a frase principal conforme existam ou nao servicos totalmente parados.
    $texto = $semNenhum > 0
        ? sprintf(
            '%d serviço(s) ativo(s) não tiveram nenhum lançamento nos últimos %d dias. O mais parado é %s.',
            $semNenhum,
            ANALISE_JANELA_PADRAO,
            $parados[0]['servico']
        )
        : sprintf(
            'Todos os serviços tiveram procura no período. O menos pedido é %s, com %d realização(ões).',
            $parados[0]['servico'],
            (int) $parados[0]['quantidade']
        );

    // Monta a lista dos servicos com menor procura.
    $itens = [];
    // Percorre os servicos parados transformando cada um em uma linha da resposta.
    foreach ($parados as $servico) {
        $itens[] = [
            'rotulo' => $servico['servico'],
            'valor'  => (int) $servico['quantidade'] . 'x',
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'servico-menos-procurado',
        $texto,
        $itens
    );
}


/* ============================================================================
 * RESPOSTAS SOBRE CLIENTES
 * ==========================================================================*/

/**
 * Responde quais pets estao ha mais tempo sem voltar ao Beauty Pet.
 */
function assistentePetsSemRetorno(PDO $pdo): array
{
    // Pede a lista de pets afastados ha mais tempo do que o limite definido.
    $pets = petsSemRetorno($pdo);

    // Avisa quando nenhum pet esta afastado, que e a melhor noticia possivel.
    if (!$pets) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'pets-sem-retorno',
            sprintf('Nenhum pet está há mais de %d dias sem voltar. A base está em dia.', ANALISE_DIAS_SEM_RETORNO),
            []
        );
    }

    // Monta a lista dos pets afastados, mostrando apenas o primeiro nome do tutor.
    $itens = [];
    // Percorre os pets transformando cada um em uma linha da resposta.
    foreach (array_slice($pets, 0, ASSISTENTE_LIMITE_LISTA) as $pet) {
        $itens[] = [
            'rotulo' => $pet['pet'] . ' (' . assistenteNomeCurto($pet['tutor']) . ')',
            'valor'  => (int) $pet['dias_sem_retorno'] . ' dias',
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'pets-sem-retorno',
        sprintf(
            '%d pet(s) estão há mais de %d dias sem voltar. O mais afastado é %s, do tutor %s, há %d dias.',
            count($pets),
            ANALISE_DIAS_SEM_RETORNO,
            $pets[0]['pet'],
            assistenteNomeCurto($pets[0]['tutor']),
            (int) $pets[0]['dias_sem_retorno']
        ),
        $itens
    );
}

/**
 * Responde quais tutores estao vindo menos do que vinham antes.
 */
function assistenteClientesEmQueda(PDO $pdo): array
{
    // Pede a lista de tutores que reduziram a frequencia de visitas.
    $clientes = clientesQueReduziramFrequencia($pdo);

    // Avisa quando ninguem reduziu a frequencia no periodo.
    if (!$clientes) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'clientes-em-queda',
            'Nenhum tutor reduziu a frequência de visitas no período analisado.',
            []
        );
    }

    // Monta a lista comparando as visitas de antes com as de agora.
    $itens = [];
    // Percorre os tutores transformando cada um em uma linha da resposta.
    foreach (array_slice($clientes, 0, ASSISTENTE_LIMITE_LISTA) as $cliente) {
        $itens[] = [
            'rotulo' => assistenteNomeCurto($cliente['tutor']),
            'valor'  => sprintf('%d → %d visitas', $cliente['visitas_anteriores'], $cliente['visitas_recentes']),
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'clientes-em-queda',
        sprintf(
            '%d tutor(es) estão vindo menos do que vinham. %s é quem mais reduziu: passou de %d para %d visitas.',
            count($clientes),
            assistenteNomeCurto($clientes[0]['tutor']),
            $clientes[0]['visitas_anteriores'],
            $clientes[0]['visitas_recentes']
        ),
        $itens
    );
}


/* ============================================================================
 * RESPOSTAS SOBRE MOVIMENTO
 * ==========================================================================*/

/**
 * Responde qual dia da semana rende menos e qual rende mais.
 */
function assistenteDiaFraco(PDO $pdo): array
{
    // Pede a lista de dias da semana ordenada do menor para o maior faturamento.
    $dias = diasDeMenorMovimento($pdo);

    // Avisa quando ainda nao ha fechamento suficiente para comparar os dias.
    if (!$dias) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'dia-fraco',
            'Ainda não há fechamentos suficientes para comparar os dias da semana.',
            []
        );
    }

    // Guarda o dia mais fraco e o mais forte da lista.
    $maisFraco = $dias[0];
    $maisForte = $dias[count($dias) - 1];

    // Monta a lista com a media de cada dia da semana.
    $itens = [];
    // Percorre os dias transformando cada um em uma linha da resposta.
    foreach ($dias as $dia) {
        $itens[] = [
            'rotulo' => $dia['dia_semana'],
            'valor'  => formatarMoeda($dia['media_por_dia']) . ' / dia',
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'dia-fraco',
        sprintf(
            'O dia mais fraco é %s, com média de %s por dia trabalhado. O mais forte é %s, com %s. É em %s que uma promoção rende mais.',
            $maisFraco['dia_semana'],
            formatarMoeda($maisFraco['media_por_dia']),
            $maisForte['dia_semana'],
            formatarMoeda($maisForte['media_por_dia']),
            $maisFraco['dia_semana']
        ),
        $itens
    );
}

/**
 * Responde quais horarios ficam mais vazios na agenda.
 */
function assistenteHorarioFraco(PDO $pdo): array
{
    // Pede a lista de horarios ordenada do mais vazio para o mais cheio.
    $horarios = horariosDeMenorMovimento($pdo);

    // Avisa quando ainda nao ha agendamento suficiente para comparar os horarios.
    if (!$horarios) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'horario-fraco',
            'Ainda não há agendamentos concluídos suficientes para comparar os horários.',
            []
        );
    }

    // Guarda o horario mais vazio e o mais cheio da lista.
    $maisVazio = $horarios[0];
    $maisCheio = $horarios[count($horarios) - 1];

    // Monta a lista com a contagem de cada faixa de horario.
    $itens = [];
    // Percorre os horarios transformando cada um em uma linha da resposta.
    foreach (array_slice($horarios, 0, ASSISTENTE_LIMITE_LISTA) as $horario) {
        $itens[] = [
            'rotulo' => $horario['faixa'],
            'valor'  => $horario['atendimentos'] . ' atendimento(s)',
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'horario-fraco',
        sprintf(
            'A faixa mais vazia é %s, com apenas %d atendimento(s) nos últimos %d dias. A mais cheia é %s, com %d.',
            $maisVazio['faixa'],
            $maisVazio['atendimentos'],
            ANALISE_JANELA_PADRAO,
            $maisCheio['faixa'],
            $maisCheio['atendimentos']
        ),
        $itens
    );
}

/**
 * Responde como os clientes estao pagando nos ultimos 30 dias.
 */
function assistenteFormasPagamento(PDO $pdo): array
{
    // Pede a distribuicao dos pagamentos por forma de recebimento.
    $pagamentos = formasDePagamento($pdo);

    // Avisa quando nenhum pagamento foi registrado no periodo.
    if (!$pagamentos) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'formas-pagamento',
            'Nenhum pagamento foi registrado nos últimos 30 dias.',
            []
        );
    }

    // Soma tudo para poder calcular a participacao de cada forma.
    $total = array_sum(array_map(
        static fn(array $linha): float => (float) $linha['total'],
        $pagamentos
    ));

    // Monta a lista com o valor e a porcentagem de cada forma de pagamento.
    $itens = [];
    // Percorre as formas de pagamento transformando cada uma em uma linha da resposta.
    foreach ($pagamentos as $pagamento) {
        // Calcula quanto esta forma representa do total recebido no periodo.
        $percentual = $total > 0 ? round(((float) $pagamento['total'] / $total) * 100) : 0;

        $itens[] = [
            'rotulo' => $pagamento['forma'],
            'valor'  => formatarMoeda($pagamento['total']) . ' · ' . $percentual . '%',
        ];
    }

    // Calcula a participacao da forma mais usada para citar na frase principal.
    $percentualLider = $total > 0 ? round(((float) $pagamentos[0]['total'] / $total) * 100) : 0;

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'formas-pagamento',
        sprintf(
            'Nos últimos 30 dias entraram %s. A forma mais usada é %s, com %d%% do total.',
            formatarMoeda($total),
            $pagamentos[0]['forma'],
            $percentualLider
        ),
        $itens
    );
}


/* ============================================================================
 * RESPOSTAS SOBRE A AGENDA
 * ==========================================================================*/

/**
 * Responde o que esta marcado na agenda de hoje.
 */
function assistenteAgendaHoje(PDO $pdo, string $hoje): array
{
    // Pede os atendimentos do dia, com um limite maior do que o usado no card do Dashboard.
    $atendimentos = atendimentosDoDia($pdo, $hoje, 20);

    // Avisa quando o dia esta sem nenhum agendamento.
    if (!$atendimentos) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return assistenteMontarResposta(
            'agenda-hoje',
            'Não há nenhum atendimento agendado para hoje.',
            []
        );
    }

    // Monta a lista com o horario, o pet e a situacao de cada atendimento.
    $itens = [];
    // Percorre os atendimentos transformando cada um em uma linha da resposta.
    foreach (array_slice($atendimentos, 0, ASSISTENTE_LIMITE_LISTA) as $atendimento) {
        $itens[] = [
            'rotulo' => formatarHoraAgenda($atendimento['data_hora_inicio']) . ' · ' . $atendimento['pet'],
            'valor'  => nomeStatusAgendamento($atendimento['status']),
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'agenda-hoje',
        sprintf(
            'Hoje há %d atendimento(s) na agenda. O primeiro é %s, às %s.',
            count($atendimentos),
            $atendimentos[0]['pet'],
            formatarHoraAgenda($atendimentos[0]['data_hora_inicio'])
        ),
        $itens
    );
}

/**
 * Responde quantas solicitacoes de agendamento ainda esperam resposta da equipe.
 */
function assistenteAgendamentosPendentes(PDO $pdo, string $hoje): array
{
    // Pede os indicadores do dia, que ja incluem a contagem de solicitacoes em aberto.
    $resumo = resumoDoDia($pdo, $hoje);

    // Escreve a frase principal conforme haja ou nao solicitacao esperando.
    $texto = $resumo['agendamentos_pendentes'] > 0
        ? sprintf(
            'Há %d solicitação(ões) de agendamento esperando resposta. Elas ficam na tela Agenda, em Solicitações.',
            $resumo['agendamentos_pendentes']
        )
        : 'Não há nenhuma solicitação de agendamento esperando resposta. Tudo respondido.';

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'agendamentos-pendentes',
        $texto,
        [
            ['rotulo' => 'Solicitações em aberto', 'valor' => (string) $resumo['agendamentos_pendentes']],
        ]
    );
}


/* ============================================================================
 * RESPOSTAS QUE USAM A ANALISE INTELIGENTE
 * ==========================================================================*/

/**
 * Responde quais promocoes os algoritmos sugerem para o periodo.
 */
function assistentePromocoes(PDO $pdo): array
{
    // Reaproveita a analise ja gerada para o Dashboard, sem refazer os calculos.
    $analise = gerarAnaliseInteligente($pdo);

    // Guarda as sugestoes encontradas pelos algoritmos.
    $promocoes = $analise['promocoes'] ?? [];

    // Monta a lista com o titulo e a explicacao de cada sugestao.
    $itens = [];
    // Percorre as promocoes transformando cada uma em uma linha da resposta.
    foreach (array_slice($promocoes, 0, ASSISTENTE_LIMITE_LISTA) as $promocao) {
        $itens[] = [
            'rotulo' => $promocao['titulo'] ?? '',
            'valor'  => $promocao['texto'] ?? '',
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'promocoes',
        sprintf(
            'Os algoritmos sugerem %d promoção(ões) para o período. Elas também ficam na aba Sugestões da IA, com a evidência de cada uma.',
            count($promocoes)
        ),
        $itens
    );
}

/**
 * Responde o que os algoritmos encontraram nos dados do periodo.
 */
function assistentePadroes(PDO $pdo): array
{
    // Reaproveita a analise ja gerada para o Dashboard, sem refazer os calculos.
    $analise = gerarAnaliseInteligente($pdo);

    // Guarda os padroes encontrados pelos algoritmos.
    $observacoes = $analise['observacoes'] ?? [];

    // Monta a lista com o titulo e a explicacao de cada padrao.
    $itens = [];
    // Percorre as observacoes transformando cada uma em uma linha da resposta.
    foreach (array_slice($observacoes, 0, ASSISTENTE_LIMITE_LISTA) as $observacao) {
        $itens[] = [
            'rotulo' => $observacao['titulo'] ?? '',
            'valor'  => $observacao['texto'] ?? '',
        ];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'padroes',
        $analise['resumo'] ?? 'A análise ainda não encontrou padrões relevantes no período.',
        $itens
    );
}


/* ============================================================================
 * RESPOSTA DE AJUDA
 * ==========================================================================*/

/**
 * Lista tudo o que o assistente sabe responder, com uma pergunta de exemplo de cada assunto.
 */
function assistenteAjuda(): array
{
    // Monta a lista percorrendo o catalogo, para que ela nunca fique desatualizada.
    $itens = [];
    // Percorre o catalogo transformando cada assunto em uma linha da resposta.
    foreach (assistenteCatalogo() as $assunto) {
        // Pula o proprio item de ajuda, que nao precisa aparecer na sua lista.
        if ($assunto['chave'] === 'ajuda') {
            continue;
        }

        $itens[] = ['rotulo' => $assunto['titulo'], 'valor' => $assunto['exemplo']];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return assistenteMontarResposta(
        'ajuda',
        'Eu respondo sobre o que está registrado no Beauty Pet. Pode perguntar com as suas palavras, do jeito que você falaria com uma colega.',
        $itens
    );
}
