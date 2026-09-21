<?php
/**
 * ARQUIVO: config/analise.php
 * RESPONSABILIDADE: Transformar os dados brutos do banco em indicadores e padroes de comportamento.
 * Todas as funcoes aqui apenas consultam e agrupam informacoes; nenhuma delas altera dados.
 * O resultado e sempre um array PHP com numeros puros, que depois alimenta os graficos do Dashboard
 * e tambem o pacote enviado para a analise inteligente.
 *
 * OBSERVACAO IMPORTANTE: o banco do sistema e PostgreSQL, por isso as consultas usam a sintaxe
 * do Postgres (conversao com "::date", EXTRACT, FILTER e STRING_AGG).
 */

// Quantidade de dias sem retorno a partir da qual um pet e considerado afastado do Beauty Pet.
const ANALISE_DIAS_SEM_RETORNO = 60;

// Janela padrao, em dias, usada nas analises de padrao de movimento.
const ANALISE_JANELA_PADRAO = 90;

/**
 * Devolve a data de inicio de uma janela de analise contada para tras a partir de hoje.
 * Calcular a data no PHP evita montar expressoes de INTERVAL dentro do SQL.
 */
function analiseDataInicio(int $dias): string
{
    // Cria a data de hoje e retrocede a quantidade de dias solicitada.
    return (new DateTime('today'))->modify("-{$dias} days")->format('Y-m-d');
}

/**
 * Converte o numero do dia da semana devolvido pelo Postgres (0 = domingo) no nome em portugues.
 */
function analiseNomeDiaSemana(int $numero): string
{
    // Lista os nomes na mesma ordem usada pela funcao EXTRACT(DOW) do PostgreSQL.
    $nomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    // Devolve o nome correspondente ou um texto neutro caso o numero seja inesperado.
    return $nomes[$numero] ?? 'Indefinido';
}

/**
 * Converte o numero do mes em uma abreviacao em portugues, usada no grafico de evolucao.
 */
function analiseNomeMes(int $numero): string
{
    // Guarda as abreviacoes comecando pelo indice 1 para casar com o numero do mes.
    $nomes = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    // Devolve a abreviacao correspondente ou um traco caso o numero seja invalido.
    return $nomes[$numero] ?? '-';
}

/**
 * Monta os numeros do topo do Dashboard referentes a um unico dia.
 * Cada indice do array devolvido alimenta um card da tela.
 */
function resumoDoDia(PDO $pdo, string $data): array
{
    // Soma tudo o que foi lancado no fechamento daquele dia, ignorando a categoria financeira.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(vt.valor_total_lancamento), 0) AS faturamento
         FROM lancamentos_diarios l
         JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
         JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
         JOIN servicos s ON s.id = l.servico_id
         JOIN categorias_servico cs ON cs.id = s.categoria_id
         WHERE f.data_fechamento = :data
           AND LOWER(cs.nome) <> 'financeiro'"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':data' => $data]);
    // Armazena em "faturamento" o total financeiro do dia consultado.
    $faturamento = (float) $stmt->fetchColumn();

    // Conta quantos pets diferentes passaram pelo Beauty Pet no dia.
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT a.pet_id)
         FROM atendimentos a
         WHERE a.data_hora_entrada::date = :data
           AND a.status <> 'CANCELADO'"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':data' => $data]);
    // Armazena em "petsAtendidos" a quantidade de pets distintos atendidos no dia.
    $petsAtendidos = (int) $stmt->fetchColumn();

    // Separa quantos lancamentos do dia foram de banho e quantos foram de tosa.
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) FILTER (WHERE s.nome ILIKE '%banho%') AS banhos,
            COUNT(*) FILTER (WHERE s.nome ILIKE '%tosa%')  AS tosas
         FROM lancamentos_diarios l
         JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
         JOIN servicos s ON s.id = l.servico_id
         WHERE f.data_fechamento = :data"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':data' => $data]);
    // Armazena a linha com as duas contagens de servico para uso logo abaixo.
    $servicosDoDia = $stmt->fetch() ?: ['banhos' => 0, 'tosas' => 0];

    // Conta as solicitacoes de agendamento que ainda aguardam resposta da equipe.
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM agendamentos WHERE status = 'SOLICITADO'"
    );
    // Armazena em "agendamentosPendentes" o total de solicitacoes em aberto.
    $agendamentosPendentes = (int) $stmt->fetchColumn();

    // Conta quantos pets ativos estao afastados ha mais tempo do que o limite definido.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM (
            SELECT p.id
            FROM pets p
            JOIN clientes c ON c.id = p.cliente_id
            JOIN lancamentos_diarios l ON l.pet_id = p.id
            JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
            WHERE p.ativo = TRUE
              AND c.ativo = TRUE
            GROUP BY p.id
            HAVING (CURRENT_DATE - MAX(f.data_fechamento)) >= :dias
         ) AS afastados"
    );
    // Informa o valor como numero inteiro para que o banco compare com a diferenca de datas.
    $stmt->bindValue(':dias', ANALISE_DIAS_SEM_RETORNO, PDO::PARAM_INT);
    // Executa a instrucao preparada com o parametro ja vinculado.
    $stmt->execute();
    // Armazena em "petsSemRetorno" quantos pets estao sem voltar ao Beauty Pet.
    $petsSemRetorno = (int) $stmt->fetchColumn();

    // Devolve todos os indicadores reunidos em um unico array.
    return [
        'faturamento'            => $faturamento,
        'pets_atendidos'         => $petsAtendidos,
        'banhos'                 => (int) $servicosDoDia['banhos'],
        'tosas'                  => (int) $servicosDoDia['tosas'],
        'agendamentos_pendentes' => $agendamentosPendentes,
        'pets_sem_retorno'       => $petsSemRetorno,
    ];
}

