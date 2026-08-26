<?php

// CONFIGURAÇÃO GERAL DO BEAUTY PET

$host = 'localhost';
$banco = 'beauty_pet';
$usuarioBanco = 'root';
$senhaBanco = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$banco};charset=utf8mb4",
        $usuarioBanco,
        $senhaBanco,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $erro) {
    exit('Não foi possível conectar ao banco de dados: ' . $erro->getMessage());
}

// Funções usadas em várias páginas

function escapar(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function somenteNumeros(?string $valor): ?string
{
    if ($valor === null || trim($valor) === '') {
        return null;
    }

    return preg_replace('/\D+/', '', $valor);
}

function cpfValido(?string $cpf): bool
{
    $cpf = preg_replace('/\D+/', '', $cpf ?? '');

    if (strlen($cpf) !== 11) {
        return false;
    }

    // CPFs com todos os números iguais são inválidos.
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    // Validação dos dois dígitos verificadores.
    for ($posicao = 9; $posicao <= 10; $posicao++) {
        $soma = 0;

        for ($indice = 0; $indice < $posicao; $indice++) {
            $soma += (int) $cpf[$indice] * (($posicao + 1) - $indice);
        }

        $digito = (10 * $soma) % 11;
        $digito = $digito === 10 ? 0 : $digito;

        if ((int) $cpf[$posicao] !== $digito) {
            return false;
        }
    }

    return true;
}

function textoOuNull(?string $valor): ?string
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);

    return $valor === '' ? null : $valor;
}

function inteiroOuNull(mixed $valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }

    return filter_var($valor, FILTER_VALIDATE_INT) !== false
        ? (int) $valor
        : null;
}

function salvarFotoPet(array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a foto do pet.');
    }

    if (($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('A foto deve ter no máximo 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);

    $extensoes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensoes[$mime])) {
        throw new RuntimeException('Use uma imagem JPG, PNG ou WEBP.');
    }

    $pasta = dirname(__DIR__) . '/uploads/pets';

    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new RuntimeException('Não foi possível criar a pasta das fotos.');
    }

    $nome = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
    $destino = $pasta . '/' . $nome;

    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível salvar a foto do pet.');
    }

    return 'uploads/pets/' . $nome;
}


function formatarMoeda(float|int|string|null $valor): string
{
    return 'R$ ' . number_format((float) ($valor ?? 0), 2, ',', '.');
}

function formatarCpf(?string $cpf): string
{
    $cpf = preg_replace('/\D+/', '', $cpf ?? '');

    if (strlen($cpf) !== 11) {
        return $cpf !== '' ? $cpf : 'Não informado';
    }

    return substr($cpf, 0, 3) . '.'
        . substr($cpf, 3, 3) . '.'
        . substr($cpf, 6, 3) . '-'
        . substr($cpf, 9, 2);
}

function formatarTelefone(?string $telefone): string
{
    $telefone = preg_replace('/\D+/', '', $telefone ?? '');

    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') '
            . substr($telefone, 2, 5) . '-'
            . substr($telefone, 7, 4);
    }

    if (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') '
            . substr($telefone, 2, 4) . '-'
            . substr($telefone, 6, 4);
    }

    return $telefone !== '' ? $telefone : 'Não informado';
}

function formatarData(?string $data): string
{
    if ($data === null || $data === '') {
        return 'Não informada';
    }

    $objeto = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));

    return $objeto ? $objeto->format('d/m/Y') : 'Não informada';
}

function iniciaisNome(?string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome ?? ''), -1, PREG_SPLIT_NO_EMPTY);

    if (!$partes) {
        return '?';
    }

    $primeiroNome = $partes[0];
    $ultimoNome = $partes[count($partes) - 1];

    $primeira = function_exists('mb_substr')
        ? mb_substr($primeiroNome, 0, 1)
        : substr($primeiroNome, 0, 1);

    $ultima = count($partes) > 1
        ? (
            function_exists('mb_substr')
                ? mb_substr($ultimoNome, 0, 1)
                : substr($ultimoNome, 0, 1)
        )
        : '';

    $iniciais = $primeira . $ultima;

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($iniciais)
        : strtoupper($iniciais);
}

function textoBooleano(mixed $valor): string
{
    if ($valor === null || $valor === '') {
        return 'Não informado';
    }

    return (int) $valor === 1 ? 'Sim' : 'Não';
}

