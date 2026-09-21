<?php
/**
 * ARQUIVO: config/ia-motor.php
 * RESPONSABILIDADE: Algoritmos de aprendizado de maquina e mineracao de dados do Beauty Pet.
 *
 * Este arquivo e o nucleo da inteligencia do sistema. Ele nao acessa o banco de dados e nao
 * escreve texto: recebe tabelas de numeros e devolve os padroes encontrados dentro delas.
 * Essa separacao permite estudar e testar cada algoritmo isoladamente.
 *
 * ALGORITMOS IMPLEMENTADOS:
 *
 *   1. Estatistica descritiva ......... media e desvio padrao, base dos demais algoritmos
 *   2. Deteccao de anomalia (z-score) . encontra valores que fogem do padrao do conjunto
 *   3. Regressao linear ............... encontra a tendencia de uma serie e projeta o proximo valor
 *   4. K-medias (k-means) ............. agrupamento nao supervisionado: descobre sozinho os
 *                                       perfis existentes nos dados, sem receber rotulos prontos
 *   5. Apriori ........................ mineracao de regras de associacao: descobre quais itens
 *                                       costumam aparecer juntos nas mesmas transacoes
 *   6. Pontuacao RFM de abandono ...... modelo de risco baseado em Recencia, Frequencia e valor
 *                                       Monetario, calibrado pelo ritmo proprio de cada pet
 *
 * Nenhum destes algoritmos depende de internet, de servico externo ou de biblioteca instalada.
 * Todos aprendem exclusivamente com os dados registrados no proprio Beauty Pet.
 */

// Semente fixa usada nos sorteios do k-medias.
// Manter a semente fixa garante que a mesma base de dados produza sempre o mesmo agrupamento,
// o que torna o resultado reproduzivel e possivel de conferir.
const MOTOR_SEMENTE = 20260914;

/* ============================================================================
 * 1. ESTATISTICA DESCRITIVA
 * ==========================================================================*/

/**
 * Calcula a media aritmetica de uma lista de numeros.
 */
function motorMedia(array $valores): float
{
    // Uma lista vazia nao tem media, entao o resultado e zero.
    if (!$valores) {
        return 0.0;
    }

    // Soma todos os valores e divide pela quantidade de elementos.
    return array_sum($valores) / count($valores);
}

/**
 * Calcula o desvio padrao populacional de uma lista de numeros.
 * O desvio padrao mede o quanto os valores costumam se afastar da media.
 */
function motorDesvioPadrao(array $valores): float
{
    // Com menos de dois valores nao existe dispersao para medir.
    if (count($valores) < 2) {
        return 0.0;
    }

    // Armazena em "media" o ponto central em torno do qual a dispersao e medida.
    $media = motorMedia($valores);

    // Soma o quadrado da distancia de cada valor ate a media.
    $somaQuadrados = 0.0;
    // Percorre os valores e acumula o quadrado de cada diferenca.
    foreach ($valores as $valor) {
        $somaQuadrados += ($valor - $media) ** 2;
    }

    // A raiz quadrada da media dos quadrados e o desvio padrao.
    return sqrt($somaQuadrados / count($valores));
}

/**
 * Devolve o percentil de cada valor dentro da lista, em uma escala de 0 a 1.
 * O menor valor recebe 0 e o maior recebe 1, o que permite comparar grandezas diferentes.
 */
function motorPercentis(array $valores): array
{
    // Armazena em "quantidade" o tamanho da lista recebida.
    $quantidade = count($valores);

    // Com um unico valor nao ha o que ordenar, e ele fica no meio da escala.
    if ($quantidade < 2) {
        return $quantidade === 1 ? [array_key_first($valores) => 0.5] : [];
    }

    // Cria uma copia ordenada para descobrir a posicao de cada valor.
    $ordenados = $valores;
    asort($ordenados);

    // Percorre a lista ordenada atribuindo a posicao relativa de cada elemento.
    $percentis = [];
    $posicao = 0;
    // Cada elemento recebe a sua posicao dividida pelo total de posicoes possiveis.
    foreach ($ordenados as $chave => $valor) {
        $percentis[$chave] = $posicao / ($quantidade - 1);
        $posicao++;
    }

    // Devolve os percentis na mesma indexacao da lista original.
    return $percentis;
}

