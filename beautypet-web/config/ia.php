<?php // Executa esta instrução como parte da regra de negócio desta página.
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
const IA_MINUTOS_CACHE = 30; // Declara uma constante de configuração deste módulo.

// A partir de quantos desvios padrao abaixo da media um valor e considerado fora do padrao.
// O valor -1,0 corresponde ao limite comum em controle estatistico de processo.
const IA_LIMITE_ANOMALIA = -1.0; // Declara uma constante de configuração deste módulo.

// Nota de risco a partir da qual um pet entra na lista de contato prioritario.
const IA_RISCO_MINIMO = 60; // Declara uma constante de configuração deste módulo.

/**
 * Reduz um nome completo ao primeiro nome, para nao expor dados pessoais na tela.
 */
function iaPrimeiroNome(?string $nome): string // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Separa o nome pelos espacos e devolve apenas a primeira parte.
    $partes = preg_split('/\s+/', trim((string) $nome)); // Armazena em partes o valor usado nas próximas etapas deste fluxo.

    // Devolve o primeiro pedaco encontrado ou um texto neutro quando o nome vem vazio.
    return $partes[0] ?? 'Cliente'; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Formata um numero no padrao monetario brasileiro, sem o simbolo da moeda.
 */
function iaNumero(float $valor, int $casas = 2): string // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Aplica a separacao de milhar com ponto e a casa decimal com virgula.
    return number_format($valor, $casas, ',', '.'); // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Monta o nome de cada perfil de cliente descoberto pelo agrupamento.
 *
 * Os nomes sao atribuidos pela POSICAO do grupo no ranking de fidelidade, e nao por
 * regra fixa: quem decide quais grupos existem e quem pertence a cada um e o algoritmo.
 */
function iaRotulosDePerfil(int $quantidadeGrupos): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Devolve a lista de nomes adequada a quantidade de grupos que o algoritmo formou.
    return match ($quantidadeGrupos) { // Devolve este resultado para quem chamou a função.
        2       => ['Clientes ativos', 'Clientes afastados'], // Executa esta instrução como parte da regra de negócio desta página.
        3       => ['Clientes fiéis', 'Clientes ocasionais', 'Clientes afastados'], // Executa esta instrução como parte da regra de negócio desta página.
        default => ['Clientes fiéis', 'Clientes regulares', 'Clientes ocasionais', 'Clientes afastados'], // Executa esta instrução como parte da regra de negócio desta página.
    }; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Decide em quantos grupos o agrupamento deve dividir a base de tutores.
 * Bases pequenas nao sustentam muitos grupos, e dividir demais produziria perfis sem sentido.
 */