function nomePorte(?string $porte): string
{
    return match ($porte) {
        'PEQUENO' => 'Pequeno',
        'MEDIO'   => 'Médio',
        'GRANDE'  => 'Grande',
        default   => 'Não informado',
    };
}

function nomeSexo(?string $sexo): string
{
    return match ($sexo) {
        'MACHO' => 'Macho',
        'FEMEA' => 'Fêmea',
        default => 'Não informado',
    };
}

function idadePet(?string $dataNascimento, bool $aproximada = false): string
{
    if ($dataNascimento === null || $dataNascimento === '') {
        return 'Não informada';
    }

    try {
        $nascimento = new DateTime($dataNascimento);
        $hoje = new DateTime();
        $idade = $nascimento->diff($hoje);

        $texto = $idade->y === 1
            ? '1 ano'
            : $idade->y . ' anos';

        return $aproximada ? 'Aproximadamente ' . $texto : $texto;
    } catch (Throwable) {
        return 'Não informada';
    }
}

function enderecoCompleto(array $cliente): string
{
    $partes = [];

    if (!empty($cliente['logradouro'])) {
        $rua = $cliente['logradouro'];

        if (!empty($cliente['numero'])) {
            $rua .= ', ' . $cliente['numero'];
        }

        $partes[] = $rua;
    }

    if (!empty($cliente['bairro'])) {
        $partes[] = $cliente['bairro'];
    }

    $cidadeEstado = trim(
        ($cliente['cidade'] ?? '')
        . (!empty($cliente['estado']) ? ' - ' . $cliente['estado'] : '')
    );

    if ($cidadeEstado !== '') {
        $partes[] = $cidadeEstado;
    }

    return $partes ? implode(' • ', $partes) : 'Não informado';
}


function iniciarSessao(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function nomePerfilUsuario(?string $perfil): string
{
    return match ($perfil) {
        'ADMINISTRADOR' => 'Administradora',
        'FUNCIONARIO'   => 'Funcionário(a)',
        default         => 'Sem perfil',
    };
}

function obterUsuarioAtual(PDO $pdo): ?array
{
    $usuarioId = $_SESSION['usuario_id'] ?? null;

    if (!$usuarioId) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, nome, email, perfil, ativo
         FROM usuarios
         WHERE id = :id
           AND ativo = TRUE'
    );
    $stmt->execute([':id' => (int) $usuarioId]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        unset($_SESSION['usuario_id']);
        return null;
    }

    return $usuario;
}

function listarUsuariosAtivos(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, nome, email, perfil
         FROM usuarios
         WHERE ativo = TRUE
         ORDER BY nome'
    );

    return $stmt->fetchAll();
}

function definirMensagemUsuario(string $tipo, string $texto): void
{
    iniciarSessao();
    $_SESSION['mensagem_usuario'] = [
        'tipo'  => $tipo,
        'texto' => $texto,
    ];
}

function consumirMensagemUsuario(): ?array
{
    iniciarSessao();

    $mensagem = $_SESSION['mensagem_usuario'] ?? null;
    unset($_SESSION['mensagem_usuario']);

    return is_array($mensagem) ? $mensagem : null;
}

function paginaRetornoSegura(?string $pagina): string
{
    $pagina = trim((string) $pagina);

    if ($pagina === '') {
        return 'index.php';
    }

    $partes = parse_url($pagina);
    $caminho = basename((string) ($partes['path'] ?? ''));

    if (!preg_match('/^[a-zA-Z0-9_-]+\\.php$/', $caminho)) {
        return 'index.php';
    }

    $query = isset($partes['query']) && $partes['query'] !== ''
        ? '?' . $partes['query']
        : '';

    return $caminho . $query;
}

function dataSqlValida(?string $data): bool
{
    if ($data === null || $data === '') {
        return false;
    }

    $objeto = DateTime::createFromFormat('Y-m-d', $data);

    return $objeto !== false
        && $objeto->format('Y-m-d') === $data;
}

function horaSqlValida(?string $hora): bool
{
    if ($hora === null || $hora === '') {
        return false;
    }

    $objeto = DateTime::createFromFormat('H:i', $hora);

    return $objeto !== false
        && $objeto->format('H:i') === $hora;
}

function formatarDataHoraAgenda(?string $dataHora): string
{
    if ($dataHora === null || $dataHora === '') {
        return 'Não informada';
    }

    try {
        return (new DateTime($dataHora))->format('d/m/Y \à\s H:i');
    } catch (Throwable) {
        return 'Não informada';
    }
}

