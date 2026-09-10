<?php

// Carrega a função responsável por ler o arquivo .env
require_once __DIR__ . '/env.php';

try {

    // Carrega as variáveis do arquivo .env
    carregarEnv(__DIR__ . '/.env');

    // Pega a URL de conexão armazenada no .env
    $databaseUrl = $_ENV['DATABASE_URL'];

    // Separa as informações da URL de conexão
    $dadosConexao = parse_url($databaseUrl);

    // Cria a conexão com o PostgreSQL do Supabase
    $pdo = new PDO(
        "pgsql:host={$dadosConexao['host']};port={$dadosConexao['port']};dbname=" . ltrim($dadosConexao['path'], '/') . ";sslmode=require",
        $dadosConexao['user'],
        $dadosConexao['pass'],
        [
            // Define que os erros do banco serão tratados como exceções
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Define o formato padrão dos resultados das consultas
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Desativa a emulação de prepared statements
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $erro) {

    // Interrompe o sistema caso não seja possível conectar ao banco
    exit('Não foi possível conectar ao banco de dados: ' . $erro->getMessage());

} catch (Exception $erro) {

    // Erro relacionado à configuração do ambiente
    exit('Erro na configuração: ' . $erro->getMessage());
}


// Funções utilizadas em diferentes páginas do sistema

// Protege textos antes de serem exibidos no HTML
function escapar(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}


// Remove caracteres que não sejam números
function somenteNumeros(?string $valor): ?string
{
    if ($valor === null || trim($valor) === '') {
        return null;
    }

    return preg_replace('/\D+/', '', $valor);
}


// Valida se o CPF possui formato e dígitos verificadores válidos
function cpfValido(?string $cpf): bool
{
    $cpf = preg_replace('/\D+/', '', $cpf ?? '');

    if (strlen($cpf) !== 11) {
        return false;
    }

    // Impede CPFs formados pelo mesmo número repetido
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    // Calcula e confere os dois dígitos verificadores do CPF
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


// Converte texto vazio em valor nulo
function textoOuNull(?string $valor): ?string
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);

    return $valor === '' ? null : $valor;
}


// Converte um valor para inteiro ou retorna nulo quando inválido
function inteiroOuNull(mixed $valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }

    return filter_var($valor, FILTER_VALIDATE_INT) !== false
        ? (int) $valor
        : null;
}


// Faz o upload e salva a foto de um pet
function salvarFotoPet(array $arquivo): ?string
{
    // Verifica se nenhum arquivo foi enviado
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // Verifica se ocorreu algum erro durante o upload
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a foto do pet.');
    }

    // Limita o tamanho máximo da imagem para 5 MB
    if (($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('A foto deve ter no máximo 5 MB.');
    }

    // Identifica o tipo real do arquivo enviado
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);

    // Define as extensões permitidas para as imagens
    $extensoes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // Impede o envio de arquivos que não sejam imagens permitidas
    if (!isset($extensoes[$mime])) {
        throw new RuntimeException('Use uma imagem JPG, PNG ou WEBP.');
    }

    // Define a pasta onde as fotos dos pets serão armazenadas
    $pasta = dirname(__DIR__) . '/uploads/pets';

    // Cria a pasta caso ela ainda não exista
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new RuntimeException('Não foi possível criar a pasta das fotos.');
    }

    // Gera um nome aleatório para evitar conflitos entre arquivos
    $nome = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
    $destino = $pasta . '/' . $nome;

    // Move a imagem enviada para a pasta definitiva
    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível salvar a foto do pet.');
    }

    // Retorna o caminho da imagem salvo no sistema
    return 'uploads/pets/' . $nome;
}


// Formata um valor numérico como moeda brasileira
function formatarMoeda(float|int|string|null $valor): string
{
    return 'R$ ' . number_format((float) ($valor ?? 0), 2, ',', '.');
}


// Formata o CPF para o padrão XXX.XXX.XXX-XX
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


// Formata telefones brasileiros com 10 ou 11 números
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


// Converte uma data do banco para o formato brasileiro
function formatarData(?string $data): string
{
    if ($data === null || $data === '') {
        return 'Não informada';
    }

    $objeto = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));

    return $objeto ? $objeto->format('d/m/Y') : 'Não informada';
}


// Gera as iniciais do nome para exibição em avatares
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


// Converte valores booleanos para "Sim" ou "Não"
function textoBooleano(mixed $valor): string
{
    if ($valor === null || $valor === '') {
        return 'Não informado';
    }

    return (int) $valor === 1 ? 'Sim' : 'Não';
}


// Converte o código do porte do pet para um nome exibível
function nomePorte(?string $porte): string
{
    return match ($porte) {
        'PEQUENO' => 'Pequeno',
        'MEDIO'   => 'Médio',
        'GRANDE'  => 'Grande',
        default   => 'Não informado',
    };
}


