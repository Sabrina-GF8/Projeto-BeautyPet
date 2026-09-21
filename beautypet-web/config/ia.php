<?php
/**
 * ARQUIVO: config/ia.php
 * RESPONSABILIDADE: Interpretar o que os algoritmos descobriram e escrever as conclusoes.
 *
 * Este arquivo e a ponte entre a matematica e a tela. Ele:
 *   1. pede a config/analise.php os dados ja organizados;
 *   2. roda sobre eles os algoritmos de config/ia-motor.php;
 *   3. transforma cada padrao encontrado em uma observacao ou sugestao de promocao,
 *      sempre acompanhada dos numeros que sustentam aquela conclusao.
 *
 * A inteligencia esta na descoberta do padrao, feita pelos algoritmos. Este arquivo apenas
 * traduz a descoberta para o portugues, e por isso toda conclusao carrega a sua evidencia:
 * quem le pode conferir de onde saiu cada afirmacao.
 *
 * NAO HA NENHUMA CHAMADA A SERVICO EXTERNO. Todo o processamento acontece dentro do PHP,
 * sem internet, sem cadastro em lugar nenhum e sem nenhum custo.
 */

// Tempo, em minutos, que a ultima analise fica guardada na sessao antes de ser refeita.
const IA_MINUTOS_CACHE = 30;

// A partir de quantos desvios padrao abaixo da media um valor e considerado fora do padrao.
// O valor -1,0 corresponde ao limite comum em controle estatistico de processo.
const IA_LIMITE_ANOMALIA = -1.0;

// Nota de risco a partir da qual um pet entra na lista de contato prioritario.
const IA_RISCO_MINIMO = 60;

/**
 * Reduz um nome completo ao primeiro nome, para nao expor dados pessoais na tela.
 */
function iaPrimeiroNome(?string $nome): string
{
    // Separa o nome pelos espacos e devolve apenas a primeira parte.
    $partes = preg_split('/\s+/', trim((string) $nome));

    // Devolve o primeiro pedaco encontrado ou um texto neutro quando o nome vem vazio.
    return $partes[0] ?? 'Cliente';
}

/**
 * Formata um numero no padrao monetario brasileiro, sem o simbolo da moeda.
 */
function iaNumero(float $valor, int $casas = 2): string
{
    // Aplica a separacao de milhar com ponto e a casa decimal com virgula.
    return number_format($valor, $casas, ',', '.');
}

/**
 * Monta o nome de cada perfil de cliente descoberto pelo agrupamento.
 *
 * Os nomes sao atribuidos pela POSICAO do grupo no ranking de fidelidade, e nao por
 * regra fixa: quem decide quais grupos existem e quem pertence a cada um e o algoritmo.
 */
function iaRotulosDePerfil(int $quantidadeGrupos): array
{
    // Devolve a lista de nomes adequada a quantidade de grupos que o algoritmo formou.
    return match ($quantidadeGrupos) {
        2       => ['Clientes ativos', 'Clientes afastados'],
        3       => ['Clientes fiéis', 'Clientes ocasionais', 'Clientes afastados'],
        default => ['Clientes fiéis', 'Clientes regulares', 'Clientes ocasionais', 'Clientes afastados'],
    };
}

/**
 * Decide em quantos grupos o agrupamento deve dividir a base de tutores.
 * Bases pequenas nao sustentam muitos grupos, e dividir demais produziria perfis sem sentido.
 */
function iaQuantidadeDeGrupos(int $totalClientes): int
{
    // Escolhe a quantidade de grupos proporcional ao tamanho da base.
    if ($totalClientes >= 40) {
        return 4;
    }

    if ($totalClientes >= 15) {
        return 3;
    }

    // Abaixo de seis tutores nem o agrupamento minimo se sustenta.
    return $totalClientes >= 6 ? 2 : 0;
}

/**
 * DESCOBERTA 1: pets em risco de abandono, pelo modelo RFM calibrado pelo ritmo de cada pet.
 * Acrescenta as observacoes e as promocoes correspondentes as listas recebidas por referencia.
 */