function iaQuantidadeDeGrupos(int $totalClientes): int // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Escolhe a quantidade de grupos proporcional ao tamanho da base.
    if ($totalClientes >= 40) { // Testa esta condição antes de executar o bloco correspondente.
        return 4; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    if ($totalClientes >= 15) { // Testa esta condição antes de executar o bloco correspondente.
        return 3; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Abaixo de seis tutores nem o agrupamento minimo se sustenta.
    return $totalClientes >= 6 ? 2 : 0; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * DESCOBERTA 1: pets em risco de abandono, pelo modelo RFM calibrado pelo ritmo de cada pet.
 * Acrescenta as observacoes e as promocoes correspondentes as listas recebidas por referencia.
 */
function iaDescobrirAbandono(array $pacote, array &$observacoes, array &$promocoes): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Executa o modelo de risco sobre a matriz RFM dos pets.
    $resultado = motorScoreAbandono($pacote['rfm_pets']); // Armazena em resultado o valor usado nas próximas etapas deste fluxo.

    // Sem historico suficiente o modelo nao produz pontuacao.
    if (!$resultado['pets']) { // Testa esta condição antes de executar o bloco correspondente.
        return $resultado; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Separa apenas os pets cujo risco ultrapassou o limite definido.
    $emRisco = array_values(array_filter( // Armazena em emRisco o valor usado nas próximas etapas deste fluxo.
        $resultado['pets'], // Executa esta instrução como parte da regra de negócio desta página.
        static fn(array $pet): bool => $pet['risco'] >= IA_RISCO_MINIMO // Executa esta instrução como parte da regra de negócio desta página.
    )); // Executa esta instrução como parte da regra de negócio desta página.

    // Sem nenhum pet acima do limite, a noticia e boa e merece ser dita.
    if (!$emRisco) { // Testa esta condição antes de executar o bloco correspondente.
        $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'tipo'      => 'positivo', // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => 'Nenhum pet está atrasado além do próprio ritmo', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => 'Todos os pets com histórico estão voltando dentro do intervalo que eles mesmos costumam manter entre uma visita e outra.', // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                '%d pets analisados · intervalo típico da casa: %s dias', // Executa esta instrução como parte da regra de negócio desta página.
                count($resultado['pets']), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero($resultado['intervalo_tipico'], 1) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'Pontuação RFM de abandono', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        return $resultado; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Armazena o caso mais grave, que e o primeiro da lista ordenada por prioridade.
    $critico = $emRisco[0]; // Armazena em critico o valor usado nas próximas etapas deste fluxo.

    $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
        'tipo'      => 'atencao', // Executa esta instrução como parte da regra de negócio desta página.
        'titulo'    => count($emRisco) . ' pets com risco alto de não voltar', // Executa esta instrução como parte da regra de negócio desta página.
        'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'O caso mais urgente é o %s, do tutor %s: ele costumava vir a cada %s dias e está há %d dias sem aparecer, ou seja, %sx o próprio ritmo. O risco não é medido por um prazo fixo, e sim comparando cada pet com o histórico dele mesmo.', // Executa esta instrução como parte da regra de negócio desta página.
            iaPrimeiroNome($critico['pet']), // Executa esta instrução como parte da regra de negócio desta página.
            iaPrimeiroNome($critico['tutor']), // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $critico['intervalo_usado'], 0), // Executa esta instrução como parte da regra de negócio desta página.
            $critico['recencia'], // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $critico['atraso_relativo'], 1) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'Risco %d/100 · atraso %sx · RFM R%d F%d M%d · intervalo típico da casa %s dias', // Executa esta instrução como parte da regra de negócio desta página.
            $critico['risco'], // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $critico['atraso_relativo'], 1), // Executa esta instrução como parte da regra de negócio desta página.
            $critico['nota_recencia'], // Executa esta instrução como parte da regra de negócio desta página.
            $critico['nota_frequencia'], // Executa esta instrução como parte da regra de negócio desta página.
            $critico['nota_valor'], // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero($resultado['intervalo_tipico'], 1) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'algoritmo' => 'Pontuação RFM de abandono', // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Monta a lista de contato priorizada, que considera risco e valor juntos.
    $prioritarios = array_slice($emRisco, 0, 3); // Armazena em prioritarios o valor usado nas próximas etapas deste fluxo.
    // Reune os nomes dos pets prioritarios para citar na sugestao.
    $nomesPrioritarios = array_map( // Armazena em nomesPrioritarios o valor usado nas próximas etapas deste fluxo.
        static fn(array $pet): string => iaPrimeiroNome($pet['pet']) . ' (' . $pet['prioridade'] . ')', // Executa esta instrução como parte da regra de negócio desta página.
        $prioritarios // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    $promocoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
        'titulo'    => 'Campanha de retorno em ordem de prioridade', // Executa esta instrução como parte da regra de negócio desta página.
        'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'Comece o contato por: %s. A ordem não é pelo tempo sem vir, e sim pela combinação de risco com o quanto cada pet já gerou de faturamento, para o esforço render mais.', // Executa esta instrução como parte da regra de negócio desta página.
            implode(', ', $nomesPrioritarios) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            '%d pets acima de %d de risco · prioridade = risco ponderado pelo percentil de faturamento', // Executa esta instrução como parte da regra de negócio desta página.
            count($emRisco), // Executa esta instrução como parte da regra de negócio desta página.
            IA_RISCO_MINIMO // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'algoritmo' => 'Pontuação RFM de abandono', // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve o resultado completo para quem quiser mostrar a tabela inteira.
    return $resultado; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * DESCOBERTA 2: perfis de cliente encontrados pelo agrupamento k-medias.
 */
function iaDescobrirPerfis(array $pacote, array &$observacoes, array &$promocoes): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Armazena a matriz de tutores que sera agrupada.
    $clientes = $pacote['rfm_clientes']; // Armazena em clientes o valor usado nas próximas etapas deste fluxo.
    // Decide em quantos grupos dividir a base.
    $k = iaQuantidadeDeGrupos(count($clientes)); // Armazena em k o valor usado nas próximas etapas deste fluxo.

    // Bases muito pequenas nao sustentam agrupamento.
    if ($k < 2) { // Testa esta condição antes de executar o bloco correspondente.
        return []; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Monta a matriz numerica com as tres dimensoes do modelo RFM.
    $matriz = array_map( // Armazena em matriz o valor usado nas próximas etapas deste fluxo.
        static fn(array $item): array => [ // Executa esta instrução como parte da regra de negócio desta página.
            (float) $item['recencia'], // Executa esta instrução como parte da regra de negócio desta página.
            (float) $item['frequencia'], // Executa esta instrução como parte da regra de negócio desta página.
            (float) $item['valor'], // Executa esta instrução como parte da regra de negócio desta página.
        ], // Executa esta instrução como parte da regra de negócio desta página.
        $clientes // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Executa o agrupamento sobre a matriz padronizada.
    $agrupamento = motorKmeans($matriz, $k); // Armazena em agrupamento o valor usado nas próximas etapas deste fluxo.

    // Sem grupos formados nao ha o que interpretar.
    if (!$agrupamento['grupos']) { // Testa esta condição antes de executar o bloco correspondente.
        return []; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Ordena os grupos do mais fiel para o mais afastado.
    // O criterio combina as duas dimensoes que definem fidelidade: vir muito e vir ha pouco tempo.
    $gruposOrdenados = $agrupamento['grupos']; // Armazena em gruposOrdenados o valor usado nas próximas etapas deste fluxo.
    uasort($gruposOrdenados, static function (array $a, array $b): int { // Executa esta instrução como parte da regra de negócio desta página.
        // Compara primeiro pela frequencia media, que e a dimensao mais forte da fidelidade.
        $comparacao = $b['centro'][1] <=> $a['centro'][1]; // Armazena em comparacao o valor usado nas próximas etapas deste fluxo.

        // Em caso de empate, vence o grupo que esteve aqui mais recentemente.
        return $comparacao !== 0 ? $comparacao : ($a['centro'][0] <=> $b['centro'][0]); // Devolve este resultado para quem chamou a função.
    }); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Atribui a cada grupo o nome correspondente a sua posicao no ranking de fidelidade.
    $rotulos = iaRotulosDePerfil(count($gruposOrdenados)); // Armazena em rotulos o valor usado nas próximas etapas deste fluxo.
    $perfis = []; // Armazena em perfis o valor usado nas próximas etapas deste fluxo.
    $posicao = 0; // Armazena em posicao o valor usado nas próximas etapas deste fluxo.
    // Percorre os grupos ja ordenados montando a descricao de cada perfil.
    foreach ($gruposOrdenados as $grupo) { // Percorre a coleção e processa cada registro individualmente.
        $perfis[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'nome'       => $rotulos[$posicao] ?? ('Perfil ' . ($posicao + 1)), // Executa esta instrução como parte da regra de negócio desta página.
            'tamanho'    => $grupo['tamanho'], // Executa esta instrução como parte da regra de negócio desta página.
            'recencia'   => $grupo['centro'][0], // Executa esta instrução como parte da regra de negócio desta página.
            'frequencia' => $grupo['centro'][1], // Executa esta instrução como parte da regra de negócio desta página.
            'valor'      => $grupo['centro'][2], // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        $posicao++; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Monta a descricao de cada perfil para o texto da observacao.
    $descricoes = array_map( // Armazena em descricoes o valor usado nas próximas etapas deste fluxo.
        static fn(array $perfil): string => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            '%s (%d tutores, vêm a cada %s dias em média)', // Executa esta instrução como parte da regra de negócio desta página.
            $perfil['nome'], // Executa esta instrução como parte da regra de negócio desta página.
            $perfil['tamanho'], // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $perfil['recencia'], 0) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        $perfis // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
        'tipo'      => 'neutro', // Executa esta instrução como parte da regra de negócio desta página.
        'titulo'    => 'A base se divide em ' . count($perfis) . ' perfis de cliente', // Executa esta instrução como parte da regra de negócio desta página.
        'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'O agrupamento encontrou sozinho estes perfis, sem receber nenhuma classificação pronta: %s. Cada tutor foi colocado no grupo cujo comportamento médio mais se parece com o dele.', // Executa esta instrução como parte da regra de negócio desta página.
            implode('; ', $descricoes) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'k-médias com k=%d · %d tutores · convergiu em %d iterações · dimensões: recência, frequência e valor', // Executa esta instrução como parte da regra de negócio desta página.
            count($perfis), // Executa esta instrução como parte da regra de negócio desta página.
            count($clientes), // Executa esta instrução como parte da regra de negócio desta página.
            $agrupamento['iteracoes'] // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'algoritmo' => 'K-médias (agrupamento não supervisionado)', // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // O ultimo perfil da lista e o mais afastado, e e nele que a acao tem mais efeito.
    $afastados = $perfis[count($perfis) - 1]; // Armazena em afastados o valor usado nas próximas etapas deste fluxo.

    // So vale sugerir acao quando o grupo afastado tem gente dentro e de fato esta distante.
    if ($afastados['tamanho'] > 0 && $afastados['recencia'] > 0) { // Testa esta condição antes de executar o bloco correspondente.
        $promocoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => 'Oferta dirigida ao perfil "' . $afastados['nome'] . '"', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'Esse grupo reúne %d tutores que vêm a cada %s dias e gastaram em média R$ %s. Uma oferta enviada só para eles evita dar desconto a quem já viria de qualquer jeito.', // Executa esta instrução como parte da regra de negócio desta página.
                $afastados['tamanho'], // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $afastados['recencia'], 0), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $afastados['valor']) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'Centro do grupo: %s dias de recência · %s visitas · R$ %s acumulados', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $afastados['recencia'], 0), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $afastados['frequencia'], 1), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $afastados['valor']) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'K-médias (agrupamento não supervisionado)', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve os perfis encontrados.
    return $perfis; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * DESCOBERTA 3: servicos que costumam ser contratados juntos, pela mineracao de regras.
 */
function iaDescobrirCombos(array $pacote, array &$observacoes, array &$promocoes): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Executa a mineracao de regras de associacao sobre as visitas do periodo.
    $mineracao = motorApriori($pacote['transacoes']); // Armazena em mineracao o valor usado nas próximas etapas deste fluxo.

    // Sem regras encontradas nao ha associacao estatistica para relatar.
    if (!$mineracao['regras']) { // Testa esta condição antes de executar o bloco correspondente.
        return $mineracao; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Separa as regras em que a associacao e mais forte do que o acaso explicaria.
    $reais = array_values(array_filter( // Armazena em reais o valor usado nas próximas etapas deste fluxo.
        $mineracao['regras'], // Executa esta instrução como parte da regra de negócio desta página.
        static fn(array $regra): bool => $regra['lift'] > 1.0 // Executa esta instrução como parte da regra de negócio desta página.
    )); // Executa esta instrução como parte da regra de negócio desta página.

    // Sem nenhuma regra com lift acima de 1, os servicos sao contratados de forma independente.
    if (!$reais) { // Testa esta condição antes de executar o bloco correspondente.
        $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'tipo'      => 'neutro', // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => 'Os serviços são contratados de forma independente', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => 'A mineração não encontrou nenhum par de serviços que apareça junto com frequência maior do que o acaso explicaria. Montar combo agora seria juntar serviços que os clientes já escolhem separadamente.', // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf('%d visitas analisadas · nenhuma regra com lift acima de 1', $mineracao['transacoes']), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'Apriori (regras de associação)', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        return $mineracao; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // A primeira regra da lista e a de associacao mais forte.
    $melhor = $reais[0]; // Armazena em melhor o valor usado nas próximas etapas deste fluxo.

    $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
        'tipo'      => 'positivo', // Executa esta instrução como parte da regra de negócio desta página.
        'titulo'    => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'Quem contrata %s também contrata %s', // Executa esta instrução como parte da regra de negócio desta página.
            implode(' e ', $melhor['condicao']), // Executa esta instrução como parte da regra de negócio desta página.
            $melhor['consequencia'] // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'Em %s%% das visitas que incluíram %s, o cliente também levou %s. A associação é %sx mais forte do que seria por acaso, então não é coincidência: é um comportamento real da clientela.', // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero($melhor['confianca'] * 100, 0), // Executa esta instrução como parte da regra de negócio desta página.
            implode(' e ', $melhor['condicao']), // Executa esta instrução como parte da regra de negócio desta página.
            $melhor['consequencia'], // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $melhor['lift'], 2) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'Suporte %s%% (%d visitas) · confiança %s%% · lift %s · base de %d visitas', // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero($melhor['suporte'] * 100, 1), // Executa esta instrução como parte da regra de negócio desta página.
            $melhor['ocorrencias'], // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero($melhor['confianca'] * 100, 0), // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $melhor['lift'], 2), // Executa esta instrução como parte da regra de negócio desta página.
            $mineracao['transacoes'] // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'algoritmo' => 'Apriori (regras de associação)', // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    $promocoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
        'titulo'    => 'Combo ' . implode(' + ', $melhor['condicao']) . ' + ' . $melhor['consequencia'], // Executa esta instrução como parte da regra de negócio desta página.
        'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'Esse combo já acontece sozinho em %d visitas. Transformar em pacote com preço fechado não cria um hábito novo: apenas dá desconto em algo que o cliente já faz, em troca de ele fechar o pacote inteiro de uma vez.', // Executa esta instrução como parte da regra de negócio desta página.
            $melhor['ocorrencias'] // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'Confiança %s%% · lift %s · o combo aparece em %s%% de todas as visitas', // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero($melhor['confianca'] * 100, 0), // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $melhor['lift'], 2), // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero($melhor['suporte'] * 100, 1) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'algoritmo' => 'Apriori (regras de associação)', // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve a mineracao completa.
    return $mineracao; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * DESCOBERTA 4: dias e horarios anormalmente fracos, pela deteccao de anomalia.
 */
function iaDescobrirOciosidade(array $pacote, array &$observacoes, array &$promocoes): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Reune o faturamento de cada dia da semana para comparar um com o outro.
    $porDiaSemana = []; // Armazena em porDiaSemana o valor usado nas próximas etapas deste fluxo.
    // Percorre a serie diaria acumulando os totais de cada dia da semana.
    foreach ($pacote['serie_diaria'] as $dia) { // Percorre a coleção e processa cada registro individualmente.
        // Cria a posicao do dia da semana na primeira vez que ele aparece.
        if (!isset($porDiaSemana[$dia['dia_semana']])) { // Testa esta condição antes de executar o bloco correspondente.
            $porDiaSemana[$dia['dia_semana']] = []; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        $porDiaSemana[$dia['dia_semana']][] = $dia['total']; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Calcula a media de cada dia da semana, que e o valor comparado pelo z-score.
    $mediasPorDia = []; // Armazena em mediasPorDia o valor usado nas próximas etapas deste fluxo.
    // Percorre os dias da semana calculando a media de cada um.
    foreach ($porDiaSemana as $numeroDia => $totais) { // Percorre a coleção e processa cada registro individualmente.
        $mediasPorDia[analiseNomeDiaSemana($numeroDia)] = motorMedia($totais); // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Aplica a deteccao de anomalia sobre as medias dos dias da semana.
    $anomaliaDias = motorZScore($mediasPorDia); // Armazena em anomaliaDias o valor usado nas próximas etapas deste fluxo.
    // Aplica a deteccao de anomalia sobre a contagem de atendimentos por hora.
    $anomaliaHoras = motorZScore($pacote['serie_horaria']); // Armazena em anomaliaHoras o valor usado nas próximas etapas deste fluxo.

    // Guarda o dia e a hora mais fracos, que sao os primeiros de cada lista ordenada.
    $diaFraco = null; // Armazena em diaFraco o valor usado nas próximas etapas deste fluxo.
    $horaFraca = null; // Armazena em horaFraca o valor usado nas próximas etapas deste fluxo.

    // Extrai o primeiro dia da lista, que e o de menor z-score.
    foreach ($anomaliaDias['scores'] as $nome => $dados) { // Percorre a coleção e processa cada registro individualmente.
        $diaFraco = ['nome' => $nome] + $dados; // Armazena em diaFraco o valor usado nas próximas etapas deste fluxo.
        break; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Extrai a primeira hora da lista, que e a de menor z-score.
    foreach ($anomaliaHoras['scores'] as $hora => $dados) { // Percorre a coleção e processa cada registro individualmente.
        $horaFraca = ['hora' => (int) $hora] + $dados; // Armazena em horaFraca o valor usado nas próximas etapas deste fluxo.
        break; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Relata o dia da semana apenas quando ele esta estatisticamente fora do padrao.
    if ($diaFraco !== null && $diaFraco['z'] <= IA_LIMITE_ANOMALIA) { // Testa esta condição antes de executar o bloco correspondente.
        $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'tipo'      => 'atencao', // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => $diaFraco['nome'] . ' está fora do padrão da semana', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                '%s fatura em média R$ %s por dia, enquanto a média geral da semana é R$ %s. A diferença equivale a %s desvios padrão abaixo do normal, o que significa que não é variação comum: é um dia consistentemente fraco.', // Executa esta instrução como parte da regra de negócio desta página.
                $diaFraco['nome'], // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $diaFraco['valor']), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $anomaliaDias['media']), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero(abs((float) $diaFraco['z']), 1) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'z = %s · média da semana R$ %s · desvio padrão R$ %s', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $diaFraco['z'], 2), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $anomaliaDias['media']), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $anomaliaDias['desvio']) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'Detecção de anomalia (z-score)', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } elseif ($diaFraco !== null) { // Executa esta instrução como parte da regra de negócio desta página.
        // Quando nenhum dia foge do padrao, informar isso tambem e uma conclusao util.
        $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'tipo'      => 'positivo', // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => 'Nenhum dia da semana está fora do padrão', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'O dia mais fraco é %s, mas ele está a apenas %s desvios da média. A semana é equilibrada, então dar desconto em um dia específico não resolveria nenhum problema real.', // Executa esta instrução como parte da regra de negócio desta página.
                $diaFraco['nome'], // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero(abs((float) $diaFraco['z']), 1) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'Menor z encontrado: %s · limite de anomalia: %s', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $diaFraco['z'], 2), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero(IA_LIMITE_ANOMALIA, 1) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'Detecção de anomalia (z-score)', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Relata a faixa de horario apenas quando ela esta estatisticamente fora do padrao.
    if ($horaFraca !== null && $horaFraca['z'] <= IA_LIMITE_ANOMALIA) { // Testa esta condição antes de executar o bloco correspondente.
        $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'tipo'      => 'neutro', // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => sprintf('Ociosidade concentrada das %02dh às %02dh', $horaFraca['hora'], $horaFraca['hora'] + 1), // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'Essa faixa recebeu %s atendimentos, contra uma média de %s por faixa de horário. É o espaço da agenda com maior capacidade sobrando.', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $horaFraca['valor'], 0), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $anomaliaHoras['media'], 1) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'z = %s · média por faixa %s atendimentos · desvio padrão %s', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $horaFraca['z'], 2), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $anomaliaHoras['media'], 1), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $anomaliaHoras['desvio'], 1) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'Detecção de anomalia (z-score)', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // A promocao so faz sentido quando dia e horario realmente estao fora do padrao.
    if ($diaFraco !== null && $horaFraca !== null // Testa esta condição antes de executar o bloco correspondente.
        && $diaFraco['z'] <= IA_LIMITE_ANOMALIA // Executa esta instrução como parte da regra de negócio desta página.
        && $horaFraca['z'] <= IA_LIMITE_ANOMALIA) { // Executa esta instrução como parte da regra de negócio desta página.
        $promocoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => sprintf('Desconto de ociosidade: %s, %02dh às %02dh', $diaFraco['nome'], $horaFraca['hora'], $horaFraca['hora'] + 1), // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'Os dois algoritmos apontaram o mesmo tipo de sobra: %s é o dia fora do padrão e a faixa das %02dh é a mais vazia. Um desconto restrito a esse encontro ocupa uma vaga que hoje ficaria vazia, sem tirar cliente dos horários que já estão cheios.', // Executa esta instrução como parte da regra de negócio desta página.
                $diaFraco['nome'], // Executa esta instrução como parte da regra de negócio desta página.
                $horaFraca['hora'] // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'Dia: z = %s · Horário: z = %s · ambos abaixo do limite de %s', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $diaFraco['z'], 2), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $horaFraca['z'], 2), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero(IA_LIMITE_ANOMALIA, 1) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'Detecção de anomalia (z-score)', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve as duas deteccoes para quem quiser detalhar na tela.
    return ['dias' => $anomaliaDias, 'horas' => $anomaliaHoras]; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * DESCOBERTA 5: tendencia do faturamento, pela regressao linear sobre a serie mensal.
 */
function iaDescobrirTendencia(array $pacote, array &$observacoes, array &$promocoes): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Extrai apenas os valores da serie mensal, que e o que a regressao consome.
    $valores = array_map( // Armazena em valores o valor usado nas próximas etapas deste fluxo.
        static fn(array $mes): float => (float) $mes['total'], // Executa esta instrução como parte da regra de negócio desta página.
        $pacote['serie_mensal'] // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Ajusta a reta que melhor descreve a evolucao do faturamento.
    $tendencia = motorRegressaoLinear($valores); // Armazena em tendencia o valor usado nas próximas etapas deste fluxo.

    // Uma reta que explica menos da metade da variacao nao autoriza nenhuma afirmacao.
    if (!$tendencia['confiavel']) { // Testa esta condição antes de executar o bloco correspondente.
        $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'tipo'      => 'neutro', // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => 'O faturamento mensal não segue uma tendência clara', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => 'A reta ajustada aos últimos meses explica menos da metade da variação, então os meses oscilam sem seguir um rumo definido. Não dá para projetar o próximo mês com segurança a partir desses dados.', // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'R² = %s (abaixo de 0,500) · %d meses analisados', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $tendencia['r2'], 3), // Executa esta instrução como parte da regra de negócio desta página.
                count($valores) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'Regressão linear (mínimos quadrados)', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        return $tendencia; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Identifica se a reta aponta para cima ou para baixo.
    $subindo = $tendencia['inclinacao'] > 0; // Armazena em subindo o valor usado nas próximas etapas deste fluxo.

    $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
        'tipo'      => $subindo ? 'positivo' : 'atencao', // Executa esta instrução como parte da regra de negócio desta página.
        'titulo'    => $subindo // Executa esta instrução como parte da regra de negócio desta página.
            ? 'O faturamento vem crescendo mês a mês' // Executa esta instrução como parte da regra de negócio desta página.
            : 'O faturamento vem caindo mês a mês', // Executa esta instrução como parte da regra de negócio desta página.
        'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'A tendência dos últimos %d meses é de %s R$ %s por mês. Mantido esse ritmo, o próximo mês fecharia em torno de R$ %s.', // Executa esta instrução como parte da regra de negócio desta página.
            count($valores), // Executa esta instrução como parte da regra de negócio desta página.
            $subindo ? 'alta de' : 'queda de', // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero(abs((float) $tendencia['inclinacao'])), // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $tendencia['previsao']) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'R² = %s · inclinação R$ %s por mês · projeção para o próximo mês: R$ %s', // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $tendencia['r2'], 3), // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $tendencia['inclinacao']), // Executa esta instrução como parte da regra de negócio desta página.
            iaNumero((float) $tendencia['previsao']) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'algoritmo' => 'Regressão linear (mínimos quadrados)', // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Uma tendencia de queda confiavel justifica uma acao imediata.
    if (!$subindo) { // Testa esta condição antes de executar o bloco correspondente.
        $promocoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => 'Ação de contenção antes do próximo mês', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'A queda é consistente, não pontual. Antecipe a campanha de retorno e a oferta dirigida ao perfil afastado ainda neste mês, porque a projeção aponta para R$ %s no próximo se nada mudar.', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $tendencia['previsao']) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
                'Queda de R$ %s por mês · R² = %s', // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero(abs((float) $tendencia['inclinacao'])), // Executa esta instrução como parte da regra de negócio desta página.
                iaNumero((float) $tendencia['r2'], 3) // Executa esta instrução como parte da regra de negócio desta página.
            ), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => 'Regressão linear (mínimos quadrados)', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve o ajuste completo.
    return $tendencia; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * DESCOBERTA 6: servicos que nao foram procurados nenhuma vez no periodo.
 * Nao usa algoritmo: e uma contagem direta, mas fecha o item do planejamento
 * sobre servicos menos procurados.
 */
function iaDescobrirServicosParados(array $pacote, array &$observacoes): void // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Separa os servicos ativos que ficaram com zero procura na janela analisada.
    $parados = array_values(array_filter( // Armazena em parados o valor usado nas próximas etapas deste fluxo.
        $pacote['servicos'], // Executa esta instrução como parte da regra de negócio desta página.
        static fn(array $servico): bool => (int) $servico['quantidade'] === 0 // Executa esta instrução como parte da regra de negócio desta página.
    )); // Executa esta instrução como parte da regra de negócio desta página.

    // Sem servicos parados nao ha o que relatar.
    if (!$parados) { // Testa esta condição antes de executar o bloco correspondente.
        return; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Reune os nomes para citar no texto, limitando a quantidade exibida.
    $nomes = array_map( // Armazena em nomes o valor usado nas próximas etapas deste fluxo.
        static fn(array $servico): string => $servico['servico'], // Executa esta instrução como parte da regra de negócio desta página.
        array_slice($parados, 0, 4) // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
        'tipo'      => 'atencao', // Executa esta instrução como parte da regra de negócio desta página.
        'titulo'    => count($parados) . ' serviços não foram pedidos nenhuma vez', // Executa esta instrução como parte da regra de negócio desta página.
        'texto'     => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            'No período analisado ninguém contratou: %s. Ou o serviço precisa ser divulgado, ou ele já não faz sentido no cardápio do Beauty Pet.', // Executa esta instrução como parte da regra de negócio desta página.
            implode(', ', $nomes) // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'evidencia' => sprintf( // Executa esta instrução como parte da regra de negócio desta página.
            '%d serviços ativos com zero lançamentos em %d dias', // Executa esta instrução como parte da regra de negócio desta página.
            count($parados), // Executa esta instrução como parte da regra de negócio desta página.
            $pacote['janela_dias'] // Executa esta instrução como parte da regra de negócio desta página.
        ), // Executa esta instrução como parte da regra de negócio desta página.
        'algoritmo' => 'Contagem direta', // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Monta o paragrafo de abertura, resumindo o que os algoritmos encontraram.
 */
function iaMontarResumo(array $pacote, array $observacoes): string // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Conta quantas observacoes sao pontos de atencao e quantas sao positivas.
    $atencao = count(array_filter( // Armazena em atencao o valor usado nas próximas etapas deste fluxo.
        $observacoes, // Executa esta instrução como parte da regra de negócio desta página.
        static fn(array $item): bool => ($item['tipo'] ?? '') === 'atencao' // Executa esta instrução como parte da regra de negócio desta página.
    )); // Executa esta instrução como parte da regra de negócio desta página.

    // Reune quantos algoritmos diferentes produziram alguma conclusao.
    $algoritmos = array_unique(array_map( // Armazena em algoritmos o valor usado nas próximas etapas deste fluxo.
        static fn(array $item): string => $item['algoritmo'] ?? '', // Executa esta instrução como parte da regra de negócio desta página.
        $observacoes // Executa esta instrução como parte da regra de negócio desta página.
    )); // Executa esta instrução como parte da regra de negócio desta página.

    // Monta o texto informando a base analisada e o que saiu dela.
    return sprintf( // Devolve este resultado para quem chamou a função.
        'Foram analisados %d dias de operação, %d tutores, %d pets com histórico e %d visitas. %d algoritmos encontraram %d padrões, sendo %d pontos que pedem atenção.', // Executa esta instrução como parte da regra de negócio desta página.
        $pacote['janela_dias'], // Executa esta instrução como parte da regra de negócio desta página.
        count($pacote['rfm_clientes']), // Executa esta instrução como parte da regra de negócio desta página.
        count($pacote['rfm_pets']), // Executa esta instrução como parte da regra de negócio desta página.
        count($pacote['transacoes']), // Executa esta instrução como parte da regra de negócio desta página.
        count(array_filter($algoritmos)), // Executa esta instrução como parte da regra de negócio desta página.
        count($observacoes), // Executa esta instrução como parte da regra de negócio desta página.
        $atencao // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Ponto de entrada usado pelo Dashboard.
 * Executa todos os algoritmos, monta as conclusoes e guarda o resultado na sessao.
 */
function gerarAnaliseInteligente(PDO $pdo, bool $forcarAtualizacao = false): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Garante que a sessao esteja aberta para poder guardar o resultado entre as visitas a pagina.
    iniciarSessao(); // Executa esta instrução como parte da regra de negócio desta página.

    // Armazena em "cache" a analise guardada anteriormente, quando houver.
    $cache = $_SESSION['analise_ia'] ?? null; // Armazena em cache o valor usado nas próximas etapas deste fluxo.

    // Reaproveita a analise anterior enquanto ela ainda estiver dentro do prazo de validade.
    if (!$forcarAtualizacao && is_array($cache)) { // Testa esta condição antes de executar o bloco correspondente.
        // Calcula ha quantos minutos a analise guardada foi gerada.
        $minutos = (time() - ($cache['momento'] ?? 0)) / 60; // Armazena em minutos o valor usado nas próximas etapas deste fluxo.

        // Verifica esta condicao antes de decidir se reaproveita o resultado guardado.
        if ($minutos < IA_MINUTOS_CACHE) { // Testa esta condição antes de executar o bloco correspondente.
            return $cache['analise']; // Devolve este resultado para quem chamou a função.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Busca no banco todos os dados que os algoritmos precisam.
    $pacote = montarPacoteAnalise($pdo); // Armazena em pacote o valor usado nas próximas etapas deste fluxo.

    // Prepara as duas listas que serao preenchidas por cada descoberta.
    $observacoes = []; // Armazena em observacoes o valor usado nas próximas etapas deste fluxo.
    $promocoes = []; // Armazena em promocoes o valor usado nas próximas etapas deste fluxo.

    // Executa os algoritmos, cada um acrescentando o que encontrou nas listas acima.
    iaDescobrirAbandono($pacote, $observacoes, $promocoes); // Executa esta instrução como parte da regra de negócio desta página.
    iaDescobrirOciosidade($pacote, $observacoes, $promocoes); // Executa esta instrução como parte da regra de negócio desta página.
    iaDescobrirCombos($pacote, $observacoes, $promocoes); // Executa esta instrução como parte da regra de negócio desta página.
    iaDescobrirPerfis($pacote, $observacoes, $promocoes); // Executa esta instrução como parte da regra de negócio desta página.
    iaDescobrirTendencia($pacote, $observacoes, $promocoes); // Executa esta instrução como parte da regra de negócio desta página.
    iaDescobrirServicosParados($pacote, $observacoes); // Executa esta instrução como parte da regra de negócio desta página.

    // Garante que a tela nunca fique sem conteudo quando ainda ha poucos dados no sistema.
    if (!$observacoes) { // Testa esta condição antes de executar o bloco correspondente.
        $observacoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'tipo'      => 'neutro', // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => 'Dados ainda insuficientes para os algoritmos', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => 'Os modelos precisam de mais fechamentos e agendamentos registrados para comparar períodos e separar padrão de variação normal.', // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => sprintf('%d visitas registradas na janela de %d dias', count($pacote['transacoes']), $pacote['janela_dias']), // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => '-', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Garante que a coluna de promocoes tambem tenha sempre algo a dizer.
    if (!$promocoes) { // Testa esta condição antes de executar o bloco correspondente.
        $promocoes[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'titulo'    => 'Ainda sem base estatística para sugerir promoções', // Executa esta instrução como parte da regra de negócio desta página.
            'texto'     => 'Nenhum padrão atingiu o limite mínimo de confiança. Sugerir promoção agora seria chutar, e os algoritmos preferem não afirmar do que afirmar errado.', // Executa esta instrução como parte da regra de negócio desta página.
            'evidencia' => 'Nenhuma regra acima dos limites de suporte, confiança e anomalia', // Executa esta instrução como parte da regra de negócio desta página.
            'algoritmo' => '-', // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Monta o resultado final no formato esperado pela tela.
    $analise = [ // Armazena em analise o valor usado nas próximas etapas deste fluxo.
        'resumo'      => iaMontarResumo($pacote, $observacoes), // Executa esta instrução como parte da regra de negócio desta página.
        'observacoes' => $observacoes, // Executa esta instrução como parte da regra de negócio desta página.
        'promocoes'   => $promocoes, // Executa esta instrução como parte da regra de negócio desta página.
        'origem'      => 'motor-proprio', // Executa esta instrução como parte da regra de negócio desta página.
        'dados'       => $pacote, // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Guarda o resultado na sessao junto com o momento da geracao.
    $_SESSION['analise_ia'] = [ // Executa esta instrução como parte da regra de negócio desta página.
        'momento' => time(), // Executa esta instrução como parte da regra de negócio desta página.
        'analise' => $analise, // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve a analise pronta para ser exibida no Dashboard.
    return $analise; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