/**
 * Devolve o faturamento de cada um dos ultimos dias, ja preenchendo com zero os dias sem movimento.
 * Alimenta o grafico de barras "Faturamento diario".
 */
function faturamentoUltimosDias(PDO $pdo, int $dias = 7): array
{
    // Define o primeiro dia da janela consultada.
    $inicio = analiseDataInicio($dias - 1);

    // Busca no banco apenas os dias que realmente tiveram fechamento registrado.
    $stmt = $pdo->prepare(
        "SELECT
            f.data_fechamento AS dia,
            COALESCE(SUM(vt.valor_total_lancamento), 0) AS total
         FROM fechamentos_diarios f
         LEFT JOIN lancamentos_diarios l ON l.fechamento_diario_id = f.id
         LEFT JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
         WHERE f.data_fechamento >= :inicio
         GROUP BY f.data_fechamento
         ORDER BY f.data_fechamento"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':inicio' => $inicio]);

    // Reorganiza o resultado usando a data como chave, para facilitar a consulta no laco seguinte.
    $totaisPorDia = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        $totaisPorDia[$linha['dia']] = (float) $linha['total'];
    }

    // Monta a lista final percorrendo todos os dias da janela, inclusive os sem movimento.
    $serie = [];
    // Percorre a quantidade de dias solicitada, do mais antigo para o mais recente.
    for ($i = $dias - 1; $i >= 0; $i--) {
        // Calcula a data correspondente a esta posicao da serie.
        $data = (new DateTime('today'))->modify("-{$i} days");
        // Armazena em "chave" a data no formato usado pelo banco.
        $chave = $data->format('Y-m-d');

        $serie[] = [
            'data'   => $chave,
            'rotulo' => analiseNomeDiaSemana((int) $data->format('w')),
            'total'  => $totaisPorDia[$chave] ?? 0.0,
        ];
    }

    // Devolve a serie pronta para ser desenhada no grafico.
    return $serie;
}

/**
 * Devolve o faturamento mes a mes dos ultimos meses.
 * Alimenta o grafico de linha "Evolucao mensal".
 */