function iaDescobrirAbandono(array $pacote, array &$observacoes, array &$promocoes): array
{
    // Executa o modelo de risco sobre a matriz RFM dos pets.
    $resultado = motorScoreAbandono($pacote['rfm_pets']);

    // Sem historico suficiente o modelo nao produz pontuacao.
    if (!$resultado['pets']) {
        return $resultado;
    }

    // Separa apenas os pets cujo risco ultrapassou o limite definido.
    $emRisco = array_values(array_filter(
        $resultado['pets'],
        static fn(array $pet): bool => $pet['risco'] >= IA_RISCO_MINIMO
    ));

    // Sem nenhum pet acima do limite, a noticia e boa e merece ser dita.
    if (!$emRisco) {
        $observacoes[] = [
            'tipo'      => 'positivo',
            'titulo'    => 'Nenhum pet está atrasado além do próprio ritmo',
            'texto'     => 'Todos os pets com histórico estão voltando dentro do intervalo que eles mesmos costumam manter entre uma visita e outra.',
            'evidencia' => sprintf(
                '%d pets analisados · intervalo típico da casa: %s dias',
                count($resultado['pets']),
                iaNumero($resultado['intervalo_tipico'], 1)
            ),
            'algoritmo' => 'Pontuação RFM de abandono',
        ];

        return $resultado;
    }

    // Armazena o caso mais grave, que e o primeiro da lista ordenada por prioridade.
    $critico = $emRisco[0];

    $observacoes[] = [
        'tipo'      => 'atencao',
        'titulo'    => count($emRisco) . ' pets com risco alto de não voltar',
        'texto'     => sprintf(
            'O caso mais urgente é o %s, do tutor %s: ele costumava vir a cada %s dias e está há %d dias sem aparecer, ou seja, %sx o próprio ritmo.',
            iaPrimeiroNome($critico['pet']),
            iaPrimeiroNome($critico['tutor']),
            iaNumero((float) $critico['intervalo_usado'], 0),
            $critico['recencia'],
            iaNumero((float) $critico['atraso_relativo'], 1)
        ),
        'evidencia' => sprintf(
            'Risco %d/100 · atraso %sx · RFM R%d F%d M%d · intervalo típico da casa %s dias',
            $critico['risco'],
            iaNumero((float) $critico['atraso_relativo'], 1),
            $critico['nota_recencia'],
            $critico['nota_frequencia'],
            $critico['nota_valor'],
            iaNumero($resultado['intervalo_tipico'], 1)
        ),
        'algoritmo' => 'Pontuação RFM de abandono',
    ];

    // Monta a lista de contato priorizada, que considera risco e valor juntos.
    $prioritarios = array_slice($emRisco, 0, 3);
    // Reune os nomes dos pets prioritarios para citar na sugestao.
    $nomesPrioritarios = array_map(
        static fn(array $pet): string => iaPrimeiroNome($pet['pet']) . ' (' . $pet['prioridade'] . ')',
        $prioritarios
    );

    $promocoes[] = [
        'titulo'    => 'Campanha de retorno em ordem de prioridade',
        'texto'     => sprintf(
            'Comece o contato por: %s. São os que mais valem o esforço de uma ligação agora.',
            implode(', ', $nomesPrioritarios)
        ),
        'evidencia' => sprintf(
            '%d pets acima de %d de risco · prioridade = risco ponderado pelo percentil de faturamento',
            count($emRisco),
            IA_RISCO_MINIMO
        ),
        'algoritmo' => 'Pontuação RFM de abandono',
    ];

    // Devolve o resultado completo para quem quiser mostrar a tabela inteira.
    return $resultado;
}

/**
 * DESCOBERTA 2: perfis de cliente encontrados pelo agrupamento k-medias.
 */