function formatarHoraAgenda(?string $dataHora): string
{
    if ($dataHora === null || $dataHora === '') {
        return '--:--';
    }

    try {
        return (new DateTime($dataHora))->format('H:i');
    } catch (Throwable) {
        return '--:--';
    }
}

function nomeStatusAgendamento(?string $status): string
{
    return match ($status) {
        'SOLICITADO'      => 'Solicitado',
        'CONFIRMADO'      => 'Confirmado',
        'RECUSADO'        => 'Recusado',
        'CANCELADO'       => 'Cancelado',
        'EM_ATENDIMENTO'  => 'Em atendimento',
        'CONCLUIDO'       => 'Concluído',
        default           => 'Não informado',
    };
}

function classeStatusAgendamento(?string $status): string
{
    return match ($status) {
        'CONFIRMADO', 'CONCLUIDO' => 'success',
        'EM_ATENDIMENTO'          => 'warning',
        'SOLICITADO'              => 'info',
        'RECUSADO', 'CANCELADO'   => 'danger',
        default                   => 'neutral',
    };
}

// Funções da agenda

function normalizarCategoriaAgenda(?string $texto): string
{
    $texto = trim((string) $texto);
    $texto = function_exists('mb_strtolower')
        ? mb_strtolower($texto, 'UTF-8')
        : strtolower($texto);

    return strtr($texto, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ]);
}

function ehBanhoTosa(?string $categoria, ?string $servico = null): bool
{
    $texto = normalizarCategoriaAgenda(
        trim((string) $categoria . ' ' . (string) $servico)
    );

    return str_contains($texto, 'banho')
        || str_contains($texto, 'tosa')
        || str_contains($texto, 'higien');
}

function nomeCategoriaAgenda(string $categoria): string
{
    $normalizada = normalizarCategoriaAgenda($categoria);

    return match (true) {
        str_contains($normalizada, 'taxi')      => 'Taxidog',
        str_contains($normalizada, 'veterin')  => 'Consulta veterinária',
        str_contains($normalizada, 'banho'),
        str_contains($normalizada, 'tosa')      => 'Banho e tosa',
        str_contains($normalizada, 'creche')    => 'Creche',
        str_contains($normalizada, 'hotel')     => 'Hotel',
        default                                 => $categoria,
    };
}

function enderecoAbreviadoAgenda(array $item): string
{
    $logradouro = trim((string) ($item['logradouro'] ?? ''));
    $numero = trim((string) ($item['numero'] ?? ''));
    $bairro = trim((string) ($item['bairro'] ?? ''));
    $cidade = trim((string) ($item['cidade'] ?? ''));
    $estado = trim((string) ($item['estado'] ?? ''));

    $linha = $logradouro;

    if ($numero !== '') {
        $linha .= ($linha !== '' ? ', ' : '') . $numero;
    }

    $local = $bairro;

    if ($local === '' && $cidade !== '') {
        $local = $cidade . ($estado !== '' ? '/' . $estado : '');
    }

    $partes = array_values(
        array_filter([$linha, $local], static fn ($valor) => $valor !== '')
    );

    return $partes ? implode(' • ', $partes) : 'Endereço não informado';
}

function formatarDuracaoAgenda(int $minutos): string
{
    $horas = intdiv($minutos, 60);
    $restante = $minutos % 60;

    if ($horas === 0) {
        return $restante . ' min';
    }

    if ($restante === 0) {
        return $horas . 'h';
    }

    return $horas . 'h' . str_pad((string) $restante, 2, '0', STR_PAD_LEFT);
}

function buscarServicoAgenda(PDO $pdo, int $servicoId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT
            s.id,
            s.nome,
            cs.nome AS categoria
         FROM servicos s
         JOIN categorias_servico cs
           ON cs.id = s.categoria_id
         WHERE s.id = :id
           AND s.ativo = TRUE
           AND cs.ativo = TRUE
           AND LOWER(cs.nome) <> 'loja'"
    );
    $stmt->execute([':id' => $servicoId]);

    $servico = $stmt->fetch();

    return $servico ?: null;
}

function buscarAgendamentoEdicao(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            a.id,
            a.pet_id,
            a.data_hora_inicio,
            a.data_hora_fim,
            a.status,
            a.observacoes_internas,
            ai.servico_id,
            s.nome AS servico,
            cs.nome AS categoria
         FROM agendamentos a
         LEFT JOIN agendamento_itens ai
           ON ai.agendamento_id = a.id
         LEFT JOIN servicos s
           ON s.id = ai.servico_id
         LEFT JOIN categorias_servico cs
           ON cs.id = s.categoria_id
         WHERE a.id = :id
         ORDER BY ai.id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);

    $item = $stmt->fetch();

    return $item ?: null;
}