function evolucaoMensal(PDO $pdo, int $meses = 6): array
{
    // Define o primeiro dia do mes mais antigo que deve entrar na analise.
    $inicio = (new DateTime('first day of this month'))
        ->modify('-' . ($meses - 1) . ' months')
        ->format('Y-m-d');

    // Agrupa os fechamentos por ano e mes para somar o faturamento de cada periodo.
    $stmt = $pdo->prepare(
        "SELECT
            TO_CHAR(f.data_fechamento, 'YYYY-MM') AS mes,
            COALESCE(SUM(vt.valor_total_lancamento), 0) AS total
         FROM fechamentos_diarios f
         LEFT JOIN lancamentos_diarios l ON l.fechamento_diario_id = f.id
         LEFT JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
         WHERE f.data_fechamento >= :inicio
         GROUP BY TO_CHAR(f.data_fechamento, 'YYYY-MM')
         ORDER BY mes"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':inicio' => $inicio]);

    // Reorganiza o resultado usando o mes como chave.
    $totaisPorMes = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        $totaisPorMes[$linha['mes']] = (float) $linha['total'];
    }

    // Monta a lista final garantindo que todos os meses da janela aparecam no grafico.
    $serie = [];
    // Percorre os meses do mais antigo para o mais recente.
    for ($i = $meses - 1; $i >= 0; $i--) {
        // Calcula o mes correspondente a esta posicao da serie.
        $mes = (new DateTime('first day of this month'))->modify("-{$i} months");
        // Armazena em "chave" o mes no formato usado pela consulta.
        $chave = $mes->format('Y-m');

        $serie[] = [
            'mes'    => $chave,
            'rotulo' => analiseNomeMes((int) $mes->format('n')),
            'total'  => $totaisPorMes[$chave] ?? 0.0,
        ];
    }

    // Devolve a serie pronta para ser desenhada no grafico.
    return $serie;
}

/**
 * Lista os servicos mais realizados dentro da janela informada.
 * Alimenta o grafico de rosca "Servicos mais realizados".
 */
function servicosMaisRealizados(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO, int $limite = 6): array
{
    // Define o primeiro dia da janela consultada.
    $inicio = analiseDataInicio($dias);

    // Conta quantas vezes cada servico foi lancado no periodo.
    $stmt = $pdo->prepare(
        "SELECT
            s.nome AS servico,
            COUNT(l.id) AS quantidade
         FROM lancamentos_diarios l
         JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
         JOIN servicos s ON s.id = l.servico_id
         JOIN categorias_servico cs ON cs.id = s.categoria_id
         WHERE f.data_fechamento >= :inicio
           AND LOWER(cs.nome) <> 'financeiro'
         GROUP BY s.nome
         ORDER BY quantidade DESC
         LIMIT :limite"
    );
    // Informa os valores um a um para poder definir o tipo inteiro do LIMIT.
    $stmt->bindValue(':inicio', $inicio);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    // Executa a instrucao preparada com os parametros ja vinculados.
    $stmt->execute();

    // Devolve a lista de servicos ordenada do mais procurado para o menos procurado.
    return $stmt->fetchAll();
}

/**
 * Soma o valor recebido por cada forma de pagamento na janela informada.
 * Alimenta as barras de proporcao "Formas de pagamento".
 */