function iaDescobrirPerfis(array $pacote, array &$observacoes, array &$promocoes): array
{
    // Armazena a matriz de tutores que sera agrupada.
    $clientes = $pacote['rfm_clientes'];
    // Decide em quantos grupos dividir a base.
    $k = iaQuantidadeDeGrupos(count($clientes));

    // Bases muito pequenas nao sustentam agrupamento.
    if ($k < 2) {
        return [];
    }

    // Monta a matriz numerica com as tres dimensoes do modelo RFM.
    $matriz = array_map(
        static fn(array $item): array => [
            (float) $item['recencia'],
            (float) $item['frequencia'],
            (float) $item['valor'],
        ],
        $clientes
    );

    // Executa o agrupamento sobre a matriz padronizada.
    $agrupamento = motorKmeans($matriz, $k);

    // Sem grupos formados nao ha o que interpretar.
    if (!$agrupamento['grupos']) {
        return [];
    }

    // Ordena os grupos do mais fiel para o mais afastado.
    // O criterio combina as duas dimensoes que definem fidelidade: vir muito e vir ha pouco tempo.
    $gruposOrdenados = $agrupamento['grupos'];
    uasort($gruposOrdenados, static function (array $a, array $b): int {
        // Compara primeiro pela frequencia media, que e a dimensao mais forte da fidelidade.
        $comparacao = $b['centro'][1] <=> $a['centro'][1];

        // Em caso de empate, vence o grupo que esteve aqui mais recentemente.
        return $comparacao !== 0 ? $comparacao : ($a['centro'][0] <=> $b['centro'][0]);
    });

    // Atribui a cada grupo o nome correspondente a sua posicao no ranking de fidelidade.
    $rotulos = iaRotulosDePerfil(count($gruposOrdenados));
    $perfis = [];
    $posicao = 0;
    // Percorre os grupos ja ordenados montando a descricao de cada perfil.
    foreach ($gruposOrdenados as $grupo) {
        $perfis[] = [
            'nome'       => $rotulos[$posicao] ?? ('Perfil ' . ($posicao + 1)),
            'tamanho'    => $grupo['tamanho'],
            'recencia'   => $grupo['centro'][0],
            'frequencia' => $grupo['centro'][1],
            'valor'      => $grupo['centro'][2],
        ];
        $posicao++;
    }

    // Monta a descricao de cada perfil para o texto da observacao.
    $descricoes = array_map(
        static fn(array $perfil): string => sprintf(
            '%s (%d tutores, vêm a cada %s dias em média)',
            $perfil['nome'],
            $perfil['tamanho'],
            iaNumero((float) $perfil['recencia'], 0)
        ),
        $perfis
    );

    $observacoes[] = [
        'tipo'      => 'neutro',
        'titulo'    => 'A base se divide em ' . count($perfis) . ' perfis de cliente',
        'texto'     => sprintf(
            'O agrupamento encontrou sozinho estes perfis, sem receber nenhuma classificação pronta: %s. Cada tutor foi colocado no grupo cujo comportamento médio mais se parece com o dele.',
            implode('; ', $descricoes)
        ),
        'evidencia' => sprintf(
            'k-médias com k=%d · %d tutores · convergiu em %d iterações · dimensões: recência, frequência e valor',
            count($perfis),
            count($clientes),
            $agrupamento['iteracoes']
        ),
        'algoritmo' => 'K-médias (agrupamento não supervisionado)',
    ];

    // O ultimo perfil da lista e o mais afastado, e e nele que a acao tem mais efeito.
    $afastados = $perfis[count($perfis) - 1];

    // So vale sugerir acao quando o grupo afastado tem gente dentro e de fato esta distante.
    if ($afastados['tamanho'] > 0 && $afastados['recencia'] > 0) {
        $promocoes[] = [
            'titulo'    => 'Oferta dirigida ao perfil "' . $afastados['nome'] . '"',
            'texto'     => sprintf(
                'Esse grupo reúne %d tutores que vêm a cada %s dias e gastaram em média R$ %s. Uma oferta enviada só para eles evita dar desconto a quem já viria de qualquer jeito.',
                $afastados['tamanho'],
                iaNumero((float) $afastados['recencia'], 0),
                iaNumero((float) $afastados['valor'])
            ),
            'evidencia' => sprintf(
                'Centro do grupo: %s dias de recência · %s visitas · R$ %s acumulados',
                iaNumero((float) $afastados['recencia'], 0),
                iaNumero((float) $afastados['frequencia'], 1),
                iaNumero((float) $afastados['valor'])
            ),
            'algoritmo' => 'K-médias (agrupamento não supervisionado)',
        ];
    }

    // Devolve os perfis encontrados.
    return $perfis;
}