/**
 * Converte um percentil de 0 a 1 na nota de 1 a 5 usada no modelo RFM.
 * A nota 5 representa o melhor quinto do conjunto e a nota 1 o pior quinto.
 */
function motorNotaQuintil(float $percentil): int
{
    // Multiplica o percentil por cinco e garante que o resultado fique entre 1 e 5.
    return max(1, min(5, (int) ceil($percentil * 5) ?: 1));
}

/* ============================================================================
 * 2. DETECCAO DE ANOMALIA POR Z-SCORE
 *
 * O z-score mede quantos desvios padrao um valor esta afastado da media do conjunto.
 * Um z-score de -2 significa que aquele valor esta dois desvios ABAIXO do normal, ou seja,
 * nao e apenas o menor da lista: ele esta estatisticamente fora do padrao.
 *
 * E isso que diferencia "o dia de menor movimento" de "o dia anormalmente fraco".
 * ==========================================================================*/

/**
 * Calcula o z-score de cada valor de uma lista associativa.
 * Devolve a media, o desvio padrao e o z-score de cada chave, ordenado do menor para o maior.
 */
function motorZScore(array $valores): array
{
    // Sem valores nao ha o que analisar.
    if (!$valores) {
        return ['media' => 0.0, 'desvio' => 0.0, 'scores' => []];
    }

    // Armazena em "media" e "desvio" as duas medidas que definem o que e o comportamento normal.
    $media = motorMedia(array_values($valores));
    $desvio = motorDesvioPadrao(array_values($valores));

    // Calcula quantos desvios padrao cada valor esta afastado da media.
    $scores = [];
    // Percorre os valores e converte cada um em z-score.
    foreach ($valores as $chave => $valor) {
        $scores[$chave] = [
            'valor' => $valor,
            // Com desvio zero todos os valores sao iguais, entao nenhum e anomalo.
            'z'     => $desvio > 0 ? round(($valor - $media) / $desvio, 2) : 0.0,
        ];
    }

    // Ordena do mais abaixo do normal para o mais acima do normal.
    uasort($scores, static fn(array $a, array $b): int => $a['z'] <=> $b['z']);

    // Devolve as medidas do conjunto junto com os z-scores calculados.
    return [
        'media'  => round($media, 2),
        'desvio' => round($desvio, 2),
        'scores' => $scores,
    ];
}

/* ============================================================================
 * 3. REGRESSAO LINEAR PELO METODO DOS MINIMOS QUADRADOS
 *
 * Encontra a reta que melhor descreve uma sequencia de valores ao longo do tempo.
 * A inclinacao dessa reta diz se a tendencia e de crescimento ou de queda, e a reta
 * permite projetar qual seria o proximo valor caso a tendencia se mantenha.
 * ==========================================================================*/

/**
 * Ajusta uma reta a uma serie de valores e projeta o proximo ponto.
 *
 * Devolve a inclinacao, o valor inicial da reta, o coeficiente de determinacao (R2)
 * e a previsao para o proximo periodo. O R2 varia de 0 a 1 e indica o quanto a reta
 * realmente explica os dados: valores baixos significam que a tendencia e fraca.
 */