function formasDePagamento(PDO $pdo, int $dias = 30): array
{
    // Define o primeiro dia da janela consultada.
    $inicio = analiseDataInicio($dias);

    // Agrupa os pagamentos nao estornados por forma de recebimento.
    $stmt = $pdo->prepare(
        "SELECT
            fp.nome AS forma,
            COALESCE(SUM(p.valor), 0) AS total
         FROM pagamentos p
         JOIN formas_pagamento fp ON fp.id = p.forma_pagamento_id
         WHERE p.estornado = FALSE
           AND p.data_hora_pagamento::date >= :inicio
         GROUP BY fp.nome
         ORDER BY total DESC"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':inicio' => $inicio]);

    // Devolve as formas de pagamento ordenadas da mais usada para a menos usada.
    return $stmt->fetchAll();
}

/**
 * Lista os atendimentos do dia com pet, servico, horario, valor e situacao.
 * Alimenta a tabela "Atendimentos do dia" na parte inferior do Dashboard.
 */
function atendimentosDoDia(PDO $pdo, string $data, int $limite = 8): array
{
    // Busca os agendamentos do dia ja com o nome do pet, a raca, os servicos e o valor lancado.
    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            a.data_hora_inicio,
            a.status,
            p.nome AS pet,
            p.foto_url,
            COALESCE(r.nome, e.nome) AS raca,
            COALESCE(itens.servicos, 'Serviço não informado') AS servicos,
            COALESCE(financeiro.valor, 0) AS valor
         FROM agendamentos a
         JOIN pets p ON p.id = a.pet_id
         JOIN especies e ON e.id = p.especie_id
         LEFT JOIN racas r ON r.id = p.raca_id
         LEFT JOIN (
            SELECT
                ai.agendamento_id,
                STRING_AGG(s.nome, ' + ' ORDER BY s.nome) AS servicos
            FROM agendamento_itens ai
            JOIN servicos s ON s.id = ai.servico_id
            GROUP BY ai.agendamento_id
         ) itens ON itens.agendamento_id = a.id
         LEFT JOIN (
            SELECT
                atd.agendamento_id,
                SUM(vt.valor_total_lancamento) AS valor
            FROM atendimentos atd
            JOIN lancamentos_diarios l ON l.atendimento_id = atd.id
            JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
            WHERE atd.agendamento_id IS NOT NULL
            GROUP BY atd.agendamento_id
         ) financeiro ON financeiro.agendamento_id = a.id
         WHERE a.data_hora_inicio::date = :data
           AND a.status <> 'RECUSADO'
         ORDER BY a.data_hora_inicio
         LIMIT :limite"
    );
    // Informa os valores um a um para poder definir o tipo inteiro do LIMIT.
    $stmt->bindValue(':data', $data);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    // Executa a instrucao preparada com os parametros ja vinculados.
    $stmt->execute();

    // Devolve os atendimentos do dia em ordem de horario.
    return $stmt->fetchAll();
}

/* ============================================================================
 * ANALISES DE PADRAO
 * Sao estas as cinco consultas que a analise inteligente interpreta para gerar
 * as observacoes e as sugestoes de promocao previstas na versao BETA.
 * ==========================================================================*/

/**
 * ANALISE 1: pets que estao ha mais tempo sem retornar ao Beauty Pet.
 * Considera como ultima visita a data do fechamento em que o pet teve o ultimo servico lancado.
 */