/**
 * DESCOBERTA 3: servicos que costumam ser contratados juntos, pela mineracao de regras.
 */
function iaDescobrirCombos(array $pacote, array &$observacoes, array &$promocoes): array
{
    // Executa a mineracao de regras de associacao sobre as visitas do periodo.
    $mineracao = motorApriori($pacote['transacoes']);

    // Sem regras encontradas nao ha associacao estatistica para relatar.
    if (!$mineracao['regras']) {
        return $mineracao;
    }

    // Separa as regras em que a associacao e mais forte do que o acaso explicaria.
    $reais = array_values(array_filter(
        $mineracao['regras'],
        static fn(array $regra): bool => $regra['lift'] > 1.0
    ));

    // Sem nenhuma regra com lift acima de 1, os servicos sao contratados de forma independente.
    if (!$reais) {
        $observacoes[] = [
            'tipo'      => 'neutro',
            'titulo'    => 'Os serviços são contratados de forma independente',
            'texto'     => 'A mineração não encontrou nenhum par de serviços que apareça junto com frequência maior do que o acaso explicaria. Montar combo agora seria juntar serviços que os clientes já escolhem separadamente.',
            'evidencia' => sprintf('%d visitas analisadas · nenhuma regra com lift acima de 1', $mineracao['transacoes']),
            'algoritmo' => 'Apriori (regras de associação)',
        ];

        return $mineracao;
    }

    // A primeira regra da lista e a de associacao mais forte.
    $melhor = $reais[0];

    $observacoes[] = [
        'tipo'      => 'positivo',
        'titulo'    => sprintf(
            'Quem contrata %s também contrata %s',
            implode(' e ', $melhor['condicao']),
            $melhor['consequencia']
        ),
        'texto'     => sprintf(
            'Em %s%% das visitas que incluíram %s, o cliente também levou %s. A associação é %sx mais forte do que seria por acaso, então não é coincidência: é um comportamento real da clientela.',
            iaNumero($melhor['confianca'] * 100, 0),
            implode(' e ', $melhor['condicao']),
            $melhor['consequencia'],
            iaNumero((float) $melhor['lift'], 2)
        ),
        'evidencia' => sprintf(
            'Suporte %s%% (%d visitas) · confiança %s%% · lift %s · base de %d visitas',
            iaNumero($melhor['suporte'] * 100, 1),
            $melhor['ocorrencias'],
            iaNumero($melhor['confianca'] * 100, 0),
            iaNumero((float) $melhor['lift'], 2),
            $mineracao['transacoes']
        ),
        'algoritmo' => 'Apriori (regras de associação)',
    ];

    $promocoes[] = [
        'titulo'    => 'Combo ' . implode(' + ', $melhor['condicao']) . ' + ' . $melhor['consequencia'],
        'texto'     => sprintf(
            'Esse combo já acontece sozinho em %d visitas. Transformar em pacote com preço fechado não cria um hábito novo: apenas dá desconto em algo que o cliente já faz, em troca de ele fechar o pacote inteiro de uma vez.',
            $melhor['ocorrencias']
        ),
        'evidencia' => sprintf(
            'Confiança %s%% · lift %s · o combo aparece em %s%% de todas as visitas',
            iaNumero($melhor['confianca'] * 100, 0),
            iaNumero((float) $melhor['lift'], 2),
            iaNumero($melhor['suporte'] * 100, 1)
        ),
        'algoritmo' => 'Apriori (regras de associação)',
    ];

    // Devolve a mineracao completa.
    return $mineracao;
}