function buscarPadraoBanhoDaData(PDO $pdo, string $data): ?array
{
    $diaSemana = (int) (new DateTime($data))->format('N');

    $stmt = $pdo->prepare(
        "SELECT *
         FROM agenda_padrao_banho_tosa
         WHERE ativo = TRUE
           AND dia_semana = :dia_semana
           AND data_inicio <= :data_inicio
           AND (
                tipo_duracao = 'INDETERMINADO'
                OR data_fim >= :data_fim
           )
         ORDER BY data_inicio DESC, id DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':dia_semana'  => $diaSemana,
        ':data_inicio' => $data,
        ':data_fim'    => $data,
    ]);

    $padrao = $stmt->fetch();

    return $padrao ?: null;
}

function buscarHorariosPadraoBanho(PDO $pdo, int $padraoId): array
{
    $stmt = $pdo->prepare(
        'SELECT horario, quantidade_vagas
         FROM agenda_padrao_banho_tosa_horarios
         WHERE agenda_padrao_id = :agenda_padrao_id
         ORDER BY horario'
    );
    $stmt->execute([':agenda_padrao_id' => $padraoId]);

    $horarios = [];

    foreach ($stmt->fetchAll() as $item) {
        $hora = substr((string) $item['horario'], 0, 5);
        $horarios[$hora] = (int) $item['quantidade_vagas'];
    }

    return $horarios;
}