function petsSemRetorno(PDO $pdo, int $diasMinimos = ANALISE_DIAS_SEM_RETORNO, int $limite = 10): array
{
    // Agrupa os lancamentos por pet para descobrir a data da ultima passagem de cada um.
    $stmt = $pdo->prepare(
        "SELECT
            p.id AS pet_id,
            p.nome AS pet,
            c.nome AS tutor,
            MAX(f.data_fechamento) AS ultima_visita,
            (CURRENT_DATE - MAX(f.data_fechamento)) AS dias_sem_retorno
         FROM pets p
         JOIN clientes c ON c.id = p.cliente_id
         JOIN lancamentos_diarios l ON l.pet_id = p.id
         JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
         WHERE p.ativo = TRUE
           AND c.ativo = TRUE
         GROUP BY p.id, p.nome, c.nome
         HAVING (CURRENT_DATE - MAX(f.data_fechamento)) >= :dias
         ORDER BY dias_sem_retorno DESC
         LIMIT :limite"
    );
    // Informa os valores um a um para poder definir os tipos inteiros.
    $stmt->bindValue(':dias', $diasMinimos, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    // Executa a instrucao preparada com os parametros ja vinculados.
    $stmt->execute();

    // Devolve os pets comecando pelo que esta afastado ha mais tempo.
    return $stmt->fetchAll();
}

/**
 * ANALISE 2: dias da semana com menor movimento.
 * Compara a media de faturamento e de servicos de cada dia da semana dentro da janela.
 */
function diasDeMenorMovimento(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO): array
{
    // Define o primeiro dia da janela consultada.
    $inicio = analiseDataInicio($dias);

    // Agrupa os fechamentos pelo dia da semana para comparar o desempenho de cada um.
    $stmt = $pdo->prepare(
        "SELECT
            EXTRACT(DOW FROM f.data_fechamento)::int AS dia_semana,
            COUNT(DISTINCT f.data_fechamento) AS ocorrencias,
            COUNT(l.id) AS servicos,
            COALESCE(SUM(vt.valor_total_lancamento), 0) AS faturamento
         FROM fechamentos_diarios f
         LEFT JOIN lancamentos_diarios l ON l.fechamento_diario_id = f.id
         LEFT JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
         WHERE f.data_fechamento >= :inicio
         GROUP BY EXTRACT(DOW FROM f.data_fechamento)
         ORDER BY faturamento ASC"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':inicio' => $inicio]);

    // Acrescenta o nome do dia e a media por dia trabalhado a cada linha do resultado.
    $resultado = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        // Armazena em "ocorrencias" quantas vezes aquele dia da semana aconteceu na janela.
        $ocorrencias = max(1, (int) $linha['ocorrencias']);

        $resultado[] = [
            'dia_semana'         => analiseNomeDiaSemana((int) $linha['dia_semana']),
            'ocorrencias'        => (int) $linha['ocorrencias'],
            'servicos'           => (int) $linha['servicos'],
            'faturamento'        => (float) $linha['faturamento'],
            'media_por_dia'      => round((float) $linha['faturamento'] / $ocorrencias, 2),
            'media_servicos_dia' => round((int) $linha['servicos'] / $ocorrencias, 1),
        ];
    }

    // Devolve a lista comecando pelo dia de menor faturamento.
    return $resultado;
}

/**
 * ANALISE 3: horarios com menor movimento.
 * Usa o horario de inicio dos agendamentos, porque e onde o sistema registra a hora do atendimento.
 */
function horariosDeMenorMovimento(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO): array
{
    // Define o primeiro dia da janela consultada.
    $inicio = analiseDataInicio($dias);

    // Agrupa os agendamentos que realmente aconteceram pela hora de inicio.
    $stmt = $pdo->prepare(
        "SELECT
            EXTRACT(HOUR FROM a.data_hora_inicio)::int AS hora,
            COUNT(*) AS atendimentos
         FROM agendamentos a
         WHERE a.data_hora_inicio::date >= :inicio
           AND a.status IN ('CONFIRMADO', 'EM_ATENDIMENTO', 'CONCLUIDO')
         GROUP BY EXTRACT(HOUR FROM a.data_hora_inicio)
         ORDER BY atendimentos ASC"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':inicio' => $inicio]);

    // Acrescenta a faixa de horario ja formatada para facilitar a leitura na tela.
    $resultado = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        // Armazena em "hora" o numero da hora devolvido pelo banco.
        $hora = (int) $linha['hora'];

        $resultado[] = [
            'hora'         => $hora,
            'faixa'        => sprintf('%02dh às %02dh', $hora, $hora + 1),
            'atendimentos' => (int) $linha['atendimentos'],
        ];
    }

    // Devolve a lista comecando pelo horario mais vazio.
    return $resultado;
}

/**
 * ANALISE 4: servicos mais e menos procurados.
 * Inclui tambem os servicos ativos que nao tiveram nenhum lancamento no periodo.
 */
function servicosMaisEMenosProcurados(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO): array
{
    // Define o primeiro dia da janela consultada.
    $inicio = analiseDataInicio($dias);

    // Parte da tabela de servicos para que os servicos sem procura tambem aparecam com zero.
    $stmt = $pdo->prepare(
        "SELECT
            s.nome AS servico,
            cs.nome AS categoria,
            COUNT(l.id) AS quantidade,
            COALESCE(SUM(vt.valor_total_lancamento), 0) AS faturamento
         FROM servicos s
         JOIN categorias_servico cs ON cs.id = s.categoria_id
         LEFT JOIN lancamentos_diarios l
                ON l.servico_id = s.id
               AND l.fechamento_diario_id IN (
                    SELECT id FROM fechamentos_diarios WHERE data_fechamento >= :inicio
               )
         LEFT JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
         WHERE s.ativo = TRUE
           AND LOWER(cs.nome) <> 'financeiro'
         GROUP BY s.nome, cs.nome
         ORDER BY quantidade DESC, s.nome"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':inicio' => $inicio]);

    // Devolve a lista completa, do servico mais procurado ate o menos procurado.
    return $stmt->fetchAll();
}

/**
 * ANALISE 5: clientes que reduziram a frequencia de utilizacao dos servicos.
 * Compara quantas vezes o tutor veio na janela recente contra a janela imediatamente anterior.
 */
function clientesQueReduziramFrequencia(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO, int $limite = 10): array
{
    // Define o inicio da janela recente, que representa o comportamento atual do cliente.
    $inicioRecente = analiseDataInicio($dias);
    // Define o inicio da janela anterior, usada como base de comparacao.
    $inicioAnterior = analiseDataInicio($dias * 2);

    // Conta as visitas de cada tutor separadamente nas duas janelas de tempo.
    $stmt = $pdo->prepare(
        "SELECT
            c.nome AS tutor,
            COUNT(DISTINCT f.data_fechamento)
                FILTER (WHERE f.data_fechamento >= :recente_a) AS visitas_recentes,
            COUNT(DISTINCT f.data_fechamento)
                FILTER (WHERE f.data_fechamento < :recente_b) AS visitas_anteriores
         FROM clientes c
         JOIN pets p ON p.cliente_id = c.id
         JOIN lancamentos_diarios l ON l.pet_id = p.id
         JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
         WHERE c.ativo = TRUE
           AND f.data_fechamento >= :anterior
         GROUP BY c.id, c.nome
         HAVING COUNT(DISTINCT f.data_fechamento) FILTER (WHERE f.data_fechamento >= :recente_c)
              < COUNT(DISTINCT f.data_fechamento) FILTER (WHERE f.data_fechamento < :recente_d)
         ORDER BY
            COUNT(DISTINCT f.data_fechamento) FILTER (WHERE f.data_fechamento < :recente_e)
          - COUNT(DISTINCT f.data_fechamento) FILTER (WHERE f.data_fechamento >= :recente_f) DESC
         LIMIT :limite"
    );
    // O PostgreSQL nao aceita repetir o mesmo parametro, por isso cada ocorrencia recebe um nome proprio.
    $stmt->bindValue(':recente_a', $inicioRecente);
    $stmt->bindValue(':recente_b', $inicioRecente);
    $stmt->bindValue(':recente_c', $inicioRecente);
    $stmt->bindValue(':recente_d', $inicioRecente);
    $stmt->bindValue(':recente_e', $inicioRecente);
    $stmt->bindValue(':recente_f', $inicioRecente);
    $stmt->bindValue(':anterior', $inicioAnterior);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    // Executa a instrucao preparada com os parametros ja vinculados.
    $stmt->execute();

    // Acrescenta a queda absoluta de visitas para deixar o resultado pronto para leitura.
    $resultado = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        $resultado[] = [
            'tutor'              => $linha['tutor'],
            'visitas_recentes'   => (int) $linha['visitas_recentes'],
            'visitas_anteriores' => (int) $linha['visitas_anteriores'],
            'queda'              => (int) $linha['visitas_anteriores'] - (int) $linha['visitas_recentes'],
        ];
    }

    // Devolve a lista comecando pelo tutor que mais reduziu a frequencia.
    return $resultado;
}

/* ============================================================================
 * DADOS BRUTOS PARA OS ALGORITMOS
 *
 * As funcoes abaixo nao produzem texto nem conclusao: elas apenas entregam as
 * tabelas de numeros que os algoritmos de config/ia-motor.php recebem como
 * entrada. Separar assim permite testar o algoritmo sem depender do banco.
 * ==========================================================================*/

/**
 * Monta a matriz RFM de cada pet: Recencia, Frequencia e valor Monetario.
 * E a entrada do calculo de risco de abandono.
 *
 * Alem dos tres valores classicos, devolve o periodo em que o pet esteve ativo,
 * o que permite descobrir o intervalo tipico entre as visitas daquele pet.
 */
function matrizRfmPets(PDO $pdo): array
{
    // Agrupa todos os lancamentos por pet para extrair o comportamento historico de cada um.
    $stmt = $pdo->query(
        "SELECT
            p.id,
            p.nome AS pet,
            c.nome AS tutor,
            (CURRENT_DATE - MAX(f.data_fechamento)) AS recencia,
            COUNT(DISTINCT f.data_fechamento) AS frequencia,
            COALESCE(SUM(vt.valor_total_lancamento), 0) AS valor,
            (MAX(f.data_fechamento) - MIN(f.data_fechamento)) AS periodo_ativo
         FROM pets p
         JOIN clientes c ON c.id = p.cliente_id
         JOIN lancamentos_diarios l ON l.pet_id = p.id
         JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
         JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
         WHERE p.ativo = TRUE
           AND c.ativo = TRUE
         GROUP BY p.id, p.nome, c.nome"
    );

    // Converte os valores para numero e ja calcula o intervalo tipico entre visitas.
    $matriz = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        // Armazena em "frequencia" quantas vezes o pet visitou o Beauty Pet.
        $frequencia = (int) $linha['frequencia'];
        // Armazena em "periodoAtivo" quantos dias separam a primeira da ultima visita.
        $periodoAtivo = (int) $linha['periodo_ativo'];

        // Calcula o intervalo medio entre visitas dividindo o periodo pelos espacos entre elas.
        // Com apenas uma visita nao existe intervalo, e o valor fica nulo.
        $intervaloMedio = $frequencia > 1
            ? round($periodoAtivo / ($frequencia - 1), 1)
            : null;

        $matriz[] = [
            'id'              => (int) $linha['id'],
            'pet'             => $linha['pet'],
            'tutor'           => $linha['tutor'],
            'recencia'        => (int) $linha['recencia'],
            'frequencia'      => $frequencia,
            'valor'           => (float) $linha['valor'],
            'intervalo_medio' => $intervaloMedio,
        ];
    }

    // Devolve a matriz pronta para ser pontuada pelo algoritmo de risco de abandono.
    return $matriz;
}