/**
 * DESCOBERTA 4: dias e horarios anormalmente fracos, pela deteccao de anomalia.
 */
function iaDescobrirOciosidade(array $pacote, array &$observacoes, array &$promocoes): array
{
    // Reune o faturamento de cada dia da semana para comparar um com o outro.
    $porDiaSemana = [];
    // Percorre a serie diaria acumulando os totais de cada dia da semana.
    foreach ($pacote['serie_diaria'] as $dia) {
        // Cria a posicao do dia da semana na primeira vez que ele aparece.
        if (!isset($porDiaSemana[$dia['dia_semana']])) {
            $porDiaSemana[$dia['dia_semana']] = [];
        }

        $porDiaSemana[$dia['dia_semana']][] = $dia['total'];
    }

    // Calcula a media de cada dia da semana, que e o valor comparado pelo z-score.
    $mediasPorDia = [];
    // Percorre os dias da semana calculando a media de cada um.
    foreach ($porDiaSemana as $numeroDia => $totais) {
        $mediasPorDia[analiseNomeDiaSemana($numeroDia)] = motorMedia($totais);
    }

    // Aplica a deteccao de anomalia sobre as medias dos dias da semana.
    $anomaliaDias = motorZScore($mediasPorDia);
    // Aplica a deteccao de anomalia sobre a contagem de atendimentos por hora.
    $anomaliaHoras = motorZScore($pacote['serie_horaria']);

    // Guarda o dia e a hora mais fracos, que sao os primeiros de cada lista ordenada.
    $diaFraco = null;
    $horaFraca = null;

    // Extrai o primeiro dia da lista, que e o de menor z-score.
    foreach ($anomaliaDias['scores'] as $nome => $dados) {
        $diaFraco = ['nome' => $nome] + $dados;
        break;
    }

    // Extrai a primeira hora da lista, que e a de menor z-score.
    foreach ($anomaliaHoras['scores'] as $hora => $dados) {
        $horaFraca = ['hora' => (int) $hora] + $dados;
        break;
    }

    // Relata o dia da semana apenas quando ele esta estatisticamente fora do padrao.
    if ($diaFraco !== null && $diaFraco['z'] <= IA_LIMITE_ANOMALIA) {
        $observacoes[] = [
            'tipo'      => 'atencao',
            'titulo'    => $diaFraco['nome'] . ' está fora do padrão da semana',
            'texto'     => sprintf(
                '%s fatura em média R$ %s por dia, enquanto a média geral da semana é R$ %s. A diferença equivale a %s desvios padrão abaixo do normal, o que significa que não é variação comum: é um dia consistentemente fraco.',
                $diaFraco['nome'],
                iaNumero((float) $diaFraco['valor']),
                iaNumero((float) $anomaliaDias['media']),
                iaNumero(abs((float) $diaFraco['z']), 1)
            ),
            'evidencia' => sprintf(
                'z = %s · média da semana R$ %s · desvio padrão R$ %s',
                iaNumero((float) $diaFraco['z'], 2),
                iaNumero((float) $anomaliaDias['media']),
                iaNumero((float) $anomaliaDias['desvio'])
            ),
            'algoritmo' => 'Detecção de anomalia (z-score)',
        ];
    } elseif ($diaFraco !== null) {
        // Quando nenhum dia foge do padrao, informar isso tambem e uma conclusao util.
        $observacoes[] = [
            'tipo'      => 'positivo',
            'titulo'    => 'Nenhum dia da semana está fora do padrão',
            'texto'     => sprintf(
                'O dia mais fraco é %s, mas ele está a apenas %s desvios da média. A semana é equilibrada, então dar desconto em um dia específico não resolveria nenhum problema real.',
                $diaFraco['nome'],
                iaNumero(abs((float) $diaFraco['z']), 1)
            ),
            'evidencia' => sprintf(
                'Menor z encontrado: %s · limite de anomalia: %s',
                iaNumero((float) $diaFraco['z'], 2),
                iaNumero(IA_LIMITE_ANOMALIA, 1)
            ),
            'algoritmo' => 'Detecção de anomalia (z-score)',
        ];
    }

    // Relata a faixa de horario apenas quando ela esta estatisticamente fora do padrao.
    if ($horaFraca !== null && $horaFraca['z'] <= IA_LIMITE_ANOMALIA) {
        $observacoes[] = [
            'tipo'      => 'neutro',
            'titulo'    => sprintf('Ociosidade concentrada das %02dh às %02dh', $horaFraca['hora'], $horaFraca['hora'] + 1),
            'texto'     => sprintf(
                'Essa faixa recebeu %s atendimentos, contra uma média de %s por faixa de horário. É o espaço da agenda com maior capacidade sobrando.',
                iaNumero((float) $horaFraca['valor'], 0),
                iaNumero((float) $anomaliaHoras['media'], 1)
            ),
            'evidencia' => sprintf(
                'z = %s · média por faixa %s atendimentos · desvio padrão %s',
                iaNumero((float) $horaFraca['z'], 2),
                iaNumero((float) $anomaliaHoras['media'], 1),
                iaNumero((float) $anomaliaHoras['desvio'], 1)
            ),
            'algoritmo' => 'Detecção de anomalia (z-score)',
        ];
    }

    // A promocao so faz sentido quando dia e horario realmente estao fora do padrao.
    if ($diaFraco !== null && $horaFraca !== null
        && $diaFraco['z'] <= IA_LIMITE_ANOMALIA
        && $horaFraca['z'] <= IA_LIMITE_ANOMALIA) {
        $promocoes[] = [
            'titulo'    => sprintf('Desconto de ociosidade: %s, %02dh às %02dh', $diaFraco['nome'], $horaFraca['hora'], $horaFraca['hora'] + 1),
            'texto'     => sprintf(
                '%s é o dia mais parado e a faixa das %02dh é a mais vazia. Um desconto só nesse encontro ocupa uma vaga que hoje ficaria vazia, sem tirar cliente dos horários que já estão cheios.',
                $diaFraco['nome'],
                $horaFraca['hora']
            ),
            'evidencia' => sprintf(
                'Dia: z = %s · Horário: z = %s · ambos abaixo do limite de %s',
                iaNumero((float) $diaFraco['z'], 2),
                iaNumero((float) $horaFraca['z'], 2),
                iaNumero(IA_LIMITE_ANOMALIA, 1)
            ),
            'algoritmo' => 'Detecção de anomalia (z-score)',
        ];
    }

    // Devolve as duas deteccoes para quem quiser detalhar na tela.
    return ['dias' => $anomaliaDias, 'horas' => $anomaliaHoras];
}