function motorRegressaoLinear(array $serie): array
{
    // Armazena em "quantidade" o tamanho da serie recebida.
    $quantidade = count($serie);

    // Com menos de tres pontos nao ha tendencia confiavel para calcular.
    if ($quantidade < 3) {
        return ['inclinacao' => 0.0, 'inicio' => 0.0, 'r2' => 0.0, 'previsao' => 0.0, 'confiavel' => false];
    }

    // Usa a posicao de cada ponto na serie como eixo do tempo.
    $eixoX = range(0, $quantidade - 1);
    $eixoY = array_values($serie);

    // Armazena as medias dos dois eixos, que sao o ponto por onde a reta obrigatoriamente passa.
    $mediaX = motorMedia($eixoX);
    $mediaY = motorMedia($eixoY);

    // Acumula os termos da formula dos minimos quadrados.
    $numerador = 0.0;
    $denominador = 0.0;
    // Percorre os pontos somando o produto e o quadrado das distancias ate as medias.
    for ($i = 0; $i < $quantidade; $i++) {
        $numerador += ($eixoX[$i] - $mediaX) * ($eixoY[$i] - $mediaY);
        $denominador += ($eixoX[$i] - $mediaX) ** 2;
    }

    // A inclinacao indica quanto o valor muda a cada periodo que passa.
    $inclinacao = $denominador > 0 ? $numerador / $denominador : 0.0;
    // O inicio e onde a reta cruza o eixo vertical.
    $inicio = $mediaY - ($inclinacao * $mediaX);

    // Calcula o R2 comparando o erro da reta com a variacao total dos dados.
    $somaErros = 0.0;
    $somaTotal = 0.0;
    // Percorre os pontos acumulando o erro da previsao e a variacao em torno da media.
    for ($i = 0; $i < $quantidade; $i++) {
        // Armazena em "previsto" o valor que a reta indicaria para este ponto.
        $previsto = $inicio + ($inclinacao * $eixoX[$i]);
        $somaErros += ($eixoY[$i] - $previsto) ** 2;
        $somaTotal += ($eixoY[$i] - $mediaY) ** 2;
    }

    // O R2 e a parcela da variacao que a reta conseguiu explicar.
    $r2 = $somaTotal > 0 ? 1 - ($somaErros / $somaTotal) : 0.0;

    // Projeta o proximo periodo aplicando a reta na posicao seguinte da serie.
    $previsao = $inicio + ($inclinacao * $quantidade);

    // Devolve o resultado do ajuste, marcando se a tendencia e forte o bastante para ser usada.
    return [
        'inclinacao' => round($inclinacao, 2),
        'inicio'     => round($inicio, 2),
        'r2'         => round(max(0.0, $r2), 3),
        'previsao'   => round(max(0.0, $previsao), 2),
        // Abaixo de 0,5 a reta explica menos da metade da variacao e nao deve virar afirmacao.
        'confiavel'  => $r2 >= 0.5,
    ];
}

/* ============================================================================
 * 4. K-MEDIAS (K-MEANS)
 *
 * Agrupamento nao supervisionado: o algoritmo recebe apenas os numeros, sem nenhum
 * rotulo, e descobre sozinho quantos perfis distintos existem e quem pertence a cada um.
 *
 * COMO FUNCIONA:
 *   1. Padroniza as colunas, para que valores em reais nao dominem valores em dias.
 *   2. Escolhe os centros iniciais pelo metodo k-means++, que os espalha pelo conjunto.
 *   3. Atribui cada ponto ao centro mais proximo.
 *   4. Recalcula cada centro como a media dos pontos que ficaram nele.
 *   5. Repete os passos 3 e 4 ate ninguem mais trocar de grupo.
 * ==========================================================================*/

/**
 * Padroniza cada coluna da matriz para media zero e desvio padrao um.
 * Sem isso, a coluna de valores em reais teria peso muito maior que a de quantidade de visitas.
 */
function motorPadronizar(array $matriz): array
{
    // Sem dados nao ha o que padronizar.
    if (!$matriz) {
        return ['normalizada' => [], 'medias' => [], 'desvios' => []];
    }

    // Armazena em "colunas" quantas caracteristicas cada ponto possui.
    $colunas = count($matriz[0]);
    $medias = [];
    $desvios = [];

    // Calcula a media e o desvio padrao de cada coluna separadamente.
    for ($c = 0; $c < $colunas; $c++) {
        // Armazena em "valoresDaColuna" todos os valores desta caracteristica.
        $valoresDaColuna = array_column($matriz, $c);
        $medias[$c] = motorMedia($valoresDaColuna);
        $desvios[$c] = motorDesvioPadrao($valoresDaColuna);
    }

    // Converte cada valor no numero de desvios padrao que ele esta afastado da media da coluna.
    $normalizada = [];
    // Percorre os pontos e padroniza cada caracteristica.
    foreach ($matriz as $ponto) {
        $linha = [];
        // Percorre as colunas aplicando a padronizacao.
        for ($c = 0; $c < $colunas; $c++) {
            // Uma coluna sem variacao nao distingue ninguem, entao vira zero.
            $linha[$c] = $desvios[$c] > 0 ? ($ponto[$c] - $medias[$c]) / $desvios[$c] : 0.0;
        }
        $normalizada[] = $linha;
    }

    // Devolve a matriz padronizada junto das medidas usadas, para poder desfazer a conta depois.
    return ['normalizada' => $normalizada, 'medias' => $medias, 'desvios' => $desvios];
}

/**
 * Calcula a distancia euclidiana entre dois pontos de mesma dimensao.
 */