// Converte o código do sexo do pet para um nome exibível
function nomeSexo(?string $sexo): string
{
    return match ($sexo) {
        'MACHO' => 'Macho',
        'FEMEA' => 'Fêmea',
        default => 'Não informado',
    };
}


// Calcula a idade do pet com base na data de nascimento
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


// Monta o endereço completo do cliente
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


// Inicia a sessão do usuário
function iniciarSessao(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}


// Converte o código do perfil para o nome exibido no sistema
function nomePerfilUsuario(?string $perfil): string
{
    return match ($perfil) {
        'ADMINISTRADOR' => 'Administradora',
        'FUNCIONARIO'   => 'Funcionário(a)',
        default         => 'Sem perfil',
    };
}


// Busca os dados do usuário atualmente conectado
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

    // Remove a sessão caso o usuário não esteja mais ativo
    if (!$usuario) {
        unset($_SESSION['usuario_id']);
        return null;
    }

    return $usuario;
}


// Lista todos os usuários ativos do sistema
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


// Armazena uma mensagem para ser exibida ao usuário
function definirMensagemUsuario(string $tipo, string $texto): void
{
    iniciarSessao();

    $_SESSION['mensagem_usuario'] = [
        'tipo'  => $tipo,
        'texto' => $texto,
    ];
}


// Recupera e remove uma mensagem armazenada na sessão
function consumirMensagemUsuario(): ?array
{
    iniciarSessao();

    $mensagem = $_SESSION['mensagem_usuario'] ?? null;

    unset($_SESSION['mensagem_usuario']);

    return is_array($mensagem) ? $mensagem : null;
}


// Valida a página de retorno para evitar redirecionamentos indevidos
function paginaRetornoSegura(?string $pagina): string
{
    $pagina = trim((string) $pagina);

    if ($pagina === '') {
        return 'index.php';
    }

    $partes = parse_url($pagina);
    $caminho = basename((string) ($partes['path'] ?? ''));

    if (!preg_match('/^[a-zA-Z0-9_-]+\.php$/', $caminho)) {
        return 'index.php';
    }

    $query = isset($partes['query']) && $partes['query'] !== ''
        ? '?' . $partes['query']
        : '';

    return $caminho . $query;
}


// Verifica se uma data está no formato utilizado pelo banco
function dataSqlValida(?string $data): bool
{
    if ($data === null || $data === '') {
        return false;
    }

    $objeto = DateTime::createFromFormat('Y-m-d', $data);

    return $objeto !== false
        && $objeto->format('Y-m-d') === $data;
}


// Verifica se uma hora está no formato utilizado pelo banco
function horaSqlValida(?string $hora): bool
{
    if ($hora === null || $hora === '') {
        return false;
    }

    $objeto = DateTime::createFromFormat('H:i', $hora);

    return $objeto !== false
        && $objeto->format('H:i') === $hora;
}


// Formata data e hora para exibição na agenda
function formatarDataHoraAgenda(?string $dataHora): string
{
    if ($dataHora === null || $dataHora === '') {
        return 'Não informada';
    }

    try {
        return (new DateTime($dataHora))->format('d/m/Y à\s H:i');
    } catch (Throwable) {
        return 'Não informada';
    }
}


// Formata somente a hora de um agendamento
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


// Converte o status do agendamento para um texto amigável
function nomeStatusAgendamento(?string $status): string
{
    return match ($status) {
        'SOLICITADO'     => 'Solicitado',
        'CONFIRMADO'     => 'Confirmado',
        'RECUSADO'       => 'Recusado',
        'CANCELADO'      => 'Cancelado',
        'EM_ATENDIMENTO' => 'Em atendimento',
        'CONCLUIDO'      => 'Concluído',
        default          => 'Não informado',
    };
}


// Define a classe visual utilizada para cada status da agenda
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


// Funções relacionadas ao funcionamento da agenda


// Normaliza o nome de uma categoria para facilitar as comparações
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


// Verifica se o serviço pertence à categoria de banho e tosa
function ehBanhoTosa(?string $categoria, ?string $servico = null): bool
{
    $texto = normalizarCategoriaAgenda(
        trim((string) $categoria . ' ' . (string) $servico)
    );

    return str_contains($texto, 'banho')
        || str_contains($texto, 'tosa')
        || str_contains($texto, 'higien');
}


// Converte o nome da categoria da agenda para o texto exibido
function nomeCategoriaAgenda(string $categoria): string
{
    $normalizada = normalizarCategoriaAgenda($categoria);

    return match (true) {
        str_contains($normalizada, 'taxi')     => 'Taxidog',
        str_contains($normalizada, 'veterin')  => 'Consulta veterinária',
        str_contains($normalizada, 'banho'),
        str_contains($normalizada, 'tosa')     => 'Banho e tosa',
        str_contains($normalizada, 'creche')   => 'Creche',
        str_contains($normalizada, 'hotel')    => 'Hotel',
        default                                => $categoria,
    };
}