/**
 * DESCOBERTA 5: tendencia do faturamento, pela regressao linear sobre a serie mensal.
 */
function iaDescobrirTendencia(array $pacote, array &$observacoes, array &$promocoes): array
{
    // Extrai apenas os valores da serie mensal, que e o que a regressao consome.
    $valores = array_map(
        static fn(array $mes): float => (float) $mes['total'],
        $pacote['serie_mensal']
    );

    // Ajusta a reta que melhor descreve a evolucao do faturamento.
    $tendencia = motorRegressaoLinear($valores);

    // Uma reta que explica menos da metade da variacao nao autoriza nenhuma afirmacao.
    if (!$tendencia['confiavel']) {
        $observacoes[] = [
            'tipo'      => 'neutro',
            'titulo'    => 'O faturamento mensal não segue uma tendência clara',
            'texto'     => 'A reta ajustada aos últimos meses explica menos da metade da variação, então os meses oscilam sem seguir um rumo definido. Não dá para projetar o próximo mês com segurança a partir desses dados.',
            'evidencia' => sprintf(
                'R² = %s (abaixo de 0,500) · %d meses analisados',
                iaNumero((float) $tendencia['r2'], 3),
                count($valores)
            ),
            'algoritmo' => 'Regressão linear (mínimos quadrados)',
        ];

        return $tendencia;
    }

    // Identifica se a reta aponta para cima ou para baixo.
    $subindo = $tendencia['inclinacao'] > 0;

    $observacoes[] = [
        'tipo'      => $subindo ? 'positivo' : 'atencao',
        'titulo'    => $subindo
            ? 'O faturamento vem crescendo mês a mês'
            : 'O faturamento vem caindo mês a mês',
        'texto'     => sprintf(
            'A tendência dos últimos %d meses é de %s R$ %s por mês. Mantido esse ritmo, o próximo mês fecharia em torno de R$ %s.',
            count($valores),
            $subindo ? 'alta de' : 'queda de',
            iaNumero(abs((float) $tendencia['inclinacao'])),
            iaNumero((float) $tendencia['previsao'])
        ),
        'evidencia' => sprintf(
            'R² = %s · inclinação R$ %s por mês · projeção para o próximo mês: R$ %s',
            iaNumero((float) $tendencia['r2'], 3),
            iaNumero((float) $tendencia['inclinacao']),
            iaNumero((float) $tendencia['previsao'])
        ),
        'algoritmo' => 'Regressão linear (mínimos quadrados)',
    ];

    // Uma tendencia de queda confiavel justifica uma acao imediata.
    if (!$subindo) {
        $promocoes[] = [
            'titulo'    => 'Ação de contenção antes do próximo mês',
            'texto'     => sprintf(
                'A queda é consistente, não pontual. Antecipe a campanha de retorno e a oferta dirigida ao perfil afastado ainda neste mês, porque a projeção aponta para R$ %s no próximo se nada mudar.',
                iaNumero((float) $tendencia['previsao'])
            ),
            'evidencia' => sprintf(
                'Queda de R$ %s por mês · R² = %s',
                iaNumero(abs((float) $tendencia['inclinacao'])),
                iaNumero((float) $tendencia['r2'], 3)
            ),
            'algoritmo' => 'Regressão linear (mínimos quadrados)',
        ];
    }

    // Devolve o ajuste completo.
    return $tendencia;
}