function motorDistancia(array $a, array $b): float
{
    // Acumula o quadrado da diferenca em cada dimensao.
    $soma = 0.0;
    // Percorre as dimensoes somando as diferencas ao quadrado.
    for ($i = 0, $n = count($a); $i < $n; $i++) {
        $soma += ($a[$i] - $b[$i]) ** 2;
    }

    // A raiz da soma e a distancia em linha reta entre os dois pontos.
    return sqrt($soma);
}

/**
 * Escolhe os centros iniciais pelo metodo k-means++.
 *
 * O primeiro centro e sorteado e cada centro seguinte tem chance maior de cair longe
 * dos centros ja escolhidos. Isso evita que dois centros nascam praticamente no mesmo
 * lugar, situacao que faria o algoritmo convergir para um agrupamento ruim.
 */
function motorCentrosIniciais(array $pontos, int $k): array
{
    // Fixa a semente do sorteio para que o resultado seja sempre o mesmo na mesma base.
    mt_srand(MOTOR_SEMENTE);

    // Sorteia o primeiro centro entre todos os pontos disponiveis.
    $centros = [$pontos[mt_rand(0, count($pontos) - 1)]];

    // Escolhe os demais centros, um de cada vez.
    while (count($centros) < $k) {
        // Mede a distancia de cada ponto ate o centro mais proximo ja escolhido.
        $distancias = [];
        // Percorre os pontos calculando a menor distancia ate os centros existentes.
        foreach ($pontos as $indice => $ponto) {
            $menor = null;
            // Compara o ponto com cada centro ja definido.
            foreach ($centros as $centro) {
                // Armazena em "distancia" o afastamento deste ponto ate este centro.
                $distancia = motorDistancia($ponto, $centro);
                // Guarda a menor distancia encontrada ate agora.
                if ($menor === null || $distancia < $menor) {
                    $menor = $distancia;
                }
            }
            // O quadrado da distancia aumenta a chance de pontos distantes serem escolhidos.
            $distancias[$indice] = ($menor ?? 0.0) ** 2;
        }

        // Armazena em "soma" o total das distancias, que define a faixa do sorteio.
        $soma = array_sum($distancias);

        // Quando todos os pontos coincidem com os centros, nao ha novo centro util a escolher.
        if ($soma <= 0) {
            break;
        }

        // Sorteia um ponto com chance proporcional a sua distancia ao centro mais proximo.
        // O sorteio usa a faixa maxima do gerador para obter uma fracao entre 0 e 1.
        $alvo = (mt_rand(0, mt_getrandmax()) / mt_getrandmax()) * $soma;
        $acumulado = 0.0;
        // Percorre as distancias ate ultrapassar o valor sorteado.
        foreach ($distancias as $indice => $distancia) {
            $acumulado += $distancia;
            // O ponto que ultrapassa o alvo e o escolhido como novo centro.
            if ($acumulado >= $alvo) {
                $centros[] = $pontos[$indice];
                break;
            }
        }
    }

    // Devolve os centros iniciais escolhidos.
    return $centros;
}

/**
 * Executa o algoritmo de k-medias sobre a matriz recebida.
 *
 * Espera uma matriz em que cada linha e um ponto e cada coluna e uma caracteristica.
 * Devolve, para cada ponto, o grupo a que ele pertence, e os centros de cada grupo
 * convertidos de volta para as unidades originais.
 */
