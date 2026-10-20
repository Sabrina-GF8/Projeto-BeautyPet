<?php
/**
 * Beauty Pet - configuração central do sistema web.
 *
 * Este arquivo concentra:
 * - BASE_URL e conexão PDO com PostgreSQL/Supabase;
 * - helpers gerais de validação e formatação;
 * - fotos e dados de pets/tutores;
 * - sessão e usuários;
 * - regras auxiliares da agenda;
 * - inicialização da sessão e troca de usuário.
 *
 * Organização apenas estrutural: as regras executáveis foram preservadas.
 */

// ============================================================================
// 1. BASE URL DO PROJETO
// ============================================================================
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$diretorioScript = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');

$documentRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$projectRoot  = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));

if ($documentRoot !== '' && $projectRoot !== '' && str_starts_with(strtolower($projectRoot), strtolower($documentRoot))) {
    $baseUrlCalculada = substr($projectRoot, strlen($documentRoot));
} else {
    $baseUrlCalculada = $diretorioScript === '/' ? '' : $diretorioScript;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim($baseUrlCalculada, '/'));
}

// ============================================================================
// 2. AMBIENTE E CONEXÃO COM POSTGRESQL / SUPABASE
// ============================================================================

// Leitura do arquivo .env.
require_once __DIR__ . '/env.php';

// Carrega as variáveis de ambiente (onde a DATABASE_URL do Supabase deve estar)
carregarEnv(__DIR__ . '/.env');
$databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '';

if ($databaseUrl === '') {
    exit('DATABASE_URL não configurada. Copie a string de conexão PostgreSQL do Supabase para config/.env.');
}

if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    exit('O driver PDO_PGSQL não está habilitado no PHP. Ative extension=pdo_pgsql no php.ini e reinicie o Apache.');
}

$partesBanco = parse_url($databaseUrl);

if ($partesBanco === false || empty($partesBanco['host']) || empty($partesBanco['user'])) {
    exit('DATABASE_URL inválida. Use a string PostgreSQL exibida em Supabase > Project Settings > Database.');
}

$hostBanco = (string) $partesBanco['host'];
$portaBanco = (int) ($partesBanco['port'] ?? 5432);
$nomeBanco = ltrim((string) ($partesBanco['path'] ?? '/postgres'), '/');
$usuarioBanco = urldecode((string) $partesBanco['user']);
$senhaBanco = urldecode((string) ($partesBanco['pass'] ?? ''));

$dsnBanco = "pgsql:host={$hostBanco};port={$portaBanco};dbname={$nomeBanco};sslmode=require";
$usarPrepareEmulado = $portaBanco === 6543;

try {
    $pdo = new PDO(
        $dsnBanco,
        $usuarioBanco,
        $senhaBanco,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => $usarPrepareEmulado,
        ]
    );
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
} catch (PDOException $erro) {
    exit('Não foi possível conectar ao Supabase/PostgreSQL: ' . $erro->getMessage());
} catch (Exception $erro) {
    exit('Erro na configuração de ambiente: ' . $erro->getMessage());
}

// ============================================================================
// 3. HELPERS GERAIS
// ============================================================================

