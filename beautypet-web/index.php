<?php
/**
 * ARQUIVO: index.php
 * RESPONSABILIDADE: Tela principal do sistema (Dashboard).
 *
 * A pagina e dividida em cinco abas, para que a equipe encontre cada assunto no seu lugar:
 *   1. Visao geral    - os indicadores do dia e os atendimentos de hoje.
 *   2. Interpretacoes - o que os algoritmos encontraram nos dados.
 *   3. Graficos       - o desempenho do periodo em forma de grafico.
 *   4. Sugestoes      - o que fazer a partir do que foi encontrado.
 *   5. Assistente     - um espaco para perguntar sobre os dados com as proprias palavras.
 *
 * As consultas ficam em config/analise.php, a interpretacao em config/ia.php e o entendimento
 * das perguntas em config/assistente.php, para que esta pagina cuide apenas da apresentacao.
 */

// Importa o arquivo de configuracao do sistema.
// O __DIR__ representa o diretorio onde este arquivo PHP esta localizado.
require __DIR__ . '/config/config.php';

// Importa as funcoes que consultam o banco e agrupam os dados das analises.
require __DIR__ . '/config/analise.php';

// Define o titulo que sera utilizado para identificar a pagina.
$tituloPagina = 'Dashboard';

// Define qual pagina esta ativa no sistema, para o menu destacar o item correspondente.
$paginaAtiva = 'dashboard';

// Armazena em "hoje" a data de referencia usada pelos indicadores do topo.
$hoje = date('Y-m-d');

// Executa as consultas que alimentam cada bloco visual da tela.
$resumo = resumoDoDia($pdo, $hoje);
$serieDiaria = faturamentoUltimosDias($pdo, 7);
$serieMensal = evolucaoMensal($pdo, 6);
$servicosTop = servicosMaisRealizados($pdo);
$pagamentos = formasDePagamento($pdo);
$atendimentos = atendimentosDoDia($pdo, $hoje);

// Lista os pets que estao sem retornar, usada quando o card de alerta e aberto.
// O limite alto existe apenas para proteger a tela de uma lista gigante.
$petsAfastados = petsSemRetorno($pdo, ANALISE_DIAS_SEM_RETORNO, 500);

// Soma o total recebido no periodo para poder calcular a porcentagem de cada forma de pagamento.
$totalPagamentos = array_sum(array_map(
    static fn(array $linha): float => (float) $linha['total'],
    $pagamentos
));

// Monta a data por extenso exibida abaixo do titulo da pagina.
$diasDaSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$mesesDoAno = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$dataAtual = new DateTime('today');
$dataPorExtenso = sprintf(
    '%s, %d de %s de %d',
    $diasDaSemana[(int) $dataAtual->format('w')],
    (int) $dataAtual->format('j'),
    $mesesDoAno[(int) $dataAtual->format('n')],
    (int) $dataAtual->format('Y')
);

// Descreve as abas do Dashboard em um unico lugar, para que a barra de navegacao
// e os paineis correspondentes nunca saiam do lugar um do outro.
$abasDashboard = [
    ['chave' => 'visao-geral',    'rotulo' => 'Visão geral',     'icone' => 'fa-solid fa-table-columns'],
    ['chave' => 'interpretacoes', 'rotulo' => 'Interpretações',  'icone' => 'fa-solid fa-magnifying-glass-chart'],
    ['chave' => 'graficos',       'rotulo' => 'Gráficos',        'icone' => 'fa-solid fa-chart-column'],
    ['chave' => 'sugestoes',      'rotulo' => 'Sugestões da IA', 'icone' => 'fa-solid fa-tags'],
    ['chave' => 'assistente',     'rotulo' => 'Assistente',      'icone' => 'fa-regular fa-comments'],
];

// Importa o arquivo responsavel pelo topo da pagina.
require __DIR__ . '/includes/topo.php';
?>

