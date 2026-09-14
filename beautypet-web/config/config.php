<?php

// Define a URL base da aplicação web para o roteamento de assets e links
define('BASE_URL', '/Projeto-BeautyPet/beautypet-web');

// Carrega a função responsável por ler o arquivo .env
require_once __DIR__ . '/env.php';

try {
    // Carrega as variáveis de ambiente (onde a DATABASE_URL do Supabase deve estar)
    carregarEnv(__DIR__ . '/.env');
    $databaseUrl = $_ENV['DATABASE_URL'];
    
    // Extrai os dados da string de conexão nativa do Supabase
    $dadosConexao = parse_url($databaseUrl);

    // Cria a conexão PDO forçando o driver pgsql e sslmode requerido pelo Supabase
    $pdo = new PDO(
        "pgsql:host={$dadosConexao['host']};port={$dadosConexao['port']};dbname=" . ltrim($dadosConexao['path'], '/') . ";sslmode=require",
        $dadosConexao['user'],
        $dadosConexao['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

} catch (PDOException $erro) {
    exit('Não foi possível conectar ao banco de dados: ' . $erro->getMessage());
} catch (Exception $erro) {
    exit('Erro na configuração de ambiente: ' . $erro->getMessage());
}

function escapar(?string $valor): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Remove caracteres não numéricos de um valor, útil para CPF, telefone e CEP.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function somenteNumeros(?string $valor): ?string
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($valor === null || trim($valor) === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return preg_replace('/\D+/', '', $valor);
}

/**
 * Valida matematicamente os dígitos verificadores de um CPF.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function cpfValido(?string $cpf): bool
{
    // Guarda apenas os números do CPF para validar e pesquisar sem influência da máscara visual.
    $cpf = preg_replace('/\D+/', '', $cpf ?? '');

    // Verifica a condição abaixo antes de executar este bloco.
    if (strlen($cpf) !== 11) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return false;
    }

    // CPFs com todos os números iguais são inválidos.
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return false;
    }

    // Validação dos dois dígitos verificadores.
    for ($posicao = 9; $posicao <= 10; $posicao++) {
        // Armazena em "soma" o valor necessário para as próximas etapas deste fluxo.
        $soma = 0;

        // Repete o bloco abaixo enquanto o contador estiver dentro do intervalo definido.
        for ($indice = 0; $indice < $posicao; $indice++) {
            $soma += (int) $cpf[$indice] * (($posicao + 1) - $indice);
        }

        // Armazena em "digito" o valor necessário para as próximas etapas deste fluxo.
        $digito = (10 * $soma) % 11;
        // Armazena em "digito" o valor necessário para as próximas etapas deste fluxo.
        $digito = $digito === 10 ? 0 : $digito;

        // Verifica a condição abaixo antes de executar este bloco.
        if ((int) $cpf[$posicao] !== $digito) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return false;
        }
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return true;
}

/**
 * Normaliza um texto e devolve null quando o conteúdo estiver vazio.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function textoOuNull(?string $valor): ?string
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($valor === null) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Armazena o valor financeiro "valor" já normalizado para os cálculos seguintes.
    $valor = trim($valor);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $valor === '' ? null : $valor;
}

/**
 * Converte um valor para inteiro ou devolve null quando não houver um número válido.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function inteiroOuNull(mixed $valor): ?int
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($valor === null || $valor === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return filter_var($valor, FILTER_VALIDATE_INT) !== false
        ? (int) $valor
        : null;
}

/**
 * Executa a responsabilidade específica de "salvarFotoPet" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function salvarFotoPet(array $arquivo): ?string
{
    // Verifica a condição abaixo antes de executar este bloco.
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
        throw new RuntimeException('Não foi possível enviar a foto do pet.');
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
        throw new RuntimeException('A foto deve ter no máximo 5 MB.');
    }

    // Armazena em "finfo" o valor necessário para as próximas etapas deste fluxo.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    // Armazena em "mime" o valor necessário para as próximas etapas deste fluxo.
    $mime = $finfo->file($arquivo['tmp_name']);

    // Armazena a coleção "extensoes" carregada para ser percorrida e exibida/processada a seguir.
    $extensoes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // Verifica a condição abaixo antes de executar este bloco.
    if (!isset($extensoes[$mime])) {
        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
        throw new RuntimeException('Use uma imagem JPG, PNG ou WEBP.');
    }

    // Como este arquivo agora está dentro de /config, dirname(__DIR__) volta para a raiz do projeto.
    // A pasta física das fotos continua sendo /uploads/pets na raiz.
    $pasta = dirname(__DIR__) . '/uploads/pets';

    // Verifica a condição abaixo antes de executar este bloco.
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
        throw new RuntimeException('Não foi possível criar a pasta das fotos.');
    }

    // Guarda o nome informado no formulário após a normalização necessária.
    $nome = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
    // Armazena em "destino" o valor necessário para as próximas etapas deste fluxo.
    $destino = $pasta . '/' . $nome;

    // Verifica a condição abaixo antes de executar este bloco.
    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
        throw new RuntimeException('Não foi possível salvar a foto do pet.');
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return 'uploads/pets/' . $nome;
}


/**
 * Executa a responsabilidade específica de "urlFotoPet" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function urlFotoPet(?string $foto): ?string
{
    // Armazena em "foto" o valor necessário para as próximas etapas deste fluxo.
    $foto = textoOuNull($foto);

    // Verifica a condição abaixo antes de executar este bloco.
    if ($foto === null) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Armazena em "relativa" o valor necessário para as próximas etapas deste fluxo.
    $relativa = ltrim(str_replace('\\', '/', $foto), '/');
    // Monta o caminho físico da foto a partir da raiz do projeto, e não da pasta /config.
    $destinoAtual = dirname(__DIR__) . '/' . $relativa;

    // Verifica a condição abaixo antes de executar este bloco.
    if (is_file($destinoAtual)) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return '../' . $relativa;
    }

    // Compatibilidade com fotos de uma estrutura anterior, que ficavam um nível acima da raiz atual.
    $destinoAntigo = dirname(dirname(__DIR__)) . '/' . $relativa;

    // Verifica a condição abaixo antes de executar este bloco.
    if (is_file($destinoAntigo)) {
        // Armazena em "pastaAtual" o valor necessário para as próximas etapas deste fluxo.
        $pastaAtual = dirname($destinoAtual);

        // Verifica a condição abaixo antes de executar este bloco.
        if (!is_dir($pastaAtual)) {
            @mkdir($pastaAtual, 0755, true);
        }

        @copy($destinoAntigo, $destinoAtual);

        // Verifica a condição abaixo antes de executar este bloco.
        if (is_file($destinoAtual)) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return '../' . $relativa;
        }
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return null;
}

/**
 * Executa a responsabilidade específica de "formatarMoeda" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function formatarMoeda(float|int|string|null $valor): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return 'R$ ' . number_format((float) ($valor ?? 0), 2, ',', '.');
}

/**
 * Executa a responsabilidade específica de "formatarCpf" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function formatarCpf(?string $cpf): string
{
    // Guarda apenas os números do CPF para validar e pesquisar sem influência da máscara visual.
    $cpf = preg_replace('/\D+/', '', $cpf ?? '');

    // Verifica a condição abaixo antes de executar este bloco.
    if (strlen($cpf) !== 11) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return $cpf !== '' ? $cpf : 'Não informado';
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return substr($cpf, 0, 3) . '.'
        . substr($cpf, 3, 3) . '.'
        . substr($cpf, 6, 3) . '-'
        . substr($cpf, 9, 2);
}

/**
 * Executa a responsabilidade específica de "formatarTelefone" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function formatarTelefone(?string $telefone): string
{
    // Armazena em "telefone" o valor necessário para as próximas etapas deste fluxo.
    $telefone = preg_replace('/\D+/', '', $telefone ?? '');

    // Verifica a condição abaixo antes de executar este bloco.
    if (strlen($telefone) === 11) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return '(' . substr($telefone, 0, 2) . ') '
            . substr($telefone, 2, 5) . '-'
            . substr($telefone, 7, 4);
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (strlen($telefone) === 10) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return '(' . substr($telefone, 0, 2) . ') '
            . substr($telefone, 2, 4) . '-'
            . substr($telefone, 6, 4);
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $telefone !== '' ? $telefone : 'Não informado';
}

/**
 * Executa a responsabilidade específica de "formatarData" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function formatarData(?string $data): string
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($data === null || $data === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'Não informada';
    }

    // Armazena em "objeto" o valor necessário para as próximas etapas deste fluxo.
    $objeto = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $objeto ? $objeto->format('d/m/Y') : 'Não informada';
}

/**
 * Executa a responsabilidade específica de "iniciaisNome" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function iniciaisNome(?string $nome): string
{
    // Armazena a coleção "partes" carregada para ser percorrida e exibida/processada a seguir.
    $partes = preg_split('/\s+/', trim($nome ?? ''), -1, PREG_SPLIT_NO_EMPTY);

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$partes) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return '?';
    }

    // Armazena em "primeiroNome" o valor necessário para as próximas etapas deste fluxo.
    $primeiroNome = $partes[0];
    // Armazena em "ultimoNome" o valor necessário para as próximas etapas deste fluxo.
    $ultimoNome = $partes[count($partes) - 1];

    // Armazena em "primeira" o valor necessário para as próximas etapas deste fluxo.
    $primeira = function_exists('mb_substr')
        ? mb_substr($primeiroNome, 0, 1)
        : substr($primeiroNome, 0, 1);

    // Armazena em "ultima" o valor necessário para as próximas etapas deste fluxo.
    $ultima = count($partes) > 1
        ? (
            function_exists('mb_substr')
                ? mb_substr($ultimoNome, 0, 1)
                : substr($ultimoNome, 0, 1)
        )
        : '';

    // Armazena a coleção "iniciais" carregada para ser percorrida e exibida/processada a seguir.
    $iniciais = $primeira . $ultima;

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return function_exists('mb_strtoupper')
        ? mb_strtoupper($iniciais)
        : strtoupper($iniciais);
}

/**
 * Executa a responsabilidade específica de "textoBooleano" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function textoBooleano(mixed $valor): string
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($valor === null || $valor === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'Não informado';
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return (int) $valor === 1 ? 'Sim' : 'Não';
}

/**
 * Executa a responsabilidade específica de "nomePorte" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function nomePorte(?string $porte): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return match ($porte) {
        'PEQUENO' => 'Pequeno',
        'MEDIO'   => 'Médio',
        'GRANDE'  => 'Grande',
        default   => 'Não informado',
    };
}

/**
 * Executa a responsabilidade específica de "nomeSexo" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function nomeSexo(?string $sexo): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return match ($sexo) {
        'MACHO' => 'Macho',
        'FEMEA' => 'Fêmea',
        default => 'Não informado',
    };
}

/**
 * Executa a responsabilidade específica de "idadePet" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function idadePet(?string $dataNascimento, bool $aproximada = false): string
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($dataNascimento === null || $dataNascimento === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'Não informada';
    }

    // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
    try {
        // Armazena em "nascimento" o valor necessário para as próximas etapas deste fluxo.
        $nascimento = new DateTime($dataNascimento);
        // Armazena em "hoje" o valor necessário para as próximas etapas deste fluxo.
        $hoje = new DateTime();
        // Armazena em "idade" o valor necessário para as próximas etapas deste fluxo.
        $idade = $nascimento->diff($hoje);

        // Armazena em "texto" o valor necessário para as próximas etapas deste fluxo.
        $texto = $idade->y === 1
            ? '1 ano'
            : $idade->y . ' anos';

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return $aproximada ? 'Aproximadamente ' . $texto : $texto;
    // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
    } catch (Throwable) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'Não informada';
    }
}

/**
 * Busca um pet específico e devolve dados formatados para preencher campos de pesquisa.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function buscarPetParaCampo(PDO $pdo, ?int $petId): ?array
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($petId === null) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nome, c.nome AS tutor
         FROM pets p
         JOIN clientes c ON c.id = p.cliente_id
         WHERE p.id = :id
           AND p.ativo = TRUE
           AND c.ativo = TRUE
         LIMIT 1'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $petId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $pet = $stmt->fetch();

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $pet ?: null;
}

/**
 * Busca um tutor específico e devolve dados formatados para preencher campos de pesquisa.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function buscarTutorParaCampo(PDO $pdo, ?int $tutorId): ?array
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($tutorId === null) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT id, nome, cpf, email, telefone_principal
         FROM clientes
         WHERE id = :id
           AND ativo = TRUE
         LIMIT 1'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $tutorId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $tutor = $stmt->fetch();

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $tutor ?: null;
}

/**
 * Executa a responsabilidade específica de "enderecoCompleto" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function enderecoCompleto(array $cliente): string
{
    // Armazena a coleção "partes" carregada para ser percorrida e exibida/processada a seguir.
    $partes = [];

    // Verifica a condição abaixo antes de executar este bloco.
    if (!empty($cliente['logradouro'])) {
        // Armazena em "rua" o valor necessário para as próximas etapas deste fluxo.
        $rua = $cliente['logradouro'];

        // Verifica a condição abaixo antes de executar este bloco.
        if (!empty($cliente['numero'])) {
            $rua .= ', ' . $cliente['numero'];
        }

        $partes[] = $rua;
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (!empty($cliente['bairro'])) {
        $partes[] = $cliente['bairro'];
    }

    // Armazena em "cidadeEstado" o valor necessário para as próximas etapas deste fluxo.
    $cidadeEstado = trim(
        ($cliente['cidade'] ?? '')
        . (!empty($cliente['estado']) ? ' - ' . $cliente['estado'] : '')
    );

    // Verifica a condição abaixo antes de executar este bloco.
    if ($cidadeEstado !== '') {
        $partes[] = $cidadeEstado;
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $partes ? implode(' • ', $partes) : 'Não informado';
}


/**
 * Executa a responsabilidade específica de "iniciarSessao" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function iniciarSessao(): void
{
    // Verifica a condição abaixo antes de executar este bloco.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Executa a responsabilidade específica de "nomePerfilUsuario" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function nomePerfilUsuario(?string $perfil): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return match ($perfil) {
        'ADMINISTRADOR' => 'Administradora',
        'FUNCIONARIO'   => 'Funcionário(a)',
        default         => 'Sem perfil',
    };
}

/**
 * Obtém os dados do usuário autenticado na sessão atual.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function obterUsuarioAtual(PDO $pdo): ?array
{
    // Guarda somente o ID do usuário atual para registrar autoria das operações no banco.
    $usuarioId = $_SESSION['usuario_id'] ?? null;

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$usuarioId) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Prepara uma consulta em "usuarios" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT id, nome, email, perfil, ativo
         FROM usuarios
         WHERE id = :id
           AND ativo = TRUE'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => (int) $usuarioId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $usuario = $stmt->fetch();

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$usuario) {
        unset($_SESSION['usuario_id']);
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $usuario;
}

/**
 * Executa a responsabilidade específica de "listarUsuariosAtivos" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function listarUsuariosAtivos(PDO $pdo): array
{
    // Prepara uma consulta em "usuarios" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->query(
        'SELECT id, nome, email, perfil
         FROM usuarios
         WHERE ativo = TRUE
         ORDER BY nome'
    );

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $stmt->fetchAll();
}

/**
 * Executa a responsabilidade específica de "definirMensagemUsuario" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function definirMensagemUsuario(string $tipo, string $texto): void
{
    iniciarSessao();
    $_SESSION['mensagem_usuario'] = [
        'tipo'  => $tipo,
        'texto' => $texto,
    ];
}

/**
 * Executa a responsabilidade específica de "consumirMensagemUsuario" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function consumirMensagemUsuario(): ?array
{
    iniciarSessao();

    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = $_SESSION['mensagem_usuario'] ?? null;
    unset($_SESSION['mensagem_usuario']);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return is_array($mensagem) ? $mensagem : null;
}

/**
 * Executa a responsabilidade específica de "paginaRetornoSegura" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function paginaRetornoSegura(?string $pagina): string
{
    // Armazena em "pagina" o valor necessário para as próximas etapas deste fluxo.
    $pagina = trim((string) $pagina);

    // Verifica a condição abaixo antes de executar este bloco.
    if ($pagina === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'index.php';
    }

    // Armazena a coleção "partes" carregada para ser percorrida e exibida/processada a seguir.
    $partes = parse_url($pagina);
    // Armazena em "caminho" o valor necessário para as próximas etapas deste fluxo.
    $caminho = basename((string) ($partes['path'] ?? ''));

    // Verifica a condição abaixo antes de executar este bloco.
    if (!preg_match('/^[a-zA-Z0-9_-]+\\.php$/', $caminho)) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'index.php';
    }

    // Armazena em "query" o valor necessário para as próximas etapas deste fluxo.
    $query = isset($partes['query']) && $partes['query'] !== ''
        ? '?' . $partes['query']
        : '';

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $caminho . $query;
}

/**
 * Confere se a data possui formato válido para uso no banco.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function dataSqlValida(?string $data): bool
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($data === null || $data === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return false;
    }

    // Armazena em "objeto" o valor necessário para as próximas etapas deste fluxo.
    $objeto = DateTime::createFromFormat('Y-m-d', $data);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $objeto !== false
        && $objeto->format('Y-m-d') === $data;
}

/**
 * Confere se a hora informada possui formato válido.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function horaSqlValida(?string $hora): bool
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($hora === null || $hora === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return false;
    }

    // Armazena em "objeto" o valor necessário para as próximas etapas deste fluxo.
    $objeto = DateTime::createFromFormat('H:i', $hora);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $objeto !== false
        && $objeto->format('H:i') === $hora;
}

/**
 * Executa a responsabilidade específica de "formatarDataHoraAgenda" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function formatarDataHoraAgenda(?string $dataHora): string
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($dataHora === null || $dataHora === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'Não informada';
    }

    // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
    try {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return (new DateTime($dataHora))->format('d/m/Y \à\s H:i');
    // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
    } catch (Throwable) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'Não informada';
    }
}

/**
 * Executa a responsabilidade específica de "formatarHoraAgenda" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function formatarHoraAgenda(?string $dataHora): string
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($dataHora === null || $dataHora === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return '--:--';
    }

    // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
    try {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return (new DateTime($dataHora))->format('H:i');
    // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
    } catch (Throwable) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return '--:--';
    }
}

/**
 * Executa a responsabilidade específica de "nomeStatusAgendamento" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function nomeStatusAgendamento(?string $status): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
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

/**
 * Executa a responsabilidade específica de "classeStatusAgendamento" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function classeStatusAgendamento(?string $status): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
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
    // Armazena em "texto" o valor necessário para as próximas etapas deste fluxo.
    $texto = trim((string) $texto);
    // Armazena em "texto" o valor necessário para as próximas etapas deste fluxo.
    $texto = function_exists('mb_strtolower')
        ? mb_strtolower($texto, 'UTF-8')
        : strtolower($texto);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return strtr($texto, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ]);
}

/**
 * Identifica se uma categoria/serviço pertence ao grupo de banho e tosa.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function ehBanhoTosa(?string $categoria, ?string $servico = null): bool
{
    // Armazena em "texto" o valor necessário para as próximas etapas deste fluxo.
    $texto = normalizarCategoriaAgenda(
        trim((string) $categoria . ' ' . (string) $servico)
    );

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return str_contains($texto, 'banho')
        || str_contains($texto, 'tosa')
        || str_contains($texto, 'higien');
}

/**
 * Executa a responsabilidade específica de "nomeCategoriaAgenda" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function nomeCategoriaAgenda(string $categoria): string
{
    // Armazena em "normalizada" o valor necessário para as próximas etapas deste fluxo.
    $normalizada = normalizarCategoriaAgenda($categoria);

    // Devolve o resultado calculado para o ponto que chamou esta função.
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

/**
 * Executa a responsabilidade específica de "enderecoAbreviadoAgenda" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function enderecoAbreviadoAgenda(array $item): string
{
    // Armazena em "logradouro" o valor necessário para as próximas etapas deste fluxo.
    $logradouro = trim((string) ($item['logradouro'] ?? ''));
    // Armazena em "numero" o valor necessário para as próximas etapas deste fluxo.
    $numero = trim((string) ($item['numero'] ?? ''));
    // Armazena em "bairro" o valor necessário para as próximas etapas deste fluxo.
    $bairro = trim((string) ($item['bairro'] ?? ''));
    // Armazena em "cidade" o valor necessário para as próximas etapas deste fluxo.
    $cidade = trim((string) ($item['cidade'] ?? ''));
    // Armazena em "estado" o valor necessário para as próximas etapas deste fluxo.
    $estado = trim((string) ($item['estado'] ?? ''));

    // Armazena em "linha" o valor necessário para as próximas etapas deste fluxo.
    $linha = $logradouro;

    // Verifica a condição abaixo antes de executar este bloco.
    if ($numero !== '') {
        $linha .= ($linha !== '' ? ', ' : '') . $numero;
    }

    // Armazena em "local" o valor necessário para as próximas etapas deste fluxo.
    $local = $bairro;

    // Verifica a condição abaixo antes de executar este bloco.
    if ($local === '' && $cidade !== '') {
        // Armazena em "local" o valor necessário para as próximas etapas deste fluxo.
        $local = $cidade . ($estado !== '' ? '/' . $estado : '');
    }

    // Armazena a coleção "partes" carregada para ser percorrida e exibida/processada a seguir.
    $partes = array_values(
        array_filter([$linha, $local], static fn ($valor) => $valor !== '')
    );

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $partes ? implode(' • ', $partes) : 'Endereço não informado';
}

/**
 * Executa a responsabilidade específica de "formatarDuracaoAgenda" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function formatarDuracaoAgenda(int $minutos): string
{
    // Armazena a coleção "horas" carregada para ser percorrida e exibida/processada a seguir.
    $horas = intdiv($minutos, 60);
    // Armazena em "restante" o valor necessário para as próximas etapas deste fluxo.
    $restante = $minutos % 60;

    // Verifica a condição abaixo antes de executar este bloco.
    if ($horas === 0) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return $restante . ' min';
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($restante === 0) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return $horas . 'h';
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $horas . 'h' . str_pad((string) $restante, 2, '0', STR_PAD_LEFT);
}

/**
 * Carrega o serviço e sua categoria a partir do ID selecionado.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function buscarServicoAgenda(PDO $pdo, int $servicoId): ?array
{
    // Prepara uma consulta em "servicos" para carregar os dados necessários a esta etapa.
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
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $servicoId]);

    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $servico = $stmt->fetch();

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $servico ?: null;
}

/**
 * Carrega os dados de um agendamento para preencher o formulário de edição.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function buscarAgendamentoEdicao(PDO $pdo, int $id): ?array
{
    // Prepara uma consulta em "agendamentos" para carregar os dados necessários a esta etapa.
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
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $id]);

    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $item = $stmt->fetch();

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $item ?: null;
}

/**
 * Localiza a agenda-padrão de banho e tosa válida para uma determinada data.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function buscarPadraoBanhoDaData(PDO $pdo, string $data): ?array
{
    // Armazena em "diaSemana" o valor necessário para as próximas etapas deste fluxo.
    $diaSemana = (int) (new DateTime($data))->format('N');

    // Prepara uma consulta em "agenda_padrao_banho_tosa" para carregar os dados necessários a esta etapa.
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

    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([
        ':dia_semana'  => $diaSemana,
        ':data_inicio' => $data,
        ':data_fim'    => $data,
    ]);

    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $padrao = $stmt->fetch();

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $padrao ?: null;
}

/**
 * Carrega os horários e capacidades definidos em uma agenda-padrão.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function buscarHorariosPadraoBanho(PDO $pdo, int $padraoId): array
{
    // Prepara uma consulta em "agenda_padrao_banho_tosa_horarios" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT horario, quantidade_vagas
         FROM agenda_padrao_banho_tosa_horarios
         WHERE agenda_padrao_id = :agenda_padrao_id
         ORDER BY horario'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':agenda_padrao_id' => $padraoId]);

    // Armazena a coleção "horarios" carregada para ser percorrida e exibida/processada a seguir.
    $horarios = [];

    // Percorre cada item da coleção para processar os registros individualmente.
    foreach ($stmt->fetchAll() as $item) {
        // Armazena o horário "hora" utilizado na regra de agendamento/atendimento.
        $hora = substr((string) $item['horario'], 0, 5);
        $horarios[$hora] = (int) $item['quantidade_vagas'];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $horarios;
}

/**
 * Calcula quantas vagas já estão ocupadas em cada bloco de banho e tosa.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function buscarOcupacaoBanhoTosa(
    PDO $pdo,
    string $data,
    array $horariosPadrao,
    ?int $ignorarAgendamentoId = null
): array {
    // Guarda quantas vagas já estão ocupadas em cada horário para impedir sobreposição da capacidade.
    $ocupacao = array_fill_keys(array_keys($horariosPadrao), 0);

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$horariosPadrao) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return $ocupacao;
    }

    // Prepara uma consulta em "agendamentos" para carregar os dados necessários a esta etapa.
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

    // Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
    $parametros = [
        ':data_inicio' => $data . ' 00:00:00',
        ':data_fim'    => $data . ' 00:00:00',
    ];

    // Verifica a condição abaixo antes de executar este bloco.
    if ($ignorarAgendamentoId !== null) {
        $sql .= ' AND a.id <> :ignorar_id';
        $parametros[':ignorar_id'] = $ignorarAgendamentoId;
    }

    // Prepara a consulta SQL que será executada com parâmetros separados para manter segurança e clareza.
    $stmt = $pdo->prepare($sql);
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute($parametros);
    // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
    $agendamentos = $stmt->fetchAll();

    // Percorre cada item da coleção para processar os registros individualmente.
    foreach ($horariosPadrao as $hora => $_capacidade) {
        // Armazena em "inicioSlot" o valor necessário para as próximas etapas deste fluxo.
        $inicioSlot = new DateTime($data . ' ' . $hora . ':00');
        // Armazena em "fimSlot" o valor necessário para as próximas etapas deste fluxo.
        $fimSlot = (clone $inicioSlot)->modify('+30 minutes');

        // Percorre cada item da coleção para processar os registros individualmente.
        foreach ($agendamentos as $agendamento) {
            // Monta a data e hora completas de início no formato esperado pelo banco de dados.
            $inicio = new DateTime((string) $agendamento['data_hora_inicio']);
            // Guarda a data e hora completas de término do agendamento quando houver.
            $fim = new DateTime((string) $agendamento['data_hora_fim']);

            // Verifica a condição abaixo antes de executar este bloco.
            if ($inicio < $fimSlot && $fim > $inicioSlot) {
                $ocupacao[$hora]++;
            }
        }
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $ocupacao;
}

/**
 * Confere se todos os blocos de 30 minutos necessários possuem vaga para a duração escolhida.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function validarVagasBanhoTosa(
    array $horariosPadrao,
    array $ocupacao,
    string $horaInicio,
    int $duracaoMinutos
): array {
    // Verifica a condição abaixo antes de executar este bloco.
    if (
        $duracaoMinutos < 30
        || $duracaoMinutos > 480
        || $duracaoMinutos % 30 !== 0
    ) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return [false, 'Selecione uma duração válida, de 30 em 30 minutos.'];
    }

    // Armazena a coleção "blocos" carregada para ser percorrida e exibida/processada a seguir.
    $blocos = intdiv($duracaoMinutos, 30);
    // Armazena em "cursor" o valor necessário para as próximas etapas deste fluxo.
    $cursor = DateTime::createFromFormat('H:i', $horaInicio);

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$cursor) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return [false, 'Selecione um horário válido.'];
    }

    // Repete o bloco abaixo enquanto o contador estiver dentro do intervalo definido.
    for ($i = 0; $i < $blocos; $i++) {
        // Armazena o horário "hora" utilizado na regra de agendamento/atendimento.
        $hora = $cursor->format('H:i');

        // Verifica a condição abaixo antes de executar este bloco.
        if (!array_key_exists($hora, $horariosPadrao)) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return [
                false,
                'O período escolhido ultrapassa os horários disponíveis da agenda-padrão.'
            ];
        }

        // Armazena em "capacidade" o valor necessário para as próximas etapas deste fluxo.
        $capacidade = (int) $horariosPadrao[$hora];
        // Armazena a coleção "ocupadas" carregada para ser percorrida e exibida/processada a seguir.
        $ocupadas = (int) ($ocupacao[$hora] ?? 0);

        // Verifica a condição abaixo antes de executar este bloco.
        if ($capacidade <= 0 || $ocupadas >= $capacidade) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return [
                false,
                'Uma das faixas de 30 minutos escolhidas já está sem vaga.'
            ];
        }

        $cursor->modify('+30 minutes');
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return [true, ''];
}

/**
 * Executa a responsabilidade específica de "horaEmBlocoDeTrinta" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function horaEmBlocoDeTrinta(?string $hora): bool
{
    // Verifica a condição abaixo antes de executar este bloco.
    if (!horaSqlValida($hora)) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return false;
    }

    // Armazena em "minuto" o valor necessário para as próximas etapas deste fluxo.
    $minuto = (int) substr((string) $hora, 3, 2);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return in_array($minuto, [0, 30], true);
}

/**
 * Executa a responsabilidade específica de "gerarHorariosDeTrinta" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function gerarHorariosDeTrinta(string $horaInicio, string $horaFim): array
{
    // Monta a data e hora completas de início no formato esperado pelo banco de dados.
    $inicio = DateTime::createFromFormat('H:i', $horaInicio);
    // Guarda a data e hora completas de término do agendamento quando houver.
    $fim = DateTime::createFromFormat('H:i', $horaFim);

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$inicio || !$fim || $fim <= $inicio) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return [];
    }

    // Armazena a coleção "horarios" carregada para ser percorrida e exibida/processada a seguir.
    $horarios = [];
    // Armazena em "cursor" o valor necessário para as próximas etapas deste fluxo.
    $cursor = clone $inicio;

    // Mantém a repetição enquanto a condição permanecer verdadeira.
    while ($cursor < $fim) {
        $horarios[] = $cursor->format('H:i');
        $cursor->modify('+30 minutes');

        // Verifica a condição abaixo antes de executar este bloco.
        if (count($horarios) > 48) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return [];
        }
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $horarios;
}

/**
 * Executa a responsabilidade específica de "padraoSobreposto" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function padraoSobreposto(
    PDO $pdo,
    int $diaSemana,
    string $dataInicio,
    ?string $dataFim,
    ?int $ignorarId = null
): bool {
    // Prepara uma consulta em "agenda_padrao_banho_tosa" para carregar os dados necessários a esta etapa.
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

    // Verifica a condição abaixo antes de executar este bloco.
    if ($ignorarId !== null) {
        $sql .= ' AND id <> :ignorar_id';
        $parametros[':ignorar_id'] = $ignorarId;
    }

    // Prepara a consulta SQL que será executada com parâmetros separados para manter segurança e clareza.
    $stmt = $pdo->prepare($sql);
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute($parametros);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Executa a responsabilidade específica de "quantidadeVagas" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function quantidadeVagas(mixed $valor): int
{
    // Armazena em "numero" o valor necessário para as próximas etapas deste fluxo.
    $numero = filter_var($valor, FILTER_VALIDATE_INT);

    // Verifica a condição abaixo antes de executar este bloco.
    if ($numero === false) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 0;
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return max(0, min(5, (int) $numero));
}

// A sessão é usada para saber qual usuário está ativo.
iniciarSessao();

// Troca de usuário: funciona como uma tela de login, usando e-mail e senha.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'trocar_usuario') {
    // Armazena em "emailDigitado" o valor necessário para as próximas etapas deste fluxo.
    $emailDigitado = textoOuNull($_POST['email_usuario'] ?? null);
    // Armazena em "senhaDigitada" o valor necessário para as próximas etapas deste fluxo.
    $senhaDigitada = (string) ($_POST['senha'] ?? '');
    // Armazena em "retorno" o valor necessário para as próximas etapas deste fluxo.
    $retorno = paginaRetornoSegura($_POST['retorno'] ?? 'index.php');

    // Padroniza o e-mail antes de comparar ou salvar, evitando diferenças por espaços ou letras maiúsculas.
    $emailNormalizado = $emailDigitado === null
        ? null
        : (function_exists('mb_strtolower')
            ? mb_strtolower(trim($emailDigitado), 'UTF-8')
            : strtolower(trim($emailDigitado)));

    // Verifica a condição abaixo antes de executar este bloco.
    if ($emailNormalizado === null || $senhaDigitada === '') {
        $_SESSION['usuario_troca_email'] = $emailNormalizado ?? '';
        definirMensagemUsuario('error', 'Digite o e-mail e a senha.');
        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header('Location: ' . $retorno);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    // Prepara uma consulta em "usuarios" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT id, nome, email, senha_hash
         FROM usuarios
         WHERE LOWER(TRIM(email)) = :email
           AND ativo = TRUE
         LIMIT 1'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':email' => $emailNormalizado]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $usuarioTroca = $stmt->fetch();

    // Verifica a condição abaixo antes de executar este bloco.
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