/**
 * Monta a matriz RFM de cada tutor.
 * E a entrada do algoritmo de agrupamento, que descobre sozinho os perfis de cliente.
 */
function matrizRfmClientes(PDO $pdo): array
{
    // Agrupa todos os lancamentos por tutor, somando o comportamento de todos os pets dele.
    $stmt = $pdo->query(
        "SELECT
            c.id,
            c.nome AS tutor,
            (CURRENT_DATE - MAX(f.data_fechamento)) AS recencia,
            COUNT(DISTINCT f.data_fechamento) AS frequencia,
            COALESCE(SUM(vt.valor_total_lancamento), 0) AS valor
         FROM clientes c
         JOIN pets p ON p.cliente_id = c.id
         JOIN lancamentos_diarios l ON l.pet_id = p.id
         JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
         JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
         WHERE c.ativo = TRUE
         GROUP BY c.id, c.nome"
    );

    // Converte os valores para numero, que e o formato esperado pelo algoritmo.
    $matriz = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        $matriz[] = [
            'id'         => (int) $linha['id'],
            'tutor'      => $linha['tutor'],
            'recencia'   => (int) $linha['recencia'],
            'frequencia' => (int) $linha['frequencia'],
            'valor'      => (float) $linha['valor'],
        ];
    }

    // Devolve a matriz pronta para ser agrupada pelo algoritmo de k-medias.
    return $matriz;
}