function motorKmeans(array $matriz, int $k = 4, int $maxIteracoes = 60): array
{
    // Armazena em "quantidade" quantos pontos existem na matriz.
    $quantidade = count($matriz);

    // Com menos pontos do que grupos o agrupamento nao faz sentido.
    if ($quantidade < $k || $k < 2) {
        return ['grupos' => [], 'atribuicoes' => [], 'iteracoes' => 0, 'convergiu' => false];
    }

    // Padroniza as colunas para que todas as caracteristicas tenham o mesmo peso.
    $padronizacao = motorPadronizar($matriz);
    $pontos = $padronizacao['normalizada'];

    // Escolhe os centros iniciais de forma espalhada pelo conjunto.
    $centros = motorCentrosIniciais($pontos, $k);
    // Ajusta o numero de grupos caso nao tenha sido possivel formar todos os centros.
    $k = count($centros);

    // Guarda a qual grupo cada ponto pertence.
    $atribuicoes = array_fill(0, $quantidade, -1);
    $iteracoes = 0;
    $convergiu = false;

    // Repete a atribuicao e o recalculo ate estabilizar ou atingir o limite de rodadas.
    for ($rodada = 0; $rodada < $maxIteracoes; $rodada++) {
        $iteracoes = $rodada + 1;
        $mudou = false;

        // PASSO 1: coloca cada ponto no grupo cujo centro esta mais proximo.
        foreach ($pontos as $indice => $ponto) {
            $melhorGrupo = 0;
            $menorDistancia = null;

            // Compara o ponto com o centro de cada grupo.
            for ($g = 0; $g < $k; $g++) {
                // Armazena em "distancia" o afastamento ate o centro deste grupo.
                $distancia = motorDistancia($ponto, $centros[$g]);
                // Guarda o grupo mais proximo encontrado ate agora.
                if ($menorDistancia === null || $distancia < $menorDistancia) {
                    $menorDistancia = $distancia;
                    $melhorGrupo = $g;
                }
            }

            // Marca que houve mudanca sempre que um ponto troca de grupo.
            if ($atribuicoes[$indice] !== $melhorGrupo) {
                $atribuicoes[$indice] = $melhorGrupo;
                $mudou = true;
            }
        }

        // PASSO 2: recalcula cada centro como a media dos pontos que ficaram no grupo.
        for ($g = 0; $g < $k; $g++) {
            // Reune os pontos que pertencem a este grupo.
            $membros = [];
            // Percorre as atribuicoes selecionando os pontos deste grupo.
            foreach ($atribuicoes as $indice => $grupo) {
                if ($grupo === $g) {
                    $membros[] = $pontos[$indice];
                }
            }

            // Um grupo vazio mantem o centro anterior, para nao desaparecer do calculo.
            if (!$membros) {
                continue;
            }

            // Calcula a media de cada caracteristica entre os membros do grupo.
            $novoCentro = [];
            // Percorre as dimensoes calculando a media de cada uma.
            for ($c = 0, $colunas = count($membros[0]); $c < $colunas; $c++) {
                $novoCentro[$c] = motorMedia(array_column($membros, $c));
            }

            $centros[$g] = $novoCentro;
        }

        // Quando ninguem troca de grupo, o algoritmo chegou ao resultado final.
        if (!$mudou) {
            $convergiu = true;
            break;
        }
    }

    // Converte os centros de volta para as unidades originais, para poderem ser lidos por pessoas.
    $grupos = [];
    // Percorre os grupos montando a descricao de cada um.
    for ($g = 0; $g < $k; $g++) {
        // Reune os indices dos pontos que pertencem a este grupo.
        $membros = array_keys(array_filter(
            $atribuicoes,
            static fn(int $grupo): bool => $grupo === $g
        ));

        // Desfaz a padronizacao para recuperar o valor em dias, visitas e reais.
        $centroOriginal = [];
        // Percorre as dimensoes revertendo a conta da padronizacao.
        foreach ($centros[$g] as $c => $valor) {
            $centroOriginal[$c] = round(
                ($valor * $padronizacao['desvios'][$c]) + $padronizacao['medias'][$c],
                2
            );
        }

        $grupos[$g] = [
            'tamanho' => count($membros),
            'membros' => $membros,
            'centro'  => $centroOriginal,
        ];
    }

    // Devolve o agrupamento completo junto das informacoes sobre a execucao.
    return [
        'grupos'      => $grupos,
        'atribuicoes' => $atribuicoes,
        'iteracoes'   => $iteracoes,
        'convergiu'   => $convergiu,
    ];
}

/* ============================================================================
 * 5. APRIORI - MINERACAO DE REGRAS DE ASSOCIACAO
 *
 * Descobre quais itens costumam aparecer juntos nas mesmas transacoes.
 * No Beauty Pet, cada transacao e uma visita e cada item e um servico contratado.
 *
 * TRES MEDIDAS:
 *   SUPORTE    - em que fracao das visitas o conjunto aparece. Mede se o padrao e comum.
 *   CONFIANCA  - dado que o cliente contratou A, com que frequencia ele tambem contrata B.
 *   LIFT       - quantas vezes a presenca de A aumenta a chance de B em relacao ao acaso.
 *                Lift acima de 1 indica associacao real; lift igual a 1 indica coincidencia.
 *
 * O nome "Apriori" vem da propriedade que o algoritmo usa para podar a busca: se um
 * conjunto nao e frequente, nenhum conjunto maior que o contenha pode ser frequente.
 * ==========================================================================*/