function escapar(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function ultimoIdInserido(PDO $pdo): int
{
    return (int) $pdo->query('SELECT LASTVAL()')->fetchColumn();
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

// ============================================================================
// 4. PETS E FOTOS
// ============================================================================

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

    // Como este arquivo agora está dentro de /config, dirname(__DIR__) volta para a raiz do projeto.
    // A pasta física das fotos continua sendo /uploads/pets na raiz.
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


function urlFotoPet(?string $foto): ?string
{
    $foto = textoOuNull($foto);

    if ($foto === null) {
        return null;
    }

    $relativa = ltrim(str_replace('\\', '/', $foto), '/');
    // Monta o caminho físico da foto a partir da raiz do projeto, e não da pasta /config.
    $destinoAtual = dirname(__DIR__) . '/' . $relativa;

    if (is_file($destinoAtual)) {
        return BASE_URL . '/' . $relativa;
    }

    // Compatibilidade com fotos de uma estrutura anterior, que ficavam um nível acima da raiz atual.
    $destinoAntigo = dirname(dirname(__DIR__)) . '/' . $relativa;

    if (is_file($destinoAntigo)) {
        $pastaAtual = dirname($destinoAtual);

        if (!is_dir($pastaAtual)) {
            @mkdir($pastaAtual, 0755, true);
        }

        @copy($destinoAntigo, $destinoAtual);

        if (is_file($destinoAtual)) {
            return '../' . $relativa;
        }
    }

    return null;
}

// ============================================================================
// 5. FORMATAÇÃO E EXIBIÇÃO
// ============================================================================

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

// ============================================================================
// 6. BUSCAS AUXILIARES DE PETS E TUTORES
// ============================================================================

function buscarPetParaCampo(PDO $pdo, ?int $petId): ?array
{
    if ($petId === null) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT 
            p.id, 
            p.nome, 
            c.nome AS tutor, 
            r.nome AS raca 
         FROM pets p 
         JOIN clientes c ON c.id = p.cliente_id 
         LEFT JOIN racas r ON r.id = p.raca_id 
         WHERE p.id = :id 
           AND p.ativo = TRUE 
           AND c.ativo = TRUE 
         LIMIT 1'
    );
    $stmt->execute([':id' => $petId]);
    $pet = $stmt->fetch();

    return $pet ?: null;
}

function buscarTutorParaCampo(PDO $pdo, ?int $tutorId): ?array
{
    if ($tutorId === null) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, nome, cpf, email, telefone_principal
         FROM clientes
         WHERE id = :id
           AND ativo = TRUE
         LIMIT 1'
    );
    $stmt->execute([':id' => $tutorId]);
    $tutor = $stmt->fetch();

    return $tutor ?: null;
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


// ============================================================================
// 7. SESSÃO, USUÁRIOS E MENSAGENS
// ============================================================================

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

// ============================================================================
// 8. DATAS, HORAS E STATUS DE AGENDAMENTO
// ============================================================================

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

// ============================================================================
// 9. REGRAS DA AGENDA
// ============================================================================

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
           AND LOWER(cs.nome) NOT IN ('loja', 'financeiro')"
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
                    a.data_hora_inicio + INTERVAL '30 minutes'
                ) AS data_hora_fim
            FROM agendamentos a
            JOIN agendamento_itens ai
              ON ai.agendamento_id = a.id
            JOIN servicos s
              ON s.id = ai.servico_id
            JOIN categorias_servico cs
              ON cs.id = s.categoria_id
            WHERE a.status NOT IN ('CANCELADO', 'RECUSADO')
              AND a.data_hora_inicio < CAST(:data_fim AS timestamp) + INTERVAL '1 day'
              AND COALESCE(
                    a.data_hora_fim,
                    a.data_hora_inicio + INTERVAL '30 minutes'
                  ) > :data_inicio
              AND (
                    LOWER(cs.nome) LIKE '%banho%'
                    OR LOWER(cs.nome) LIKE '%tosa%'
                    OR LOWER(s.nome) LIKE '%banho%'
                    OR LOWER(s.nome) LIKE '%tosa%'
                    OR LOWER(s.nome) LIKE '%higien%'
                  )";

    // Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
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

    // Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
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

function quantidadeVagas(mixed $valor): int
{
    $numero = filter_var($valor, FILTER_VALIDATE_INT);

    if ($numero === false) {
        return 0;
    }

    return max(0, min(5, (int) $numero));
}

// ============================================================================
// 10. INICIALIZAÇÃO E TROCA DE USUÁRIO
// ============================================================================

// A sessão é usada para saber qual usuário está ativo.
iniciarSessao();

// Troca de usuário: funciona como uma tela de login, usando e-mail e senha.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'trocar_usuario') {
    $emailDigitado = textoOuNull($_POST['email_usuario'] ?? null);
    $senhaDigitada = (string) ($_POST['senha'] ?? '');
    $retorno = paginaRetornoSegura($_POST['retorno'] ?? 'index.php');

    // Padroniza o e-mail antes de comparar ou salvar, evitando diferenças por espaços ou letras maiúsculas.
    $emailNormalizado = $emailDigitado === null
        ? null
        : (function_exists('mb_strtolower')
            ? mb_strtolower(trim($emailDigitado), 'UTF-8')
            : strtolower(trim($emailDigitado)));

    if ($emailNormalizado === null || $senhaDigitada === '') {
        $_SESSION['usuario_troca_email'] = $emailNormalizado ?? '';
        definirMensagemUsuario('error', 'Digite o e-mail e a senha.');
        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header('Location: ' . $retorno);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, nome, email, senha_hash
         FROM usuarios
         WHERE LOWER(TRIM(email)) = :email
           AND ativo = TRUE
         LIMIT 1'
    );
    $stmt->execute([':email' => $emailNormalizado]);
    $usuarioTroca = $stmt->fetch();

    if (!$usuarioTroca || !password_verify($senhaDigitada, $usuarioTroca['senha_hash'])) {
        $_SESSION['usuario_troca_email'] = $emailNormalizado;
        definirMensagemUsuario('error', 'E-mail ou senha incorretos.');
        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header('Location: ' . $retorno);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuarioTroca['id'];
    unset($_SESSION['usuario_troca_email']);
    definirMensagemUsuario('success', 'Usuário alterado para ' . $usuarioTroca['nome'] . '.');
    // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
    header('Location: ' . $retorno);
    // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
    exit;
}
