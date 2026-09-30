<?php // Executa esta instrução como parte da regra de negócio desta página.
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
const MOTOR_SEMENTE = 20260914; // Declara uma constante de configuração deste módulo.

/* ============================================================================
 * 1. ESTATISTICA DESCRITIVA
 * ==========================================================================*/

/**
 * Calcula a media aritmetica de uma lista de numeros.
 */
function motorMedia(array $valores): float // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Uma lista vazia nao tem media, entao o resultado e zero.
    if (!$valores) { // Testa esta condição antes de executar o bloco correspondente.
        return 0.0; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Soma todos os valores e divide pela quantidade de elementos.
    return array_sum($valores) / count($valores); // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Calcula o desvio padrao populacional de uma lista de numeros.
 * O desvio padrao mede o quanto os valores costumam se afastar da media.
 */
function motorDesvioPadrao(array $valores): float // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Com menos de dois valores nao existe dispersao para medir.
    if (count($valores) < 2) { // Testa esta condição antes de executar o bloco correspondente.
        return 0.0; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Armazena em "media" o ponto central em torno do qual a dispersao e medida.
    $media = motorMedia($valores); // Armazena em media o valor usado nas próximas etapas deste fluxo.

    // Soma o quadrado da distancia de cada valor ate a media.
    $somaQuadrados = 0.0; // Armazena em somaQuadrados o valor usado nas próximas etapas deste fluxo.
    // Percorre os valores e acumula o quadrado de cada diferenca.
    foreach ($valores as $valor) { // Percorre a coleção e processa cada registro individualmente.
        $somaQuadrados += ($valor - $media) ** 2; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // A raiz quadrada da media dos quadrados e o desvio padrao.
    return sqrt($somaQuadrados / count($valores)); // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Devolve o percentil de cada valor dentro da lista, em uma escala de 0 a 1.
 * O menor valor recebe 0 e o maior recebe 1, o que permite comparar grandezas diferentes.
 */
function motorPercentis(array $valores): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Armazena em "quantidade" o tamanho da lista recebida.
    $quantidade = count($valores); // Armazena em quantidade o valor usado nas próximas etapas deste fluxo.

    // Com um unico valor nao ha o que ordenar, e ele fica no meio da escala.
    if ($quantidade < 2) { // Testa esta condição antes de executar o bloco correspondente.
        return $quantidade === 1 ? [array_key_first($valores) => 0.5] : []; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Cria uma copia ordenada para descobrir a posicao de cada valor.
    $ordenados = $valores; // Armazena em ordenados o valor usado nas próximas etapas deste fluxo.
    asort($ordenados); // Executa esta instrução como parte da regra de negócio desta página.

    // Percorre a lista ordenada atribuindo a posicao relativa de cada elemento.
    $percentis = []; // Armazena em percentis o valor usado nas próximas etapas deste fluxo.
    $posicao = 0; // Armazena em posicao o valor usado nas próximas etapas deste fluxo.
    // Cada elemento recebe a sua posicao dividida pelo total de posicoes possiveis.
    foreach ($ordenados as $chave => $valor) { // Percorre a coleção e processa cada registro individualmente.
        $percentis[$chave] = $posicao / ($quantidade - 1); // Executa esta instrução como parte da regra de negócio desta página.
        $posicao++; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve os percentis na mesma indexacao da lista original.
    return $percentis; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Converte um percentil de 0 a 1 na nota de 1 a 5 usada no modelo RFM.
 * A nota 5 representa o melhor quinto do conjunto e a nota 1 o pior quinto.
 */
function motorNotaQuintil(float $percentil): int // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Multiplica o percentil por cinco e garante que o resultado fique entre 1 e 5.
    return max(1, min(5, (int) ceil($percentil * 5) ?: 1)); // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

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
function motorZScore(array $valores): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Sem valores nao ha o que analisar.
    if (!$valores) { // Testa esta condição antes de executar o bloco correspondente.
        return ['media' => 0.0, 'desvio' => 0.0, 'scores' => []]; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Armazena em "media" e "desvio" as duas medidas que definem o que e o comportamento normal.
    $media = motorMedia(array_values($valores)); // Armazena em media o valor usado nas próximas etapas deste fluxo.
    $desvio = motorDesvioPadrao(array_values($valores)); // Armazena em desvio o valor usado nas próximas etapas deste fluxo.

    // Calcula quantos desvios padrao cada valor esta afastado da media.
    $scores = []; // Armazena em scores o valor usado nas próximas etapas deste fluxo.
    // Percorre os valores e converte cada um em z-score.
    foreach ($valores as $chave => $valor) { // Percorre a coleção e processa cada registro individualmente.
        $scores[$chave] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'valor' => $valor, // Executa esta instrução como parte da regra de negócio desta página.
            // Com desvio zero todos os valores sao iguais, entao nenhum e anomalo.
            'z'     => $desvio > 0 ? round(($valor - $media) / $desvio, 2) : 0.0, // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Ordena do mais abaixo do normal para o mais acima do normal.
    uasort($scores, static fn(array $a, array $b): int => $a['z'] <=> $b['z']); // Executa esta instrução como parte da regra de negócio desta página.

    // Devolve as medidas do conjunto junto com os z-scores calculados.
    return [ // Devolve este resultado para quem chamou a função.
        'media'  => round($media, 2), // Executa esta instrução como parte da regra de negócio desta página.
        'desvio' => round($desvio, 2), // Executa esta instrução como parte da regra de negócio desta página.
        'scores' => $scores, // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

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
function motorRegressaoLinear(array $serie): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Armazena em "quantidade" o tamanho da serie recebida.
    $quantidade = count($serie); // Armazena em quantidade o valor usado nas próximas etapas deste fluxo.

    // Com menos de tres pontos nao ha tendencia confiavel para calcular.
    if ($quantidade < 3) { // Testa esta condição antes de executar o bloco correspondente.
        return ['inclinacao' => 0.0, 'inicio' => 0.0, 'r2' => 0.0, 'previsao' => 0.0, 'confiavel' => false]; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Usa a posicao de cada ponto na serie como eixo do tempo.
    $eixoX = range(0, $quantidade - 1); // Armazena em eixoX o valor usado nas próximas etapas deste fluxo.
    $eixoY = array_values($serie); // Armazena em eixoY o valor usado nas próximas etapas deste fluxo.

    // Armazena as medias dos dois eixos, que sao o ponto por onde a reta obrigatoriamente passa.
    $mediaX = motorMedia($eixoX); // Armazena em mediaX o valor usado nas próximas etapas deste fluxo.
    $mediaY = motorMedia($eixoY); // Armazena em mediaY o valor usado nas próximas etapas deste fluxo.

    // Acumula os termos da formula dos minimos quadrados.
    $numerador = 0.0; // Armazena em numerador o valor usado nas próximas etapas deste fluxo.
    $denominador = 0.0; // Armazena em denominador o valor usado nas próximas etapas deste fluxo.
    // Percorre os pontos somando o produto e o quadrado das distancias ate as medias.
    for ($i = 0; $i < $quantidade; $i++) { // Repete este bloco enquanto o controle definido permitir.
        $numerador += ($eixoX[$i] - $mediaX) * ($eixoY[$i] - $mediaY); // Executa esta instrução como parte da regra de negócio desta página.
        $denominador += ($eixoX[$i] - $mediaX) ** 2; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // A inclinacao indica quanto o valor muda a cada periodo que passa.
    $inclinacao = $denominador > 0 ? $numerador / $denominador : 0.0; // Armazena em inclinacao o valor usado nas próximas etapas deste fluxo.
    // O inicio e onde a reta cruza o eixo vertical.
    $inicio = $mediaY - ($inclinacao * $mediaX); // Armazena em inicio o valor usado nas próximas etapas deste fluxo.

    // Calcula o R2 comparando o erro da reta com a variacao total dos dados.
    $somaErros = 0.0; // Armazena em somaErros o valor usado nas próximas etapas deste fluxo.
    $somaTotal = 0.0; // Armazena em somaTotal o valor usado nas próximas etapas deste fluxo.
    // Percorre os pontos acumulando o erro da previsao e a variacao em torno da media.
    for ($i = 0; $i < $quantidade; $i++) { // Repete este bloco enquanto o controle definido permitir.
        // Armazena em "previsto" o valor que a reta indicaria para este ponto.
        $previsto = $inicio + ($inclinacao * $eixoX[$i]); // Armazena em previsto o valor usado nas próximas etapas deste fluxo.
        $somaErros += ($eixoY[$i] - $previsto) ** 2; // Executa esta instrução como parte da regra de negócio desta página.
        $somaTotal += ($eixoY[$i] - $mediaY) ** 2; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // O R2 e a parcela da variacao que a reta conseguiu explicar.
    $r2 = $somaTotal > 0 ? 1 - ($somaErros / $somaTotal) : 0.0; // Armazena em r2 o valor usado nas próximas etapas deste fluxo.

    // Projeta o proximo periodo aplicando a reta na posicao seguinte da serie.
    $previsao = $inicio + ($inclinacao * $quantidade); // Armazena em previsao o valor usado nas próximas etapas deste fluxo.

    // Devolve o resultado do ajuste, marcando se a tendencia e forte o bastante para ser usada.
    return [ // Devolve este resultado para quem chamou a função.
        'inclinacao' => round($inclinacao, 2), // Executa esta instrução como parte da regra de negócio desta página.
        'inicio'     => round($inicio, 2), // Executa esta instrução como parte da regra de negócio desta página.
        'r2'         => round(max(0.0, $r2), 3), // Executa esta instrução como parte da regra de negócio desta página.
        'previsao'   => round(max(0.0, $previsao), 2), // Executa esta instrução como parte da regra de negócio desta página.
        // Abaixo de 0,5 a reta explica menos da metade da variacao e nao deve virar afirmacao.
        'confiavel'  => $r2 >= 0.5, // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

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
function motorPadronizar(array $matriz): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Sem dados nao ha o que padronizar.
    if (!$matriz) { // Testa esta condição antes de executar o bloco correspondente.
        return ['normalizada' => [], 'medias' => [], 'desvios' => []]; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Armazena em "colunas" quantas caracteristicas cada ponto possui.
    $colunas = count($matriz[0]); // Armazena em colunas o valor usado nas próximas etapas deste fluxo.
    $medias = []; // Armazena em medias o valor usado nas próximas etapas deste fluxo.
    $desvios = []; // Armazena em desvios o valor usado nas próximas etapas deste fluxo.

    // Calcula a media e o desvio padrao de cada coluna separadamente.
    for ($c = 0; $c < $colunas; $c++) { // Repete este bloco enquanto o controle definido permitir.
        // Armazena em "valoresDaColuna" todos os valores desta caracteristica.
        $valoresDaColuna = array_column($matriz, $c); // Armazena em valoresDaColuna o valor usado nas próximas etapas deste fluxo.
        $medias[$c] = motorMedia($valoresDaColuna); // Executa esta instrução como parte da regra de negócio desta página.
        $desvios[$c] = motorDesvioPadrao($valoresDaColuna); // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Converte cada valor no numero de desvios padrao que ele esta afastado da media da coluna.
    $normalizada = []; // Armazena em normalizada o valor usado nas próximas etapas deste fluxo.
    // Percorre os pontos e padroniza cada caracteristica.
    foreach ($matriz as $ponto) { // Percorre a coleção e processa cada registro individualmente.
        $linha = []; // Armazena em linha o valor usado nas próximas etapas deste fluxo.
        // Percorre as colunas aplicando a padronizacao.
        for ($c = 0; $c < $colunas; $c++) { // Repete este bloco enquanto o controle definido permitir.
            // Uma coluna sem variacao nao distingue ninguem, entao vira zero.
            $linha[$c] = $desvios[$c] > 0 ? ($ponto[$c] - $medias[$c]) / $desvios[$c] : 0.0; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        $normalizada[] = $linha; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve a matriz padronizada junto das medidas usadas, para poder desfazer a conta depois.
    return ['normalizada' => $normalizada, 'medias' => $medias, 'desvios' => $desvios]; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Calcula a distancia euclidiana entre dois pontos de mesma dimensao.
 */
function motorDistancia(array $a, array $b): float // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Acumula o quadrado da diferenca em cada dimensao.
    $soma = 0.0; // Armazena em soma o valor usado nas próximas etapas deste fluxo.
    // Percorre as dimensoes somando as diferencas ao quadrado.
    for ($i = 0, $n = count($a); $i < $n; $i++) { // Repete este bloco enquanto o controle definido permitir.
        $soma += ($a[$i] - $b[$i]) ** 2; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // A raiz da soma e a distancia em linha reta entre os dois pontos.
    return sqrt($soma); // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Escolhe os centros iniciais pelo metodo k-means++.
 *
 * O primeiro centro e sorteado e cada centro seguinte tem chance maior de cair longe
 * dos centros ja escolhidos. Isso evita que dois centros nascam praticamente no mesmo
 * lugar, situacao que faria o algoritmo convergir para um agrupamento ruim.
 */
function motorCentrosIniciais(array $pontos, int $k): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Fixa a semente do sorteio para que o resultado seja sempre o mesmo na mesma base.
    mt_srand(MOTOR_SEMENTE); // Executa esta instrução como parte da regra de negócio desta página.

    // Sorteia o primeiro centro entre todos os pontos disponiveis.
    $centros = [$pontos[mt_rand(0, count($pontos) - 1)]]; // Armazena em centros o valor usado nas próximas etapas deste fluxo.

    // Escolhe os demais centros, um de cada vez.
    while (count($centros) < $k) { // Repete este bloco enquanto o controle definido permitir.
        // Mede a distancia de cada ponto ate o centro mais proximo ja escolhido.
        $distancias = []; // Armazena em distancias o valor usado nas próximas etapas deste fluxo.
        // Percorre os pontos calculando a menor distancia ate os centros existentes.
        foreach ($pontos as $indice => $ponto) { // Percorre a coleção e processa cada registro individualmente.
            $menor = null; // Armazena em menor o valor usado nas próximas etapas deste fluxo.
            // Compara o ponto com cada centro ja definido.
            foreach ($centros as $centro) { // Percorre a coleção e processa cada registro individualmente.
                // Armazena em "distancia" o afastamento deste ponto ate este centro.
                $distancia = motorDistancia($ponto, $centro); // Armazena em distancia o valor usado nas próximas etapas deste fluxo.
                // Guarda a menor distancia encontrada ate agora.
                if ($menor === null || $distancia < $menor) { // Testa esta condição antes de executar o bloco correspondente.
                    $menor = $distancia; // Armazena em menor o valor usado nas próximas etapas deste fluxo.
                } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
            // O quadrado da distancia aumenta a chance de pontos distantes serem escolhidos.
            $distancias[$indice] = ($menor ?? 0.0) ** 2; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Armazena em "soma" o total das distancias, que define a faixa do sorteio.
        $soma = array_sum($distancias); // Armazena em soma o valor usado nas próximas etapas deste fluxo.

        // Quando todos os pontos coincidem com os centros, nao ha novo centro util a escolher.
        if ($soma <= 0) { // Testa esta condição antes de executar o bloco correspondente.
            break; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Sorteia um ponto com chance proporcional a sua distancia ao centro mais proximo.
        // O sorteio usa a faixa maxima do gerador para obter uma fracao entre 0 e 1.
        $alvo = (mt_rand(0, mt_getrandmax()) / mt_getrandmax()) * $soma; // Armazena em alvo o valor usado nas próximas etapas deste fluxo.
        $acumulado = 0.0; // Armazena em acumulado o valor usado nas próximas etapas deste fluxo.
        // Percorre as distancias ate ultrapassar o valor sorteado.
        foreach ($distancias as $indice => $distancia) { // Percorre a coleção e processa cada registro individualmente.
            $acumulado += $distancia; // Executa esta instrução como parte da regra de negócio desta página.
            // O ponto que ultrapassa o alvo e o escolhido como novo centro.
            if ($acumulado >= $alvo) { // Testa esta condição antes de executar o bloco correspondente.
                $centros[] = $pontos[$indice]; // Executa esta instrução como parte da regra de negócio desta página.
                break; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve os centros iniciais escolhidos.
    return $centros; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Executa o algoritmo de k-medias sobre a matriz recebida.
 *
 * Espera uma matriz em que cada linha e um ponto e cada coluna e uma caracteristica.
 * Devolve, para cada ponto, o grupo a que ele pertence, e os centros de cada grupo
 * convertidos de volta para as unidades originais.
 */
function motorKmeans(array $matriz, int $k = 4, int $maxIteracoes = 60): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Armazena em "quantidade" quantos pontos existem na matriz.
    $quantidade = count($matriz); // Armazena em quantidade o valor usado nas próximas etapas deste fluxo.

    // Com menos pontos do que grupos o agrupamento nao faz sentido.
    if ($quantidade < $k || $k < 2) { // Testa esta condição antes de executar o bloco correspondente.
        return ['grupos' => [], 'atribuicoes' => [], 'iteracoes' => 0, 'convergiu' => false]; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Padroniza as colunas para que todas as caracteristicas tenham o mesmo peso.
    $padronizacao = motorPadronizar($matriz); // Armazena em padronizacao o valor usado nas próximas etapas deste fluxo.
    $pontos = $padronizacao['normalizada']; // Armazena em pontos o valor usado nas próximas etapas deste fluxo.

    // Escolhe os centros iniciais de forma espalhada pelo conjunto.
    $centros = motorCentrosIniciais($pontos, $k); // Armazena em centros o valor usado nas próximas etapas deste fluxo.
    // Ajusta o numero de grupos caso nao tenha sido possivel formar todos os centros.
    $k = count($centros); // Armazena em k o valor usado nas próximas etapas deste fluxo.

    // Guarda a qual grupo cada ponto pertence.
    $atribuicoes = array_fill(0, $quantidade, -1); // Armazena em atribuicoes o valor usado nas próximas etapas deste fluxo.
    $iteracoes = 0; // Armazena em iteracoes o valor usado nas próximas etapas deste fluxo.
    $convergiu = false; // Armazena em convergiu o valor usado nas próximas etapas deste fluxo.

    // Repete a atribuicao e o recalculo ate estabilizar ou atingir o limite de rodadas.
    for ($rodada = 0; $rodada < $maxIteracoes; $rodada++) { // Repete este bloco enquanto o controle definido permitir.
        $iteracoes = $rodada + 1; // Armazena em iteracoes o valor usado nas próximas etapas deste fluxo.
        $mudou = false; // Armazena em mudou o valor usado nas próximas etapas deste fluxo.

        // PASSO 1: coloca cada ponto no grupo cujo centro esta mais proximo.
        foreach ($pontos as $indice => $ponto) { // Percorre a coleção e processa cada registro individualmente.
            $melhorGrupo = 0; // Armazena em melhorGrupo o valor usado nas próximas etapas deste fluxo.
            $menorDistancia = null; // Armazena em menorDistancia o valor usado nas próximas etapas deste fluxo.

            // Compara o ponto com o centro de cada grupo.
            for ($g = 0; $g < $k; $g++) { // Repete este bloco enquanto o controle definido permitir.
                // Armazena em "distancia" o afastamento ate o centro deste grupo.
                $distancia = motorDistancia($ponto, $centros[$g]); // Armazena em distancia o valor usado nas próximas etapas deste fluxo.
                // Guarda o grupo mais proximo encontrado ate agora.
                if ($menorDistancia === null || $distancia < $menorDistancia) { // Testa esta condição antes de executar o bloco correspondente.
                    $menorDistancia = $distancia; // Armazena em menorDistancia o valor usado nas próximas etapas deste fluxo.
                    $melhorGrupo = $g; // Armazena em melhorGrupo o valor usado nas próximas etapas deste fluxo.
                } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Marca que houve mudanca sempre que um ponto troca de grupo.
            if ($atribuicoes[$indice] !== $melhorGrupo) { // Testa esta condição antes de executar o bloco correspondente.
                $atribuicoes[$indice] = $melhorGrupo; // Executa esta instrução como parte da regra de negócio desta página.
                $mudou = true; // Armazena em mudou o valor usado nas próximas etapas deste fluxo.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // PASSO 2: recalcula cada centro como a media dos pontos que ficaram no grupo.
        for ($g = 0; $g < $k; $g++) { // Repete este bloco enquanto o controle definido permitir.
            // Reune os pontos que pertencem a este grupo.
            $membros = []; // Armazena em membros o valor usado nas próximas etapas deste fluxo.
            // Percorre as atribuicoes selecionando os pontos deste grupo.
            foreach ($atribuicoes as $indice => $grupo) { // Percorre a coleção e processa cada registro individualmente.
                if ($grupo === $g) { // Testa esta condição antes de executar o bloco correspondente.
                    $membros[] = $pontos[$indice]; // Executa esta instrução como parte da regra de negócio desta página.
                } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Um grupo vazio mantem o centro anterior, para nao desaparecer do calculo.
            if (!$membros) { // Testa esta condição antes de executar o bloco correspondente.
                continue; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Calcula a media de cada caracteristica entre os membros do grupo.
            $novoCentro = []; // Armazena em novoCentro o valor usado nas próximas etapas deste fluxo.
            // Percorre as dimensoes calculando a media de cada uma.
            for ($c = 0, $colunas = count($membros[0]); $c < $colunas; $c++) { // Repete este bloco enquanto o controle definido permitir.
                $novoCentro[$c] = motorMedia(array_column($membros, $c)); // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            $centros[$g] = $novoCentro; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Quando ninguem troca de grupo, o algoritmo chegou ao resultado final.
        if (!$mudou) { // Testa esta condição antes de executar o bloco correspondente.
            $convergiu = true; // Armazena em convergiu o valor usado nas próximas etapas deste fluxo.
            break; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Converte os centros de volta para as unidades originais, para poderem ser lidos por pessoas.
    $grupos = []; // Armazena em grupos o valor usado nas próximas etapas deste fluxo.
    // Percorre os grupos montando a descricao de cada um.
    for ($g = 0; $g < $k; $g++) { // Repete este bloco enquanto o controle definido permitir.
        // Reune os indices dos pontos que pertencem a este grupo.
        $membros = array_keys(array_filter( // Armazena em membros o valor usado nas próximas etapas deste fluxo.
            $atribuicoes, // Executa esta instrução como parte da regra de negócio desta página.
            static fn(int $grupo): bool => $grupo === $g // Executa esta instrução como parte da regra de negócio desta página.
        )); // Executa esta instrução como parte da regra de negócio desta página.

        // Desfaz a padronizacao para recuperar o valor em dias, visitas e reais.
        $centroOriginal = []; // Armazena em centroOriginal o valor usado nas próximas etapas deste fluxo.
        // Percorre as dimensoes revertendo a conta da padronizacao.
        foreach ($centros[$g] as $c => $valor) { // Percorre a coleção e processa cada registro individualmente.
            $centroOriginal[$c] = round( // Executa esta instrução como parte da regra de negócio desta página.
                ($valor * $padronizacao['desvios'][$c]) + $padronizacao['medias'][$c], // Executa esta instrução como parte da regra de negócio desta página.
                2 // Executa esta instrução como parte da regra de negócio desta página.
            ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        $grupos[$g] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'tamanho' => count($membros), // Executa esta instrução como parte da regra de negócio desta página.
            'membros' => $membros, // Executa esta instrução como parte da regra de negócio desta página.
            'centro'  => $centroOriginal, // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve o agrupamento completo junto das informacoes sobre a execucao.
    return [ // Devolve este resultado para quem chamou a função.
        'grupos'      => $grupos, // Executa esta instrução como parte da regra de negócio desta página.
        'atribuicoes' => $atribuicoes, // Executa esta instrução como parte da regra de negócio desta página.
        'iteracoes'   => $iteracoes, // Executa esta instrução como parte da regra de negócio desta página.
        'convergiu'   => $convergiu, // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

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
function motorContarConjuntos(array $transacoes, array $conjuntos): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Prepara o contador de cada conjunto candidato.
    $contagens = []; // Armazena em contagens o valor usado nas próximas etapas deste fluxo.
    // Percorre os candidatos iniciando todos em zero.
    foreach ($conjuntos as $chave => $conjunto) { // Percorre a coleção e processa cada registro individualmente.
        $contagens[$chave] = 0; // Executa esta instrução como parte da regra de negócio desta página.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Percorre as transacoes verificando quais conjuntos estao contidos em cada uma.
    foreach ($transacoes as $transacao) { // Percorre a coleção e processa cada registro individualmente.
        // Compara a transacao com cada conjunto candidato.
        foreach ($conjuntos as $chave => $conjunto) { // Percorre a coleção e processa cada registro individualmente.
            // O conjunto so conta quando TODOS os seus itens estao na transacao.
            if (!array_diff($conjunto, $transacao)) { // Testa esta condição antes de executar o bloco correspondente.
                $contagens[$chave]++; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve quantas vezes cada conjunto apareceu.
    return $contagens; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Gera os candidatos de tamanho N+1 a partir dos conjuntos frequentes de tamanho N.
 * Apenas conjuntos cujos pedacos ja se mostraram frequentes viram candidatos.
 */
function motorGerarCandidatos(array $frequentes, int $tamanho): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Reune todos os itens que aparecem nos conjuntos frequentes.
    $itens = []; // Armazena em itens o valor usado nas próximas etapas deste fluxo.
    // Percorre os conjuntos frequentes coletando seus itens.
    foreach ($frequentes as $conjunto) { // Percorre a coleção e processa cada registro individualmente.
        // Percorre os itens do conjunto adicionando os que ainda nao foram vistos.
        foreach ($conjunto as $item) { // Percorre a coleção e processa cada registro individualmente.
            if (!in_array($item, $itens, true)) { // Testa esta condição antes de executar o bloco correspondente.
                $itens[] = $item; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Monta os novos candidatos combinando cada conjunto frequente com cada item disponivel.
    $candidatos = []; // Armazena em candidatos o valor usado nas próximas etapas deste fluxo.
    // Percorre os conjuntos frequentes.
    foreach ($frequentes as $conjunto) { // Percorre a coleção e processa cada registro individualmente.
        // Tenta acrescentar cada item ao conjunto.
        foreach ($itens as $item) { // Percorre a coleção e processa cada registro individualmente.
            // Ignora itens que ja estao no conjunto.
            if (in_array($item, $conjunto, true)) { // Testa esta condição antes de executar o bloco correspondente.
                continue; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Monta o candidato e ordena para que a mesma combinacao tenha sempre a mesma chave.
            $candidato = $conjunto; // Armazena em candidato o valor usado nas próximas etapas deste fluxo.
            $candidato[] = $item; // Executa esta instrução como parte da regra de negócio desta página.
            sort($candidato); // Executa esta instrução como parte da regra de negócio desta página.

            // Descarta candidatos com tamanho diferente do esperado.
            if (count($candidato) !== $tamanho) { // Testa esta condição antes de executar o bloco correspondente.
                continue; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Usa o proprio conteudo como chave, o que elimina combinacoes repetidas.
            $candidatos[implode(' | ', $candidato)] = $candidato; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Devolve os candidatos unicos.
    return $candidatos; // Devolve este resultado para quem chamou a função.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

/**
 * Executa o algoritmo Apriori e devolve as regras de associacao encontradas.
 *
 * @param array $transacoes     Lista de visitas, cada uma com os servicos contratados nela.
 * @param float $suporteMinimo  Fracao minima de visitas em que o conjunto precisa aparecer.
 * @param float $confiancaMinima Confianca minima para a regra ser considerada util.
 */
function motorApriori(array $transacoes, float $suporteMinimo = 0.05, float $confiancaMinima = 0.30): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Armazena em "total" quantas visitas foram analisadas.
    $total = count($transacoes); // Armazena em total o valor usado nas próximas etapas deste fluxo.

    // Sem transacoes suficientes nao ha padrao estatistico para minerar.
    if ($total < 10) { // Testa esta condição antes de executar o bloco correspondente.
        return ['regras' => [], 'frequentes' => [], 'transacoes' => $total]; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Armazena em "minimoAbsoluto" quantas visitas correspondem ao suporte minimo pedido.
    $minimoAbsoluto = max(2, (int) ceil($suporteMinimo * $total)); // Armazena em minimoAbsoluto o valor usado nas próximas etapas deste fluxo.

    // PASSO 1: monta os conjuntos de um item e mantem apenas os frequentes.
    $candidatos = []; // Armazena em candidatos o valor usado nas próximas etapas deste fluxo.
    // Percorre as transacoes coletando todos os servicos distintos.
    foreach ($transacoes as $transacao) { // Percorre a coleção e processa cada registro individualmente.
        // Percorre os servicos da transacao criando um candidato para cada um.
        foreach ($transacao as $item) { // Percorre a coleção e processa cada registro individualmente.
            $candidatos[$item] = [$item]; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Conta em quantas visitas cada servico aparece.
    $contagens = motorContarConjuntos($transacoes, $candidatos); // Armazena em contagens o valor usado nas próximas etapas deste fluxo.

    // Guarda a contagem de todos os conjuntos frequentes encontrados em qualquer tamanho.
    $frequentesPorChave = []; // Armazena em frequentesPorChave o valor usado nas próximas etapas deste fluxo.
    // Guarda os conjuntos frequentes do tamanho atual, usados para gerar o proximo tamanho.
    $nivelAtual = []; // Armazena em nivelAtual o valor usado nas próximas etapas deste fluxo.

    // Separa os servicos que atingiram o suporte minimo.
    foreach ($candidatos as $chave => $conjunto) { // Percorre a coleção e processa cada registro individualmente.
        // Mantem apenas os conjuntos suficientemente comuns.
        if ($contagens[$chave] >= $minimoAbsoluto) { // Testa esta condição antes de executar o bloco correspondente.
            $frequentesPorChave[$chave] = $contagens[$chave]; // Executa esta instrução como parte da regra de negócio desta página.
            $nivelAtual[$chave] = $conjunto; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // PASSO 2: sobe de tamanho enquanto houver conjuntos frequentes para combinar.
    // O limite de tres itens mantem o custo baixo e cobre os combos que um pet shop consegue montar.
    for ($tamanho = 2; $tamanho <= 3; $tamanho++) { // Repete este bloco enquanto o controle definido permitir.
        // Sem pelo menos dois conjuntos frequentes nao ha o que combinar.
        if (count($nivelAtual) < 2) { // Testa esta condição antes de executar o bloco correspondente.
            break; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Gera os candidatos do proximo tamanho a partir dos frequentes do tamanho anterior.
        $candidatos = motorGerarCandidatos($nivelAtual, $tamanho); // Armazena em candidatos o valor usado nas próximas etapas deste fluxo.

        // Interrompe quando nao restam candidatos a avaliar.
        if (!$candidatos) { // Testa esta condição antes de executar o bloco correspondente.
            break; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Conta em quantas visitas cada candidato aparece por inteiro.
        $contagens = motorContarConjuntos($transacoes, $candidatos); // Armazena em contagens o valor usado nas próximas etapas deste fluxo.

        // Mantem apenas os candidatos que atingiram o suporte minimo.
        $nivelAtual = []; // Armazena em nivelAtual o valor usado nas próximas etapas deste fluxo.
        foreach ($candidatos as $chave => $conjunto) { // Percorre a coleção e processa cada registro individualmente.
            if ($contagens[$chave] >= $minimoAbsoluto) { // Testa esta condição antes de executar o bloco correspondente.
                $frequentesPorChave[$chave] = $contagens[$chave]; // Executa esta instrução como parte da regra de negócio desta página.
                $nivelAtual[$chave] = $conjunto; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // PASSO 3: transforma os conjuntos frequentes em regras do tipo "se A entao B".
    $regras = []; // Armazena em regras o valor usado nas próximas etapas deste fluxo.
    // Percorre os conjuntos frequentes procurando os que tem mais de um item.
    foreach ($frequentesPorChave as $chave => $contagem) { // Percorre a coleção e processa cada registro individualmente.
        // Reconstroi a lista de itens a partir da chave.
        $conjunto = explode(' | ', $chave); // Armazena em conjunto o valor usado nas próximas etapas deste fluxo.

        // Conjuntos de um item sozinho nao geram regra.
        if (count($conjunto) < 2) { // Testa esta condição antes de executar o bloco correspondente.
            continue; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Gera uma regra para cada item do conjunto, tratando-o como consequencia.
        foreach ($conjunto as $consequencia) { // Percorre a coleção e processa cada registro individualmente.
            // A condicao e formada por todos os outros itens do conjunto.
            $condicao = array_values(array_diff($conjunto, [$consequencia])); // Armazena em condicao o valor usado nas próximas etapas deste fluxo.
            // Reconstroi as chaves para consultar as contagens ja calculadas.
            $chaveCondicao = implode(' | ', $condicao); // Armazena em chaveCondicao o valor usado nas próximas etapas deste fluxo.
            $chaveConsequencia = $consequencia; // Armazena em chaveConsequencia o valor usado nas próximas etapas deste fluxo.

            // Ignora a regra quando alguma das partes nao foi registrada como frequente.
            if (!isset($frequentesPorChave[$chaveCondicao], $frequentesPorChave[$chaveConsequencia])) { // Testa esta condição antes de executar o bloco correspondente.
                continue; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // A confianca e a fracao das visitas com a condicao que tambem tiveram a consequencia.
            $confianca = $contagem / $frequentesPorChave[$chaveCondicao]; // Armazena em confianca o valor usado nas próximas etapas deste fluxo.

            // Descarta regras pouco confiaveis.
            if ($confianca < $confiancaMinima) { // Testa esta condição antes de executar o bloco correspondente.
                continue; // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // O suporte da consequencia isolada serve de base de comparacao para o lift.
            $suporteConsequencia = $frequentesPorChave[$chaveConsequencia] / $total; // Armazena em suporteConsequencia o valor usado nas próximas etapas deste fluxo.

            $regras[] = [ // Executa esta instrução como parte da regra de negócio desta página.
                'condicao'    => $condicao, // Executa esta instrução como parte da regra de negócio desta página.
                'consequencia' => $consequencia, // Executa esta instrução como parte da regra de negócio desta página.
                'ocorrencias' => $contagem, // Executa esta instrução como parte da regra de negócio desta página.
                'suporte'     => round($contagem / $total, 3), // Executa esta instrução como parte da regra de negócio desta página.
                'confianca'   => round($confianca, 3), // Executa esta instrução como parte da regra de negócio desta página.
                // Lift maior que 1 indica que a associacao e mais forte do que o acaso explicaria.
                'lift'        => $suporteConsequencia > 0 ? round($confianca / $suporteConsequencia, 2) : 0.0, // Executa esta instrução como parte da regra de negócio desta página.
            ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Ordena as regras da associacao mais forte para a mais fraca.
    usort($regras, static fn(array $a, array $b): int => $b['lift'] <=> $a['lift']); // Executa esta instrução como parte da regra de negócio desta página.

    // Devolve as regras encontradas junto do contexto em que foram mineradas.
    return [ // Devolve este resultado para quem chamou a função.
        'regras'     => $regras, // Executa esta instrução como parte da regra de negócio desta página.
        'frequentes' => $frequentesPorChave, // Executa esta instrução como parte da regra de negócio desta página.
        'transacoes' => $total, // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

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
function motorScoreAbandono(array $matrizRfm): array // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Sem historico nao ha comportamento para modelar.
    if (count($matrizRfm) < 3) { // Testa esta condição antes de executar o bloco correspondente.
        return ['pets' => [], 'intervalo_tipico' => 0.0]; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Reune os intervalos conhecidos para descobrir o ritmo tipico da casa.
    $intervalos = array_values(array_filter(array_column($matrizRfm, 'intervalo_medio'))); // Armazena em intervalos o valor usado nas próximas etapas deste fluxo.
    // Ordena os intervalos para poder extrair a mediana.
    sort($intervalos); // Executa esta instrução como parte da regra de negócio desta página.
    // A mediana representa o ritmo tipico melhor que a media, porque ignora casos extremos.
    $intervaloTipico = $intervalos // Armazena em intervaloTipico o valor usado nas próximas etapas deste fluxo.
        ? $intervalos[(int) floor(count($intervalos) / 2)] // Executa esta instrução como parte da regra de negócio desta página.
        : 30.0; // Executa esta instrução como parte da regra de negócio desta página.

    // Calcula os percentis de cada dimensao do modelo RFM.
    // A recencia entra invertida, porque nela o menor valor e o melhor.
    $percentisRecencia = motorPercentis(array_map( // Armazena em percentisRecencia o valor usado nas próximas etapas deste fluxo.
        static fn(array $item): float => -1 * $item['recencia'], // Executa esta instrução como parte da regra de negócio desta página.
        $matrizRfm // Executa esta instrução como parte da regra de negócio desta página.
    )); // Executa esta instrução como parte da regra de negócio desta página.
    $percentisFrequencia = motorPercentis(array_column($matrizRfm, 'frequencia')); // Armazena em percentisFrequencia o valor usado nas próximas etapas deste fluxo.
    $percentisValor = motorPercentis(array_column($matrizRfm, 'valor')); // Armazena em percentisValor o valor usado nas próximas etapas deste fluxo.

    // Pontua cada pet combinando o atraso relativo com a importancia dele para o negocio.
    $pets = []; // Armazena em pets o valor usado nas próximas etapas deste fluxo.
    // Percorre a matriz calculando o risco individual de cada pet.
    foreach ($matrizRfm as $indice => $item) { // Percorre a coleção e processa cada registro individualmente.
        // Usa o intervalo do proprio pet ou, na falta dele, o ritmo tipico da casa.
        $intervaloReferencia = $item['intervalo_medio'] ?? $intervaloTipico; // Armazena em intervaloReferencia o valor usado nas próximas etapas deste fluxo.
        // Protege contra intervalos invalidos que zerariam a divisao.
        $intervaloReferencia = max(1.0, (float) $intervaloReferencia); // Armazena em intervaloReferencia o valor usado nas próximas etapas deste fluxo.

        // Mede quantas vezes o pet ja ultrapassou o proprio intervalo entre visitas.
        $atrasoRelativo = $item['recencia'] / $intervaloReferencia; // Armazena em atrasoRelativo o valor usado nas próximas etapas deste fluxo.

        // Converte o atraso em uma nota de risco de 0 a 100.
        // Um atraso de 1x equivale a 30 pontos e um atraso de 3,3x satura a escala em 100.
        $risco = (int) min(100, round($atrasoRelativo * 30)); // Armazena em risco o valor usado nas próximas etapas deste fluxo.

        // A prioridade pondera o risco pelo quanto aquele pet representa em faturamento.
        // Um pet valioso em risco deve ser contatado antes de um pet de baixo valor no mesmo risco.
        $prioridade = (int) round($risco * (0.5 + (0.5 * $percentisValor[$indice]))); // Armazena em prioridade o valor usado nas próximas etapas deste fluxo.

        $pets[] = [ // Executa esta instrução como parte da regra de negócio desta página.
            'pet'                => $item['pet'], // Executa esta instrução como parte da regra de negócio desta página.
            'tutor'              => $item['tutor'], // Executa esta instrução como parte da regra de negócio desta página.
            'recencia'           => $item['recencia'], // Executa esta instrução como parte da regra de negócio desta página.
            'frequencia'         => $item['frequencia'], // Executa esta instrução como parte da regra de negócio desta página.
            'valor'              => $item['valor'], // Executa esta instrução como parte da regra de negócio desta página.
            'intervalo_proprio'  => $item['intervalo_medio'], // Executa esta instrução como parte da regra de negócio desta página.
            'intervalo_usado'    => round($intervaloReferencia, 1), // Executa esta instrução como parte da regra de negócio desta página.
            'atraso_relativo'    => round($atrasoRelativo, 1), // Executa esta instrução como parte da regra de negócio desta página.
            'risco'              => $risco, // Executa esta instrução como parte da regra de negócio desta página.
            'prioridade'         => $prioridade, // Executa esta instrução como parte da regra de negócio desta página.
            // Notas RFM classicas, de 1 a 5, uteis para conferir a classificacao.
            'nota_recencia'      => motorNotaQuintil($percentisRecencia[$indice]), // Executa esta instrução como parte da regra de negócio desta página.
            'nota_frequencia'    => motorNotaQuintil($percentisFrequencia[$indice]), // Executa esta instrução como parte da regra de negócio desta página.
            'nota_valor'         => motorNotaQuintil($percentisValor[$indice]), // Executa esta instrução como parte da regra de negócio desta página.
        ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Ordena da maior para a menor prioridade de contato.
    usort($pets, static fn(array $a, array $b): int => $b['prioridade'] <=> $a['prioridade']); // Executa esta instrução como parte da regra de negócio desta página.

    // Devolve a pontuacao junto do ritmo tipico usado como referencia.
    return [ // Devolve este resultado para quem chamou a função.
        'pets'             => $pets, // Executa esta instrução como parte da regra de negócio desta página.
        'intervalo_tipico' => round($intervaloTipico, 1), // Executa esta instrução como parte da regra de negócio desta página.
    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