/**
 * Conta em quantas transacoes cada conjunto de itens aparece por inteiro.
 */
function motorContarConjuntos(array $transacoes, array $conjuntos): array
{
    // Prepara o contador de cada conjunto candidato.
    $contagens = [];
    // Percorre os candidatos iniciando todos em zero.
    foreach ($conjuntos as $chave => $conjunto) {
        $contagens[$chave] = 0;
    }

    // Percorre as transacoes verificando quais conjuntos estao contidos em cada uma.
    foreach ($transacoes as $transacao) {
        // Compara a transacao com cada conjunto candidato.
        foreach ($conjuntos as $chave => $conjunto) {
            // O conjunto so conta quando TODOS os seus itens estao na transacao.
            if (!array_diff($conjunto, $transacao)) {
                $contagens[$chave]++;
            }
        }
    }

    // Devolve quantas vezes cada conjunto apareceu.
    return $contagens;
}

/**
 * Gera os candidatos de tamanho N+1 a partir dos conjuntos frequentes de tamanho N.
 * Apenas conjuntos cujos pedacos ja se mostraram frequentes viram candidatos.
 */
function motorGerarCandidatos(array $frequentes, int $tamanho): array
{
    // Reune todos os itens que aparecem nos conjuntos frequentes.
    $itens = [];
    // Percorre os conjuntos frequentes coletando seus itens.
    foreach ($frequentes as $conjunto) {
        // Percorre os itens do conjunto adicionando os que ainda nao foram vistos.
        foreach ($conjunto as $item) {
            if (!in_array($item, $itens, true)) {
                $itens[] = $item;
            }
        }
    }

    // Monta os novos candidatos combinando cada conjunto frequente com cada item disponivel.
    $candidatos = [];
    // Percorre os conjuntos frequentes.
    foreach ($frequentes as $conjunto) {
        // Tenta acrescentar cada item ao conjunto.
        foreach ($itens as $item) {
            // Ignora itens que ja estao no conjunto.
            if (in_array($item, $conjunto, true)) {
                continue;
            }

            // Monta o candidato e ordena para que a mesma combinacao tenha sempre a mesma chave.
            $candidato = $conjunto;
            $candidato[] = $item;
            sort($candidato);

            // Descarta candidatos com tamanho diferente do esperado.
            if (count($candidato) !== $tamanho) {
                continue;
            }

            // Usa o proprio conteudo como chave, o que elimina combinacoes repetidas.
            $candidatos[implode(' | ', $candidato)] = $candidato;
        }
    }

    // Devolve os candidatos unicos.
    return $candidatos;
}

/**
 * Executa o algoritmo Apriori e devolve as regras de associacao encontradas.
 *
 * @param array $transacoes     Lista de visitas, cada uma com os servicos contratados nela.
 * @param float $suporteMinimo  Fracao minima de visitas em que o conjunto precisa aparecer.
 * @param float $confiancaMinima Confianca minima para a regra ser considerada util.
 */