function buscarOcupacaoBanhoTosa(
    PDO $pdo,
    string $data,
    array $horariosPadrao,
    ?int $ignorarAgendamentoId = null
): array {
    $ocupacao = array_fill_keys(array_keys($horariosPadrao), 0);

    if (!$horariosPadrao) {
        return $ocupacao;
    }

    $sql = "SELECT DISTINCT
                a.id,
                a.data_hora_inicio,
                COALESCE(
                    a.data_hora_fim,
                    DATE_ADD(a.data_hora_inicio, INTERVAL 30 MINUTE)
                ) AS data_hora_fim
            FROM agendamentos a
            JOIN agendamento_itens ai
              ON ai.agendamento_id = a.id
            JOIN servicos s
              ON s.id = ai.servico_id
            JOIN categorias_servico cs
              ON cs.id = s.categoria_id
            WHERE a.status NOT IN ('CANCELADO', 'RECUSADO')
              AND a.data_hora_inicio < DATE_ADD(:data_fim, INTERVAL 1 DAY)
              AND COALESCE(
                    a.data_hora_fim,
                    DATE_ADD(a.data_hora_inicio, INTERVAL 30 MINUTE)
                  ) > :data_inicio
              AND (
                    LOWER(cs.nome) LIKE '%banho%'
                    OR LOWER(cs.nome) LIKE '%tosa%'
                    OR LOWER(s.nome) LIKE '%banho%'
                    OR LOWER(s.nome) LIKE '%tosa%'
                    OR LOWER(s.nome) LIKE '%higien%'
                  )";

    $parametros = [
        ':data_inicio' => $data . ' 00:00:00',
        ':data_fim'    => $data . ' 00:00:00',
    ];

    if ($ignorarAgendamentoId !== null) {
        $sql .= ' AND a.id <> :ignorar_id';
        $parametros[':ignorar_id'] = $ignorarAgendamentoId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $agendamentos = $stmt->fetchAll();

    foreach ($horariosPadrao as $hora => $_capacidade) {
        $inicioSlot = new DateTime($data . ' ' . $hora . ':00');
        $fimSlot = (clone $inicioSlot)->modify('+30 minutes');

        foreach ($agendamentos as $agendamento) {
            $inicio = new DateTime((string) $agendamento['data_hora_inicio']);
            $fim = new DateTime((string) $agendamento['data_hora_fim']);

            if ($inicio < $fimSlot && $fim > $inicioSlot) {
                $ocupacao[$hora]++;
            }
        }
    }

    return $ocupacao;
}

function validarVagasBanhoTosa(
    array $horariosPadrao,
    array $ocupacao,
    string $horaInicio,
    int $duracaoMinutos
): array {
    if (
        $duracaoMinutos < 30
        || $duracaoMinutos > 480
        || $duracaoMinutos % 30 !== 0
    ) {
        return [false, 'Selecione uma duração válida, de 30 em 30 minutos.'];
    }

    $blocos = intdiv($duracaoMinutos, 30);
    $cursor = DateTime::createFromFormat('H:i', $horaInicio);

    if (!$cursor) {
        return [false, 'Selecione um horário válido.'];
    }

    for ($i = 0; $i < $blocos; $i++) {
        $hora = $cursor->format('H:i');

        if (!array_key_exists($hora, $horariosPadrao)) {
            return [
                false,
                'O período escolhido ultrapassa os horários disponíveis da agenda-padrão.'
            ];
        }

        $capacidade = (int) $horariosPadrao[$hora];
        $ocupadas = (int) ($ocupacao[$hora] ?? 0);

        if ($capacidade <= 0 || $ocupadas >= $capacidade) {
            return [
                false,
                'Uma das faixas de 30 minutos escolhidas já está sem vaga.'
            ];
        }

        $cursor->modify('+30 minutes');
    }

    return [true, ''];
}

function horaEmBlocoDeTrinta(?string $hora): bool
{
    if (!horaSqlValida($hora)) {
        return false;
    }

    $minuto = (int) substr((string) $hora, 3, 2);

    return in_array($minuto, [0, 30], true);
}

function gerarHorariosDeTrinta(string $horaInicio, string $horaFim): array
{
    $inicio = DateTime::createFromFormat('H:i', $horaInicio);
    $fim = DateTime::createFromFormat('H:i', $horaFim);

    if (!$inicio || !$fim || $fim <= $inicio) {
        return [];
    }

    $horarios = [];
    $cursor = clone $inicio;

    while ($cursor < $fim) {
        $horarios[] = $cursor->format('H:i');
        $cursor->modify('+30 minutes');

        if (count($horarios) > 48) {
            return [];
        }
    }

    return $horarios;
}

function padraoSobreposto(
    PDO $pdo,
    int $diaSemana,
    string $dataInicio,
    ?string $dataFim,
    ?int $ignorarId = null
): bool {
    $sql = "SELECT COUNT(*)
            FROM agenda_padrao_banho_tosa
            WHERE dia_semana = :dia_semana
              AND ativo = TRUE
              AND data_inicio <= :novo_fim
              AND COALESCE(data_fim, '9999-12-31') >= :novo_inicio";

    $parametros = [
        ':dia_semana'  => $diaSemana,
        ':novo_fim'    => $dataFim ?? '9999-12-31',
        ':novo_inicio' => $dataInicio,
    ];

    if ($ignorarId !== null) {
        $sql .= ' AND id <> :ignorar_id';
        $parametros[':ignorar_id'] = $ignorarId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return (int) $stmt->fetchColumn() > 0;
}

function contarVagasEnviadas(mixed $valores): int
{
    if (!is_array($valores)) {
        return 0;
    }

    $vagas = [];

    foreach ($valores as $valor) {
        $numero = filter_var($valor, FILTER_VALIDATE_INT);

        if ($numero !== false && $numero >= 1 && $numero <= 5) {
            $vagas[$numero] = true;
        }
    }

    return count($vagas);
}

// A sessão é usada para saber qual usuário está ativo.
iniciarSessao();

// Troca de usuário: fica aqui para não precisar de um arquivo só para isso.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'trocar_usuario') {
    $usuarioId = inteiroOuNull($_POST['usuario_id'] ?? null);
    $senhaDigitada = (string) ($_POST['senha'] ?? '');
    $retorno = paginaRetornoSegura($_POST['retorno'] ?? 'index.php');

    if (!$usuarioId || $senhaDigitada === '') {
        $_SESSION['usuario_troca_id'] = $usuarioId;
        definirMensagemUsuario('error', 'Selecione o usuário e digite a senha.');
        header('Location: ' . $retorno);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE id = ? AND ativo = TRUE');
    $stmt->execute([$usuarioId]);
    $usuarioTroca = $stmt->fetch();

    if (!$usuarioTroca || !password_verify($senhaDigitada, $usuarioTroca['senha_hash'])) {
        $_SESSION['usuario_troca_id'] = $usuarioId;
        definirMensagemUsuario('error', 'Usuário ou senha incorretos.');
        header('Location: ' . $retorno);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuarioTroca['id'];
    unset($_SESSION['usuario_troca_id']);
    definirMensagemUsuario('success', 'Usuário alterado para ' . $usuarioTroca['nome'] . '.');
    header('Location: ' . $retorno);
    exit;
}