/**
 * Monta a lista de transacoes usada pela mineracao de regras de associacao.
 *
 * Cada transacao representa uma visita: o conjunto de servicos que um mesmo pet
 * recebeu em um mesmo dia. E a partir desse conjunto que o algoritmo descobre
 * quais servicos costumam ser contratados juntos.
 */
function transacoesDeServicos(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO): array
{
    // Define o primeiro dia da janela consultada.
    $inicio = analiseDataInicio($dias);

    // Busca cada servico lancado junto do pet e da data, que juntos identificam a visita.
    $stmt = $pdo->prepare(
        "SELECT
            l.pet_id,
            f.data_fechamento,
            s.nome AS servico
         FROM lancamentos_diarios l
         JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
         JOIN servicos s ON s.id = l.servico_id
         JOIN categorias_servico cs ON cs.id = s.categoria_id
         WHERE f.data_fechamento >= :inicio
           AND LOWER(cs.nome) <> 'financeiro'
         ORDER BY l.pet_id, f.data_fechamento"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':inicio' => $inicio]);

    // Agrupa os servicos por visita, usando o pet e a data como identificador da transacao.
    $transacoes = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        // Armazena em "chave" o identificador da visita a que este servico pertence.
        $chave = $linha['pet_id'] . '|' . $linha['data_fechamento'];

        // Cria a transacao na primeira vez que a visita aparece.
        if (!isset($transacoes[$chave])) {
            $transacoes[$chave] = [];
        }

        // Evita repetir o mesmo servico dentro da mesma visita.
        if (!in_array($linha['servico'], $transacoes[$chave], true)) {
            $transacoes[$chave][] = $linha['servico'];
        }
    }

    // Descarta as chaves e devolve apenas a lista de conjuntos de servicos.
    return array_values($transacoes);
}