/**
 * DESCOBERTA 6: servicos que nao foram procurados nenhuma vez no periodo.
 * Nao usa algoritmo: e uma contagem direta, mas fecha o item do planejamento
 * sobre servicos menos procurados.
 */
function iaDescobrirServicosParados(array $pacote, array &$observacoes): void
{
    // Separa os servicos ativos que ficaram com zero procura na janela analisada.
    $parados = array_values(array_filter(
        $pacote['servicos'],
        static fn(array $servico): bool => (int) $servico['quantidade'] === 0
    ));

    // Sem servicos parados nao ha o que relatar.
    if (!$parados) {
        return;
    }

    // Reune os nomes para citar no texto, limitando a quantidade exibida.
    $nomes = array_map(
        static fn(array $servico): string => $servico['servico'],
        array_slice($parados, 0, 4)
    );

    $observacoes[] = [
        'tipo'      => 'atencao',
        'titulo'    => count($parados) . ' serviços não foram pedidos nenhuma vez',
        'texto'     => sprintf(
            'Nos últimos meses ninguém contratou: %s. Ou o serviço precisa ser divulgado, ou ele já não faz sentido no cardápio do Beauty Pet.',
            implode(', ', $nomes)
        ),
        'evidencia' => sprintf(
            '%d serviços ativos com zero lançamentos em %d dias',
            count($parados),
            $pacote['janela_dias']
        ),
        'algoritmo' => 'Contagem direta',
    ];
}

/**
 * Monta a frase de abertura da analise.
 *
 * A frase fala do que foi encontrado no movimento do Beauty Pet, e nunca de quantos
 * dados foram lidos, de quantos algoritmos rodaram ou do que o sistema conseguiu fazer:
 * isso e assunto de quem programa, nao de quem atende no balcao.
 */