function motorApriori(array $transacoes, float $suporteMinimo = 0.05, float $confiancaMinima = 0.30): array
{
    // Armazena em "total" quantas visitas foram analisadas.
    $total = count($transacoes);

    // Sem transacoes suficientes nao ha padrao estatistico para minerar.
    if ($total < 10) {
        return ['regras' => [], 'frequentes' => [], 'transacoes' => $total];
    }

    // Armazena em "minimoAbsoluto" quantas visitas correspondem ao suporte minimo pedido.
    $minimoAbsoluto = max(2, (int) ceil($suporteMinimo * $total));

    // PASSO 1: monta os conjuntos de um item e mantem apenas os frequentes.
    $candidatos = [];
    // Percorre as transacoes coletando todos os servicos distintos.
    foreach ($transacoes as $transacao) {
        // Percorre os servicos da transacao criando um candidato para cada um.
        foreach ($transacao as $item) {
            $candidatos[$item] = [$item];
        }
    }

    // Conta em quantas visitas cada servico aparece.
    $contagens = motorContarConjuntos($transacoes, $candidatos);

    // Guarda a contagem de todos os conjuntos frequentes encontrados em qualquer tamanho.
    $frequentesPorChave = [];
    // Guarda os conjuntos frequentes do tamanho atual, usados para gerar o proximo tamanho.
    $nivelAtual = [];

    // Separa os servicos que atingiram o suporte minimo.
    foreach ($candidatos as $chave => $conjunto) {
        // Mantem apenas os conjuntos suficientemente comuns.
        if ($contagens[$chave] >= $minimoAbsoluto) {
            $frequentesPorChave[$chave] = $contagens[$chave];
            $nivelAtual[$chave] = $conjunto;
        }
    }

    // PASSO 2: sobe de tamanho enquanto houver conjuntos frequentes para combinar.
    // O limite de tres itens mantem o custo baixo e cobre os combos que um pet shop consegue montar.
    for ($tamanho = 2; $tamanho <= 3; $tamanho++) {
        // Sem pelo menos dois conjuntos frequentes nao ha o que combinar.
        if (count($nivelAtual) < 2) {
            break;
        }

        // Gera os candidatos do proximo tamanho a partir dos frequentes do tamanho anterior.
        $candidatos = motorGerarCandidatos($nivelAtual, $tamanho);

        // Interrompe quando nao restam candidatos a avaliar.
        if (!$candidatos) {
            break;
        }

        // Conta em quantas visitas cada candidato aparece por inteiro.
        $contagens = motorContarConjuntos($transacoes, $candidatos);

        // Mantem apenas os candidatos que atingiram o suporte minimo.
        $nivelAtual = [];
        foreach ($candidatos as $chave => $conjunto) {
            if ($contagens[$chave] >= $minimoAbsoluto) {
                $frequentesPorChave[$chave] = $contagens[$chave];
                $nivelAtual[$chave] = $conjunto;
            }
        }
    }

    // PASSO 3: transforma os conjuntos frequentes em regras do tipo "se A entao B".
    $regras = [];
    // Percorre os conjuntos frequentes procurando os que tem mais de um item.
    foreach ($frequentesPorChave as $chave => $contagem) {
        // Reconstroi a lista de itens a partir da chave.
        $conjunto = explode(' | ', $chave);

        // Conjuntos de um item sozinho nao geram regra.
        if (count($conjunto) < 2) {
            continue;
        }

        // Gera uma regra para cada item do conjunto, tratando-o como consequencia.
        foreach ($conjunto as $consequencia) {
            // A condicao e formada por todos os outros itens do conjunto.
            $condicao = array_values(array_diff($conjunto, [$consequencia]));
            // Reconstroi as chaves para consultar as contagens ja calculadas.
            $chaveCondicao = implode(' | ', $condicao);
            $chaveConsequencia = $consequencia;

            // Ignora a regra quando alguma das partes nao foi registrada como frequente.
            if (!isset($frequentesPorChave[$chaveCondicao], $frequentesPorChave[$chaveConsequencia])) {
                continue;
            }

            // A confianca e a fracao das visitas com a condicao que tambem tiveram a consequencia.
            $confianca = $contagem / $frequentesPorChave[$chaveCondicao];

            // Descarta regras pouco confiaveis.
            if ($confianca < $confiancaMinima) {
                continue;
            }

            // O suporte da consequencia isolada serve de base de comparacao para o lift.
            $suporteConsequencia = $frequentesPorChave[$chaveConsequencia] / $total;

            $regras[] = [
                'condicao'    => $condicao,
                'consequencia' => $consequencia,
                'ocorrencias' => $contagem,
                'suporte'     => round($contagem / $total, 3),
                'confianca'   => round($confianca, 3),
                // Lift maior que 1 indica que a associacao e mais forte do que o acaso explicaria.
                'lift'        => $suporteConsequencia > 0 ? round($confianca / $suporteConsequencia, 2) : 0.0,
            ];
        }
    }

    // Ordena as regras da associacao mais forte para a mais fraca.
    usort($regras, static fn(array $a, array $b): int => $b['lift'] <=> $a['lift']);

    // Devolve as regras encontradas junto do contexto em que foram mineradas.
    return [
        'regras'     => $regras,
        'frequentes' => $frequentesPorChave,
        'transacoes' => $total,
    ];
}