/**
 * Devolve o faturamento e a quantidade de servicos de cada dia com fechamento registrado.
 * E a entrada da deteccao de anomalia, que compara cada dia da semana com o comportamento geral.
 */
function serieDiariaFaturamento(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO): array
{
    // Define o primeiro dia da janela consultada.
    $inicio = analiseDataInicio($dias);

    // Soma o movimento de cada dia separadamente, sem agrupar por dia da semana.
    $stmt = $pdo->prepare(
        "SELECT
            f.data_fechamento AS dia,
            EXTRACT(DOW FROM f.data_fechamento)::int AS dia_semana,
            COUNT(l.id) AS servicos,
            COALESCE(SUM(vt.valor_total_lancamento), 0) AS total
         FROM fechamentos_diarios f
         LEFT JOIN lancamentos_diarios l ON l.fechamento_diario_id = f.id
         LEFT JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
         WHERE f.data_fechamento >= :inicio
         GROUP BY f.data_fechamento, EXTRACT(DOW FROM f.data_fechamento)
         ORDER BY f.data_fechamento"
    );
    // Executa a instrucao preparada enviando os parametros de forma separada e segura.
    $stmt->execute([':inicio' => $inicio]);

    // Converte os valores para numero antes de entregar ao algoritmo.
    $serie = [];
    // Percorre as linhas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($stmt->fetchAll() as $linha) {
        $serie[] = [
            'dia'        => $linha['dia'],
            'dia_semana' => (int) $linha['dia_semana'],
            'servicos'   => (int) $linha['servicos'],
            'total'      => (float) $linha['total'],
        ];
    }

    // Devolve a serie diaria completa.
    return $serie;
}

/**
 * Devolve quantos agendamentos aconteceram em cada hora do dia.
 * E a entrada da deteccao de anomalia aplicada aos horarios da agenda.
 */
function serieHorariaAgenda(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO): array
{
    // Reaproveita a analise de horarios, que ja devolve a contagem por faixa.
    $horarios = horariosDeMenorMovimento($pdo, $dias);

    // Reorganiza o resultado usando a hora como chave, que e o formato esperado pelo algoritmo.
    $serie = [];
    // Percorre as faixas retornadas e aplica a mesma regra a cada uma delas.
    foreach ($horarios as $item) {
        $serie[$item['hora']] = $item['atendimentos'];
    }

    // Devolve a contagem de atendimentos por hora.
    return $serie;
}

/**
 * Reune tudo o que a IA do Beauty Pet precisa: as analises descritivas que aparecem
 * na tela e as matrizes de numeros que alimentam os algoritmos.
 */
function montarPacoteAnalise(PDO $pdo, int $dias = ANALISE_JANELA_PADRAO): array
{
    // Executa cada consulta e guarda o resultado sob uma chave que identifica o assunto.
    return [
        'janela_dias'       => $dias,
        'gerado_em'         => date('Y-m-d H:i:s'),

        // Analises descritivas, que respondem diretamente aos itens do planejamento.
        'pets_sem_retorno'  => petsSemRetorno($pdo),
        'dias_fracos'       => diasDeMenorMovimento($pdo, $dias),
        'horarios_fracos'   => horariosDeMenorMovimento($pdo, $dias),
        'servicos'          => servicosMaisEMenosProcurados($pdo, $dias),
        'clientes_em_queda' => clientesQueReduziramFrequencia($pdo, $dias),

        // Matrizes de numeros consumidas pelos algoritmos de config/ia-motor.php.
        'rfm_pets'          => matrizRfmPets($pdo),
        'rfm_clientes'      => matrizRfmClientes($pdo),
        'transacoes'        => transacoesDeServicos($pdo, $dias),
        'serie_diaria'      => serieDiariaFaturamento($pdo, $dias),
        'serie_horaria'     => serieHorariaAgenda($pdo, $dias),
        'serie_mensal'      => evolucaoMensal($pdo, 6),
    ];
}