function iaMontarResumo(array $observacoes): string
{
    // Separa os pontos que pedem atencao, porque e por eles que a leitura deve comecar.
    $atencao = array_values(array_filter(
        $observacoes,
        static fn(array $item): bool => ($item['tipo'] ?? '') === 'atencao'
    ));

    // Abre pelo ponto mais importante quando existe algum pedindo atencao.
    if ($atencao) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'O que mais pede atenção agora: ' . lcfirst((string) $atencao[0]['titulo']) . '.';
    }

    // Sem pontos de atencao, abre pela primeira conclusao positiva encontrada.
    if ($observacoes) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return (string) $observacoes[0]['titulo'] . '.';
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return 'Por enquanto não há nada fora do comum no movimento do Beauty Pet.';
}

/**
 * Ponto de entrada usado pelo Dashboard.
 * Executa todos os algoritmos, monta as conclusoes e guarda o resultado na sessao.
 */
function gerarAnaliseInteligente(PDO $pdo, bool $forcarAtualizacao = false): array
{
    // Garante que a sessao esteja aberta para poder guardar o resultado entre as visitas a pagina.
    iniciarSessao();

    // Armazena em "cache" a analise guardada anteriormente, quando houver.
    $cache = $_SESSION['analise_ia'] ?? null;

    // Reaproveita a analise anterior enquanto ela ainda estiver dentro do prazo de validade.
    if (!$forcarAtualizacao && is_array($cache)) {
        // Calcula ha quantos minutos a analise guardada foi gerada.
        $minutos = (time() - ($cache['momento'] ?? 0)) / 60;

        // Verifica esta condicao antes de decidir se reaproveita o resultado guardado.
        if ($minutos < IA_MINUTOS_CACHE) {
            return $cache['analise'];
        }
    }

    // Busca no banco todos os dados que os algoritmos precisam.
    $pacote = montarPacoteAnalise($pdo);

    // Prepara as duas listas que serao preenchidas por cada descoberta.
    $observacoes = [];
    $promocoes = [];

    // Executa os algoritmos, cada um acrescentando o que encontrou nas listas acima.
    iaDescobrirAbandono($pacote, $observacoes, $promocoes);
    iaDescobrirOciosidade($pacote, $observacoes, $promocoes);
    iaDescobrirCombos($pacote, $observacoes, $promocoes);
    iaDescobrirPerfis($pacote, $observacoes, $promocoes);
    iaDescobrirTendencia($pacote, $observacoes, $promocoes);
    iaDescobrirServicosParados($pacote, $observacoes);

    // Garante que a tela nunca fique sem conteudo, sem explicar o porque ao usuario.
    if (!$observacoes) {
        $observacoes[] = [
            'tipo'      => 'neutro',
            'titulo'    => 'Nada para destacar por enquanto',
            'texto'     => 'O movimento das últimas semanas não trouxe nenhum ponto fora do comum.',
            'evidencia' => sprintf('%d visitas registradas na janela de %d dias', count($pacote['transacoes']), $pacote['janela_dias']),
            'algoritmo' => '-',
        ];
    }

    // Garante que a coluna de promocoes tambem tenha sempre algo a dizer.
    if (!$promocoes) {
        $promocoes[] = [
            'titulo'    => 'Nenhuma promoção a recomendar agora',
            'texto'     => 'O movimento do período não aponta uma oferta que valha a pena neste momento.',
            'evidencia' => 'Nenhuma regra acima dos limites de suporte, confiança e anomalia',
            'algoritmo' => '-',
        ];
    }

    // Monta o resultado final no formato esperado pela tela.
    $analise = [
        'resumo'      => iaMontarResumo($observacoes),
        'observacoes' => $observacoes,
        'promocoes'   => $promocoes,
        'origem'      => 'motor-proprio',
        'dados'       => $pacote,
    ];

    // Guarda o resultado na sessao junto com o momento da geracao.
    $_SESSION['analise_ia'] = [
        'momento' => time(),
        'analise' => $analise,
    ];

    // Devolve a analise pronta para ser exibida no Dashboard.
    return $analise;
}