<!-- Container principal do Dashboard. -->
<div class="dashboard">

    <!-- Faixa de destaque com o titulo da pagina e a barra de abas. -->
    <section class="dashboard-destaque">
        <div class="dashboard-titulo">
            <h1>Dashboard</h1>
            <p>Beauty Pet &middot; <?= escapar($dataPorExtenso) ?></p>
        </div>

        <!--
            Barra de abas. Cada botao mostra um painel diferente sem recarregar a pagina.
            O atributo data-aba liga o botao ao painel de mesma chave.
        -->
        <nav class="dashboard-abas" role="tablist" aria-label="Seções do Dashboard">
            <?php foreach ($abasDashboard as $indice => $aba): ?>
                <button
                    type="button"
                    class="dashboard-aba <?= $indice === 0 ? 'aba-ativa' : '' ?>"
                    role="tab"
                    id="botao-aba-<?= escapar($aba['chave']) ?>"
                    data-aba="<?= escapar($aba['chave']) ?>"
                    aria-controls="painel-<?= escapar($aba['chave']) ?>"
                    aria-selected="<?= $indice === 0 ? 'true' : 'false' ?>">
                    <i class="<?= escapar($aba['icone']) ?>"></i>
                    <span><?= escapar($aba['rotulo']) ?></span>
                </button>
            <?php endforeach; ?>
        </nav>
    </section>

    <!-- ====================================================================
         ABA 1: VISAO GERAL
         Responde as perguntas rapidas do balcao: quanto entrou, quantos pets
         passaram e quem ainda esta por vir hoje.
         ================================================================= -->
    <section class="aba-painel aba-painel-ativo" id="painel-visao-geral" role="tabpanel" aria-labelledby="botao-aba-visao-geral">

        <!-- Cards com os numeros do dia. Cada card responde a uma pergunta rapida da equipe. -->
        <div class="kpi-grid">
            <article class="kpi-card">
                <span class="kpi-icone kpi-icone-verde"><i class="fa-solid fa-dollar-sign"></i></span>
                <strong class="kpi-valor"><?= escapar(formatarMoeda($resumo['faturamento'])) ?></strong>
                <small class="kpi-rotulo">Faturamento do dia</small>
            </article>

            <article class="kpi-card">
                <span class="kpi-icone kpi-icone-azul"><i class="fa-solid fa-paw"></i></span>
                <strong class="kpi-valor"><?= (int) $resumo['pets_atendidos'] ?></strong>
                <small class="kpi-rotulo">Pets atendidos hoje</small>
            </article>

            <article class="kpi-card">
                <span class="kpi-icone kpi-icone-ciano"><i class="fa-solid fa-shower"></i></span>
                <strong class="kpi-valor"><?= (int) $resumo['banhos'] ?></strong>
                <small class="kpi-rotulo">Banhos realizados</small>
            </article>

            <article class="kpi-card">
                <span class="kpi-icone kpi-icone-roxo"><i class="fa-solid fa-scissors"></i></span>
                <strong class="kpi-valor"><?= (int) $resumo['tosas'] ?></strong>
                <small class="kpi-rotulo">Tosas realizadas</small>
            </article>

            <article class="kpi-card">
                <span class="kpi-icone kpi-icone-amarelo"><i class="fa-regular fa-clock"></i></span>
                <strong class="kpi-valor"><?= (int) $resumo['agendamentos_pendentes'] ?></strong>
                <small class="kpi-rotulo">Agendamentos pendentes</small>
            </article>

            <!--
                Card de atencao: e o unico que nasce de uma analise, e nao de uma contagem do dia.
                Diferente dos outros, ele e um botao: o clique abre a lista com os nomes dos pets.
            -->
            <button
                type="button"
                class="kpi-card kpi-card-alerta kpi-card-botao"
                id="botaoPetsAfastados"
                aria-haspopup="dialog"
                aria-controls="modalPetsAfastados"
                <?= $petsAfastados ? '' : 'disabled' ?>>
                <span class="kpi-icone kpi-icone-vermelho"><i class="fa-solid fa-heart-crack"></i></span>
                <strong class="kpi-valor"><?= (int) $resumo['pets_sem_retorno'] ?></strong>
                <small class="kpi-rotulo">Pets sem retornar há mais de <?= ANALISE_DIAS_SEM_RETORNO ?> dias</small>

                <?php if ($petsAfastados): ?>
                    <small class="kpi-dica"><i class="fa-solid fa-list-ul"></i> Clique para ver os nomes</small>
                <?php endif; ?>
            </button>
        </div>

        <!--
            Lista dos pets afastados, mostrada por cima da tela quando o card de alerta e clicado.
            O conteudo ja vem pronto do PHP, entao a lista abre sem esperar nenhuma consulta.
        -->
        <div class="lista-modal" id="modalPetsAfastados" aria-hidden="true">
            <div class="lista-modal-caixa" role="dialog" aria-modal="true" aria-labelledby="tituloPetsAfastados">
                <header class="lista-modal-topo">
                    <div>
                        <h2 id="tituloPetsAfastados">Pets sem retornar</h2>
                        <small>
                            <?= count($petsAfastados) ?> pet<?= count($petsAfastados) === 1 ? '' : 's' ?>
                            sem vir há mais de <?= ANALISE_DIAS_SEM_RETORNO ?> dias, do mais afastado para o menos
                        </small>
                    </div>

                    <button class="lista-modal-fechar" id="fecharPetsAfastados" type="button" aria-label="Fechar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <!-- Campo que filtra a lista pelo nome do pet ou do tutor, sem recarregar a pagina. -->
                <div class="lista-modal-busca">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="search"
                        id="filtroPetsAfastados"
                        autocomplete="off"
                        placeholder="Filtrar por pet ou tutor..."
                        aria-label="Filtrar a lista por pet ou tutor">
                </div>

                <ul class="lista-modal-itens" id="listaPetsAfastados">
                    <?php foreach ($petsAfastados as $petAfastado): ?>
                        <?php
                        // Texto usado pelo filtro da lista. A extensao mbstring nem sempre esta
                        // ligada no servidor, por isso o sistema confere antes de usar.
                        $buscaPetAfastado = $petAfastado['pet'] . ' ' . $petAfastado['tutor'];
                        $buscaPetAfastado = function_exists('mb_strtolower')
                            ? mb_strtolower($buscaPetAfastado, 'UTF-8')
                            : strtolower($buscaPetAfastado);
                        ?>
                        <li class="lista-modal-item" data-busca="<?= escapar($buscaPetAfastado) ?>">
                            <a href="<?= BASE_URL ?>/pages/ficha-pet.php?id=<?= (int) $petAfastado['pet_id'] ?>">
                                <span class="lista-modal-iniciais"><?= escapar(iniciaisNome($petAfastado['pet'])) ?></span>

                                <span class="lista-modal-identidade">
                                    <strong><?= escapar($petAfastado['pet']) ?></strong>
                                    <small>Tutor: <?= escapar($petAfastado['tutor']) ?></small>
                                </span>

                                <span class="lista-modal-numero">
                                    <strong><?= (int) $petAfastado['dias_sem_retorno'] ?> dias</strong>
                                    <small>última visita em <?= escapar(formatarData($petAfastado['ultima_visita'])) ?></small>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Aviso que aparece quando o filtro nao encontra nenhum pet. -->
                <p class="painel-vazio lista-modal-vazio" id="vazioPetsAfastados" hidden>
                    Nenhum pet encontrado com esse nome.
                </p>

                <?php if (count($petsAfastados) < (int) $resumo['pets_sem_retorno']): ?>
                    <p class="lista-modal-nota">
                        Mostrando os <?= count($petsAfastados) ?> mais afastados de um total de
                        <?= (int) $resumo['pets_sem_retorno'] ?>.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lista dos atendimentos do dia, na mesma ordem em que acontecem na agenda. -->
        <section class="card-painel">
            <div class="card-painel-cabecalho">
                <div>
                    <h2>Atendimentos do dia</h2>
                    <small class="card-painel-nota"><?= escapar($dataPorExtenso) ?></small>
                </div>
                <a class="link-secundario" href="<?= BASE_URL ?>/pages/agenda.php">
                    Ver agenda completa <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <?php if ($atendimentos): ?>
                <ul class="atendimentos-lista">
                    <?php foreach ($atendimentos as $atendimento): ?>
                        <?php
                        // Monta o caminho da foto do pet a partir da raiz do sistema.
                        $fotoRelativa = trim((string) ($atendimento['foto_url'] ?? ''));
                        $fotoPet = $fotoRelativa !== ''
                            ? BASE_URL . '/' . ltrim(str_replace('\\', '/', $fotoRelativa), '/')
                            : null;
                        ?>
                        <li class="atendimento-item">
                            <div class="atendimento-pet">
                                <?php if ($fotoPet !== null): ?>
                                    <img class="atendimento-foto" src="<?= escapar($fotoPet) ?>" alt="Foto de <?= escapar($atendimento['pet']) ?>">
                                <?php else: ?>
                                    <span class="atendimento-foto atendimento-foto-vazia">
                                        <?= escapar(iniciaisNome($atendimento['pet'])) ?>
                                    </span>
                                <?php endif; ?>

                                <div class="atendimento-identidade">
                                    <strong><?= escapar($atendimento['pet']) ?></strong>
                                    <small><?= escapar($atendimento['raca']) ?></small>
                                </div>
                            </div>

                            <span class="atendimento-valor"><?= escapar(formatarMoeda($atendimento['valor'])) ?></span>
                            <span class="atendimento-servico"><?= escapar($atendimento['servicos']) ?></span>
                            <span class="atendimento-hora"><?= escapar(formatarHoraAgenda($atendimento['data_hora_inicio'])) ?></span>

                            <span class="etiqueta etiqueta-<?= escapar(classeStatusAgendamento($atendimento['status'])) ?>">
                                <?= escapar(nomeStatusAgendamento($atendimento['status'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="painel-vazio">Nenhum atendimento agendado para hoje.</p>
            <?php endif; ?>
        </section>
    </section>

    <!-- ====================================================================
         ABA 2: INTERPRETACOES
         Mostra a leitura geral do periodo e os padroes que os algoritmos
         encontraram, cada um com o numero que sustenta a conclusao.
         O conteudo chega depois do carregamento da pagina, por meio de
         pages/ia-insights.php, para que a tela apareca imediatamente.
         ================================================================= -->
    <section class="aba-painel" id="painel-interpretacoes" role="tabpanel" aria-labelledby="botao-aba-interpretacoes">
        <article class="card-painel painel-ia">
            <header class="painel-ia-topo">
                <div class="painel-ia-identidade">
                    <span class="painel-ia-selo"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                    <div>
                        <h2>Análise Inteligente</h2>
                        <small class="js-ia-situacao">Lendo o movimento do Beauty Pet...</small>
                    </div>
                </div>

                <button class="painel-ia-botao js-ia-atualizar" type="button">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    <span>Atualizar análise</span>
                </button>
            </header>

            <!-- Texto de leitura geral do periodo, preenchido pelo JavaScript. -->
            <p class="painel-ia-resumo" id="iaResumo">
                Lendo o movimento do Beauty Pet...
            </p>

            <div class="painel-ia-bloco">
                <h3><i class="fa-solid fa-magnifying-glass-chart"></i> Padrões encontrados</h3>
                <div class="ia-lista" id="iaObservacoes">
                    <div class="ia-carregando">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                    </div>
                </div>
            </div>
        </article>
    </section>

    <!-- ====================================================================
         ABA 3: GRAFICOS
         Reune o desempenho do periodo. Os graficos so sao desenhados quando
         esta aba e aberta pela primeira vez, porque a biblioteca precisa que
         a area ja esteja visivel para calcular o tamanho certo.
         ================================================================= -->
    <section class="aba-painel" id="painel-graficos" role="tabpanel" aria-labelledby="botao-aba-graficos">

        <!-- Primeira linha de graficos: faturamento por dia e distribuicao dos servicos. -->
        <div class="dashboard-linha dashboard-linha-principal">
            <article class="card-painel">
                <h2>Faturamento diário</h2>
                <small class="card-painel-nota">Últimos 7 dias</small>
                <div class="grafico-area">
                    <canvas id="graficoFaturamento"></canvas>
                </div>
            </article>

            <article class="card-painel">
                <h2>Serviços mais realizados</h2>
                <small class="card-painel-nota">Últimos <?= ANALISE_JANELA_PADRAO ?> dias</small>
                <?php if ($servicosTop): ?>
                    <div class="grafico-rosca">
                        <canvas id="graficoServicos"></canvas>
                    </div>
                    <ul class="legenda-lista" id="legendaServicos"></ul>
                <?php else: ?>
                    <p class="painel-vazio">Nenhum serviço lançado no período.</p>
                <?php endif; ?>
            </article>
        </div>

        <!-- Segunda linha de graficos: evolucao mensal e formas de pagamento. -->
        <div class="dashboard-linha dashboard-linha-principal">
            <article class="card-painel">
                <h2>Evolução mensal</h2>
                <small class="card-painel-nota">Últimos 6 meses</small>
                <div class="grafico-area">
                    <canvas id="graficoEvolucao"></canvas>
                </div>
            </article>

            <article class="card-painel">
                <h2>Formas de pagamento</h2>
                <small class="card-painel-nota">Últimos 30 dias</small>

                <?php if ($pagamentos && $totalPagamentos > 0): ?>
                    <ul class="barras-lista">
                        <?php foreach ($pagamentos as $formaPagamento): ?>
                            <?php
                            // Calcula quanto esta forma representa do total recebido no periodo.
                            $percentual = round(((float) $formaPagamento['total'] / $totalPagamentos) * 100);
                            ?>
                            <li class="barra-item">
                                <div class="barra-cabecalho">
                                    <span><?= escapar($formaPagamento['forma']) ?></span>
                                    <strong><?= $percentual ?>%</strong>
                                </div>
                                <div class="barra-trilho">
                                    <div class="barra-preenchimento" style="width: <?= $percentual ?>%"></div>
                                </div>
                                <small class="barra-valor"><?= escapar(formatarMoeda($formaPagamento['total'])) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="painel-vazio">Nenhum pagamento registrado no período.</p>
                <?php endif; ?>
            </article>
        </div>
    </section>

    <!-- ====================================================================
         ABA 4: SUGESTOES DA IA
         Mostra o que fazer a partir do que foi encontrado na aba de
         Interpretacoes. Usa a mesma analise, sem consultar o banco de novo.
         ================================================================= -->
    <section class="aba-painel" id="painel-sugestoes" role="tabpanel" aria-labelledby="botao-aba-sugestoes">
        <article class="card-painel painel-ia">
            <header class="painel-ia-topo">
                <div class="painel-ia-identidade">
                    <span class="painel-ia-selo"><i class="fa-solid fa-tags"></i></span>
                    <div>
                        <h2>Sugestões de promoção</h2>
                        <small class="js-ia-situacao">Lendo o movimento do Beauty Pet...</small>
                    </div>
                </div>

                <button class="painel-ia-botao js-ia-atualizar" type="button">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    <span>Atualizar análise</span>
                </button>
            </header>

            <div class="painel-ia-bloco">
                <div class="ia-lista" id="iaPromocoes">
                    <div class="ia-carregando">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                    </div>
                </div>
            </div>
        </article>
    </section>

    <!-- ====================================================================
         ABA 5: ASSISTENTE
         Espaco para a atendente perguntar com as proprias palavras. A resposta
         vem de pages/ia-assistente.php, que consulta as mesmas analises do
         Dashboard. Nenhuma pergunta digitada vira consulta ao banco.
         ================================================================= -->
    <section class="aba-painel" id="painel-assistente" role="tabpanel" aria-labelledby="botao-aba-assistente">
        <article class="card-painel assistente">
            <header class="painel-ia-topo">
                <div class="painel-ia-identidade">
                    <span class="painel-ia-selo"><i class="fa-regular fa-comments"></i></span>
                    <div>
                        <h2>Assistente Beauty Pet</h2>
                        <small>Pergunte com as suas palavras sobre os pets, a agenda e o movimento da casa.</small>
                    </div>
                </div>
            </header>

            <!-- Area onde as perguntas e as respostas vao aparecendo, uma abaixo da outra. -->
            <div class="assistente-conversa" id="assistenteConversa" aria-live="polite"></div>

            <!-- Atalhos com perguntas de exemplo, atualizados a cada resposta. -->
            <div class="assistente-sugestoes" id="assistenteSugestoes"></div>

            <!-- Campo de escrita da pergunta. -->
            <form class="assistente-form" id="assistenteForm" autocomplete="off">
                <input
                    class="assistente-campo"
                    type="text"
                    id="assistentePergunta"
                    maxlength="300"
                    placeholder="Ex.: quanto faturamos hoje?"
                    aria-label="Escreva a sua pergunta">

                <button class="assistente-enviar" type="submit" id="assistenteEnviar" aria-label="Enviar pergunta">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </article>
    </section>
</div>

<!-- Biblioteca responsavel por desenhar os graficos desta pagina. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>

<script>
// Recebe do PHP os dados ja preparados para cada grafico da tela.
const dadosFaturamento = <?= json_encode($serieDiaria, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const dadosEvolucao = <?= json_encode($serieMensal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const dadosServicos = <?= json_encode($servicosTop, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

// Endereco da pagina que devolve a analise inteligente em formato JSON.
const enderecoAnalise = "<?= BASE_URL ?>/pages/ia-insights.php";

// Endereco da pagina que responde as perguntas do Assistente.
const enderecoAssistente = "<?= BASE_URL ?>/pages/ia-assistente.php";

// Cores usadas nos graficos, mantidas juntas para facilitar qualquer ajuste visual.
const coresGrafico = {
    barra: "#6c63ff",
    linha: "#ef3028",
    fatias: ["#f472b6", "#1e3a8a", "#4ade80", "#fbbf24", "#60a5fa", "#a78bfa"]
};

// Formata um numero como moeda brasileira, usado nos rotulos dos graficos.
const formatarReal = valor => "R$ " + Number(valor).toLocaleString("pt-BR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
});


/* ============================================================
 * NAVEGACAO ENTRE AS ABAS
 * ==========================================================*/

// Guarda as referencias dos botoes e dos paineis das abas.
const botoesAba = document.querySelectorAll(".dashboard-aba");
const paineisAba = document.querySelectorAll(".aba-painel");
let graficosDesenhados = false;
// Mostra o painel pedido e destaca o botao correspondente.
function abrirAba(chave) {
    // Procura o botao da aba pedida para saber se ela realmente existe.
    const botaoDestino = document.querySelector('.dashboard-aba[data-aba="' + chave + '"]');

    // Interrompe quando a chave recebida nao corresponde a nenhuma aba da tela.
    if (!botaoDestino) return;

    // Destaca apenas o botao da aba escolhida.
    botoesAba.forEach(botao => {
        const ativo = botao.dataset.aba === chave;
        botao.classList.toggle("aba-ativa", ativo);
        botao.setAttribute("aria-selected", ativo ? "true" : "false");
    });

    // Mostra apenas o painel da aba escolhida.
    paineisAba.forEach(painel => {
        painel.classList.toggle("aba-painel-ativo", painel.id === "painel-" + chave);
    });

    // Guarda a aba aberta no endereco, para que atualizar a pagina volte para ela.
    history.replaceState(null, "", "#" + chave);

    // Desenha os graficos na primeira vez que esta aba aparece na tela.
    if (chave === "graficos") desenharGraficos();

    // Coloca o cursor no campo de pergunta assim que o Assistente aparece.
    if (chave === "assistente") {
        const campo = document.querySelector("#assistentePergunta");
        if (campo) campo.focus();
    }
}

// Liga cada botao da barra a abertura do seu painel.
botoesAba.forEach(botao => {
    botao.addEventListener("click", () => abrirAba(botao.dataset.aba));
});

// Abre a aba indicada no endereco da pagina, quando houver uma.
if (window.location.hash) {
    abrirAba(window.location.hash.replace("#", ""));
}


/* ============================================================
 * LISTA DE PETS SEM RETORNAR
 *
 * O card de alerta mostra apenas a quantidade. O clique abre a lista
 * com os nomes, que ja veio pronta do PHP junto com a pagina.
 * ==========================================================*/

// Guarda as referencias dos elementos da lista de pets afastados.
const botaoPetsAfastados = document.querySelector("#botaoPetsAfastados");
const modalPetsAfastados = document.querySelector("#modalPetsAfastados");
const fecharPetsAfastados = document.querySelector("#fecharPetsAfastados");
const filtroPetsAfastados = document.querySelector("#filtroPetsAfastados");
const vazioPetsAfastados = document.querySelector("#vazioPetsAfastados");
const itensPetsAfastados = document.querySelectorAll("#listaPetsAfastados .lista-modal-item");

// Mostra a lista por cima da tela e coloca o cursor no campo de filtro.
function abrirPetsAfastados() {
    if (!modalPetsAfastados) return;

    modalPetsAfastados.classList.add("is-open");
    modalPetsAfastados.setAttribute("aria-hidden", "false");
    document.body.classList.add("lista-modal-aberta");

    if (filtroPetsAfastados) setTimeout(() => filtroPetsAfastados.focus(), 40);
}

// Esconde a lista e devolve o foco ao card que a abriu.
function fecharListaPetsAfastados() {
    if (!modalPetsAfastados) return;

    modalPetsAfastados.classList.remove("is-open");
    modalPetsAfastados.setAttribute("aria-hidden", "true");
    document.body.classList.remove("lista-modal-aberta");

    if (botaoPetsAfastados) botaoPetsAfastados.focus();
}

// Mostra apenas os pets cujo nome, ou o nome do tutor, contem o texto digitado.
function filtrarPetsAfastados() {
    // Compara sempre em minusculas, do mesmo jeito que o PHP gravou o atributo de busca.
    const procurado = (filtroPetsAfastados?.value || "").trim().toLowerCase();

    // Conta quantos itens sobraram, para saber se o aviso de lista vazia deve aparecer.
    let visiveis = 0;

    itensPetsAfastados.forEach(item => {
        const combina = !procurado || (item.dataset.busca || "").includes(procurado);
        item.hidden = !combina;
        if (combina) visiveis++;
    });

    if (vazioPetsAfastados) vazioPetsAfastados.hidden = visiveis > 0;
}

if (botaoPetsAfastados && modalPetsAfastados) {
    botaoPetsAfastados.addEventListener("click", abrirPetsAfastados);

    if (fecharPetsAfastados) {
        fecharPetsAfastados.addEventListener("click", fecharListaPetsAfastados);
    }

    // Fecha ao clicar na area escura, fora da caixa da lista.
    modalPetsAfastados.addEventListener("click", evento => {
        if (evento.target === modalPetsAfastados) fecharListaPetsAfastados();
    });

    // Fecha com a tecla Esc, como acontece nas outras janelas do sistema.
    document.addEventListener("keydown", evento => {
        if (evento.key === "Escape" && modalPetsAfastados.classList.contains("is-open")) {
            fecharListaPetsAfastados();
        }
    });

    if (filtroPetsAfastados) {
        filtroPetsAfastados.addEventListener("input", filtrarPetsAfastados);
    }
}


/* ============================================================
 * GRAFICOS
 *
 * A biblioteca calcula o tamanho do grafico no momento em que ele e criado.
 * Dentro de uma aba escondida a area mede zero, e o grafico nasceria torto.
 * Por isso o desenho so acontece quando a aba Graficos e aberta, e apenas
 * na primeira vez.
 * ==========================================================*/

// Marca se os graficos ja foram desenhados, para nao repetir o trabalho.


// Desenha os tres graficos da aba, uma unica vez.
function desenharGraficos() {
    // Interrompe quando os graficos ja estao na tela ou a biblioteca nao carregou.
    if (graficosDesenhados || typeof Chart === "undefined") return;

    // Marca antes de desenhar para que um segundo clique rapido nao duplique os graficos.
    graficosDesenhados = true;

    // GRAFICO 1: barras com o faturamento de cada um dos ultimos sete dias.
    const areaFaturamento = document.querySelector("#graficoFaturamento");
    if (areaFaturamento) {
        new Chart(areaFaturamento, {
            type: "bar",
            data: {
                labels: dadosFaturamento.map(item => item.rotulo),
                datasets: [{
                    data: dadosFaturamento.map(item => item.total),
                    backgroundColor: coresGrafico.barra,
                    borderRadius: 6,
                    maxBarThickness: 46
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: contexto => formatarReal(contexto.parsed.y)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: valor => formatarReal(valor) },
                        grid: { color: "#eef0f4" }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // GRAFICO 2: linha com a evolucao do faturamento nos ultimos seis meses.
    const areaEvolucao = document.querySelector("#graficoEvolucao");
    if (areaEvolucao) {
        new Chart(areaEvolucao, {
            type: "line",
            data: {
                labels: dadosEvolucao.map(item => item.rotulo),
                datasets: [{
                    data: dadosEvolucao.map(item => item.total),
                    borderColor: coresGrafico.linha,
                    backgroundColor: "rgba(239, 48, 40, .12)",
                    pointBackgroundColor: coresGrafico.linha,
                    pointRadius: 4,
                    borderWidth: 2,
                    tension: .35,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: contexto => formatarReal(contexto.parsed.y)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: valor => formatarReal(valor) },
                        grid: { color: "#eef0f4" }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // GRAFICO 3: rosca com a participacao de cada servico no total realizado.
    const areaServicos = document.querySelector("#graficoServicos");
    if (areaServicos && dadosServicos.length) {
        // Soma todas as quantidades para poder calcular a porcentagem de cada servico.
        const totalServicos = dadosServicos.reduce((soma, item) => soma + Number(item.quantidade), 0);

        new Chart(areaServicos, {
            type: "doughnut",
            data: {
                labels: dadosServicos.map(item => item.servico),
                datasets: [{
                    data: dadosServicos.map(item => Number(item.quantidade)),
                    backgroundColor: coresGrafico.fatias,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "62%",
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Monta a legenda ao lado do grafico, com a cor, o nome e a porcentagem de cada servico.
        const legenda = document.querySelector("#legendaServicos");
        if (legenda) {
            dadosServicos.forEach((item, posicao) => {
                // Calcula a participacao deste servico no total do periodo.
                const percentual = totalServicos > 0
                    ? Math.round((Number(item.quantidade) / totalServicos) * 100)
                    : 0;

                const linha = document.createElement("li");
                linha.innerHTML =
                    '<span class="legenda-cor"></span>' +
                    '<span class="legenda-nome"></span>' +
                    '<strong class="legenda-valor"></strong>';

                linha.querySelector(".legenda-cor").style.background =
                    coresGrafico.fatias[posicao % coresGrafico.fatias.length];
                linha.querySelector(".legenda-nome").textContent = item.servico;
                linha.querySelector(".legenda-valor").textContent = percentual + "%";

                legenda.appendChild(linha);
            });
        }
    }
}

// Desenha os graficos logo de cara quando a pagina ja abriu direto na aba Graficos.
if (document.querySelector("#painel-graficos.aba-painel-ativo")) {
    desenharGraficos();
}


/* ============================================================
 * ANALISE INTELIGENTE
 *
 * Uma unica consulta alimenta as abas Interpretacoes e Sugestoes da IA.
 * ==========================================================*/

// Guarda as referencias dos elementos que recebem o resultado da analise inteligente.
const iaSituacoes = document.querySelectorAll(".js-ia-situacao");
const iaBotoes = document.querySelectorAll(".js-ia-atualizar");
const iaResumo = document.querySelector("#iaResumo");
const iaObservacoes = document.querySelector("#iaObservacoes");
const iaPromocoes = document.querySelector("#iaPromocoes");

// Escreve o mesmo aviso de situacao nas duas abas que mostram a analise.
function avisarSituacaoIa(texto) {
    iaSituacoes.forEach(elemento => { elemento.textContent = texto; });
}

// Desenha uma lista de cartoes dentro do bloco informado, sem usar HTML vindo do servidor.
function desenharBlocoIa(destino, itens, vazio) {
    destino.innerHTML = "";

    // Mostra uma mensagem propria quando a analise nao encontrou nada para aquele bloco.
    if (!itens || !itens.length) {
        const aviso = document.createElement("p");
        aviso.className = "painel-vazio";
        aviso.textContent = vazio;
        destino.appendChild(aviso);
        return;
    }

    // Percorre os itens recebidos e cria um cartao para cada um.
    itens.forEach(item => {
        const cartao = document.createElement("article");
        cartao.className = "ia-item ia-item-" + (item.tipo || "neutro");

        const titulo = document.createElement("strong");
        titulo.textContent = item.titulo || "";

        const texto = document.createElement("p");
        texto.textContent = item.texto || "";

        cartao.appendChild(titulo);
        cartao.appendChild(texto);

        destino.appendChild(cartao);
    });
}

// Busca a analise no servidor e preenche as duas abas com o resultado.
async function carregarAnalise(forcarAtualizacao = false) {
    // Bloqueia os botoes enquanto a consulta acontece, para evitar pedidos repetidos.
    iaBotoes.forEach(botao => { botao.disabled = true; });
    avisarSituacaoIa(forcarAtualizacao ? "Refazendo a análise..." : "Lendo os dados do período...");

    try {
        // Pede a analise ao servidor, indicando se o resultado anterior deve ser descartado.
        const resposta = await fetch(enderecoAnalise + (forcarAtualizacao ? "?atualizar=1" : ""));

        // Interrompe quando o servidor respondeu com erro.
        if (!resposta.ok) throw new Error("Falha ao gerar a análise");

        const analise = await resposta.json();

        // Interrompe quando o servidor avisou que nao conseguiu concluir a analise.
        if (!analise.ok) throw new Error(analise.mensagem || "Falha ao gerar a análise");

        // Preenche o resumo e as listas das duas abas.
        iaResumo.textContent = analise.resumo;
        desenharBlocoIa(iaObservacoes, analise.observacoes, "Nada para destacar por enquanto.");
        desenharBlocoIa(iaPromocoes, analise.promocoes, "Nenhuma promoção a recomendar agora.");

        // Limpa o aviso de espera assim que o conteudo aparece na tela.
        avisarSituacaoIa("O que os números mostram sobre o período");
    } catch (erro) {
        // Avisa o usuario sem expor detalhes tecnicos na tela.
        avisarSituacaoIa("Não foi possível gerar a análise agora.");
        iaObservacoes.innerHTML = '<p class="painel-vazio">Tente novamente em instantes.</p>';
        iaPromocoes.innerHTML = '<p class="painel-vazio">Tente novamente em instantes.</p>';
    } finally {
        // Libera os botoes independentemente do resultado da consulta.
        iaBotoes.forEach(botao => { botao.disabled = false; });
    }
}

// Liga os botoes de atualizacao ao pedido de uma nova analise.
iaBotoes.forEach(botao => {
    botao.addEventListener("click", () => carregarAnalise(true));
});

// Carrega a analise assim que a pagina termina de ser exibida.
carregarAnalise(false);


/* ============================================================
 * ASSISTENTE
 * ==========================================================*/

// Guarda as referencias dos elementos da aba Assistente.
const assistenteForm = document.querySelector("#assistenteForm");
const assistenteCampo = document.querySelector("#assistentePergunta");
const assistenteEnviar = document.querySelector("#assistenteEnviar");
const assistenteConversa = document.querySelector("#assistenteConversa");
const assistenteSugestoes = document.querySelector("#assistenteSugestoes");

// Acrescenta um balao de pergunta, escrito pela atendente.
function assistenteAdicionarPergunta(texto) {
    const balao = document.createElement("div");
    balao.className = "assistente-balao assistente-balao-pergunta";
    balao.textContent = texto;

    assistenteConversa.appendChild(balao);
    assistenteRolarParaOFim();
}

// Acrescenta um balao de espera enquanto o servidor consulta os dados.
function assistenteAdicionarEspera() {
    const balao = document.createElement("div");
    balao.className = "assistente-balao assistente-balao-resposta assistente-espera";
    balao.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Consultando os dados...';

    assistenteConversa.appendChild(balao);
    assistenteRolarParaOFim();

    // Devolve o balao para que quem chamou possa troca-lo pela resposta real.
    return balao;
}

// Transforma a resposta recebida do servidor em um balao, sem usar HTML vindo de fora.
function assistenteDesenharResposta(balao, dados) {
    balao.className = "assistente-balao assistente-balao-resposta";
    balao.innerHTML = "";

    // Escreve o nome do assunto reconhecido, para deixar claro o que foi consultado.
    if (dados.titulo) {
        const titulo = document.createElement("strong");
        titulo.className = "assistente-titulo";
        titulo.textContent = dados.titulo;
        balao.appendChild(titulo);
    }

    // Escreve a frase que responde a pergunta.
    const texto = document.createElement("p");
    texto.className = "assistente-texto";
    texto.textContent = dados.resposta || "";
    balao.appendChild(texto);

    // Monta a lista de numeros quando a resposta trouxe detalhamento.
    if (dados.itens && dados.itens.length) {
        const lista = document.createElement("ul");
        lista.className = "assistente-itens";

        dados.itens.forEach(item => {
            const linha = document.createElement("li");

            const rotulo = document.createElement("span");
            rotulo.textContent = item.rotulo || "";

            const valor = document.createElement("strong");
            valor.textContent = item.valor || "";

            linha.appendChild(rotulo);
            linha.appendChild(valor);
            lista.appendChild(linha);
        });

        balao.appendChild(lista);
    }

    assistenteRolarParaOFim();
}

// Redesenha os atalhos de pergunta abaixo da conversa.
function assistenteDesenharSugestoes(sugestoes) {
    assistenteSugestoes.innerHTML = "";

    // Interrompe quando o servidor nao mandou nenhuma sugestao.
    if (!sugestoes || !sugestoes.length) return;

    // Cria um atalho clicavel para cada pergunta sugerida.
    sugestoes.forEach(sugestao => {
        const atalho = document.createElement("button");
        atalho.type = "button";
        atalho.className = "assistente-atalho";
        atalho.textContent = sugestao;

        // Ao clicar, a pergunta sugerida e enviada na hora.
        atalho.addEventListener("click", () => assistentePerguntar(sugestao));

        assistenteSugestoes.appendChild(atalho);
    });
}

// Mantem a conversa sempre mostrando a mensagem mais recente.
function assistenteRolarParaOFim() {
    assistenteConversa.scrollTop = assistenteConversa.scrollHeight;
}

// Envia a pergunta ao servidor e desenha a resposta na conversa.
async function assistentePerguntar(pergunta) {
    // Interrompe quando a pergunta esta em branco.
    if (!pergunta || !pergunta.trim()) return;

    // Mostra a pergunta e limpa o campo, para a atendente poder escrever a proxima.
    assistenteAdicionarPergunta(pergunta.trim());
    assistenteCampo.value = "";
    assistenteEnviar.disabled = true;

    // Coloca um balao de espera no lugar onde a resposta vai aparecer.
    const balao = assistenteAdicionarEspera();

    try {
        // Envia a pergunta por POST, para que o texto nao apareca no endereco da pagina.
        const corpo = new URLSearchParams();
        corpo.append("pergunta", pergunta.trim());

        const resposta = await fetch(enderecoAssistente, { method: "POST", body: corpo });

        // Interrompe quando o servidor respondeu com erro.
        if (!resposta.ok) throw new Error("Falha ao consultar os dados");

        const dados = await resposta.json();

        // Interrompe quando o servidor avisou que nao conseguiu responder.
        if (!dados.ok) throw new Error(dados.mensagem || "Falha ao consultar os dados");

        // Troca o balao de espera pela resposta e atualiza os atalhos.
        assistenteDesenharResposta(balao, dados);
        assistenteDesenharSugestoes(dados.sugestoes);
    } catch (erro) {
        // Avisa o usuario sem expor detalhes tecnicos na tela.
        balao.className = "assistente-balao assistente-balao-resposta assistente-balao-erro";
        balao.textContent = "Não consegui consultar os dados agora. Tente novamente em instantes.";
    } finally {
        // Libera o botao independentemente do resultado da consulta.
        assistenteEnviar.disabled = false;
        assistenteCampo.focus();
    }
}

// Liga o formulario ao envio da pergunta, sem recarregar a pagina.
if (assistenteForm) {
    assistenteForm.addEventListener("submit", evento => {
        evento.preventDefault();
        assistentePerguntar(assistenteCampo.value);
    });

    // Abre a conversa com a lista do que o assistente sabe responder.
    assistentePerguntar("o que posso te perguntar?");
}
</script>

<?php
// Importa o arquivo responsavel pelo rodape da pagina.
require __DIR__ . '/includes/rodape.php';
?>