/* ============================================================================
 * 6. PONTUACAO RFM DE RISCO DE ABANDONO
 *
 * O modelo RFM classifica clientes por Recencia, Frequencia e valor Monetario.
 * A adaptacao feita aqui e comparar a recencia de cada pet com o RITMO DELE PROPRIO,
 * e nao com um prazo fixo igual para todos.
 *
 * Um pet que vem a cada 15 dias e esta ha 45 dias sem vir esta muito mais atrasado
 * do que um pet que vem a cada 90 dias e esta ha 100 dias sem vir, mesmo o segundo
 * tendo o numero absoluto maior. Um prazo fixo de 60 dias erraria nos dois casos.
 * ==========================================================================*/

/**
 * Calcula o risco de abandono de cada pet e a prioridade de contato.
 *
 * Devolve a lista ordenada da maior para a menor prioridade, com todos os numeros
 * que sustentam a conclusao, para que ela possa ser conferida.
 */
function motorScoreAbandono(array $matrizRfm): array
{
    // Sem historico nao ha comportamento para modelar.
    if (count($matrizRfm) < 3) {
        return ['pets' => [], 'intervalo_tipico' => 0.0];
    }

    // Reune os intervalos conhecidos para descobrir o ritmo tipico da casa.
    $intervalos = array_values(array_filter(array_column($matrizRfm, 'intervalo_medio')));
    // Ordena os intervalos para poder extrair a mediana.
    sort($intervalos);
    // A mediana representa o ritmo tipico melhor que a media, porque ignora casos extremos.
    $intervaloTipico = $intervalos
        ? $intervalos[(int) floor(count($intervalos) / 2)]
        : 30.0;

    // Calcula os percentis de cada dimensao do modelo RFM.
    // A recencia entra invertida, porque nela o menor valor e o melhor.
    $percentisRecencia = motorPercentis(array_map(
        static fn(array $item): float => -1 * $item['recencia'],
        $matrizRfm
    ));
    $percentisFrequencia = motorPercentis(array_column($matrizRfm, 'frequencia'));
    $percentisValor = motorPercentis(array_column($matrizRfm, 'valor'));

    // Pontua cada pet combinando o atraso relativo com a importancia dele para o negocio.
    $pets = [];
    // Percorre a matriz calculando o risco individual de cada pet.
    foreach ($matrizRfm as $indice => $item) {
        // Usa o intervalo do proprio pet ou, na falta dele, o ritmo tipico da casa.
        $intervaloReferencia = $item['intervalo_medio'] ?? $intervaloTipico;
        // Protege contra intervalos invalidos que zerariam a divisao.
        $intervaloReferencia = max(1.0, (float) $intervaloReferencia);

        // Mede quantas vezes o pet ja ultrapassou o proprio intervalo entre visitas.
        $atrasoRelativo = $item['recencia'] / $intervaloReferencia;

        // Converte o atraso em uma nota de risco de 0 a 100.
        // Um atraso de 1x equivale a 30 pontos e um atraso de 3,3x satura a escala em 100.
        $risco = (int) min(100, round($atrasoRelativo * 30));

        // A prioridade pondera o risco pelo quanto aquele pet representa em faturamento.
        // Um pet valioso em risco deve ser contatado antes de um pet de baixo valor no mesmo risco.
        $prioridade = (int) round($risco * (0.5 + (0.5 * $percentisValor[$indice])));

        $pets[] = [
            'pet'                => $item['pet'],
            'tutor'              => $item['tutor'],
            'recencia'           => $item['recencia'],
            'frequencia'         => $item['frequencia'],
            'valor'              => $item['valor'],
            'intervalo_proprio'  => $item['intervalo_medio'],
            'intervalo_usado'    => round($intervaloReferencia, 1),
            'atraso_relativo'    => round($atrasoRelativo, 1),
            'risco'              => $risco,
            'prioridade'         => $prioridade,
            // Notas RFM classicas, de 1 a 5, uteis para conferir a classificacao.
            'nota_recencia'      => motorNotaQuintil($percentisRecencia[$indice]),
            'nota_frequencia'    => motorNotaQuintil($percentisFrequencia[$indice]),
            'nota_valor'         => motorNotaQuintil($percentisValor[$indice]),
        ];
    }

    // Ordena da maior para a menor prioridade de contato.
    usort($pets, static fn(array $a, array $b): int => $b['prioridade'] <=> $a['prioridade']);

    // Devolve a pontuacao junto do ritmo tipico usado como referencia.
    return [
        'pets'             => $pets,
        'intervalo_tipico' => round($intervaloTipico, 1),
    ];
}
