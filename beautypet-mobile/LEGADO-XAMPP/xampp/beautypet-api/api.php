<?php
// ============================================================
// BEAUTY PET - API ÚNICA PARA O MOBILE
// React Native -> api.php -> config.php -> MySQL/MariaDB (XAMPP)
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/config.php';

function responder(array $dados, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lerEntrada(): array
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return $_GET;
    }

    $conteudo = file_get_contents('php://input');
    $dados = json_decode($conteudo ?: '{}', true);
    return is_array($dados) ? $dados : [];
}

function texto(array $dados, string $chave): string
{
    return trim((string) ($dados[$chave] ?? ''));
}

function inteiroOuNull(mixed $valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }
    return filter_var($valor, FILTER_VALIDATE_INT) !== false ? (int) $valor : null;
}

function numeroMonetario(mixed $valor): ?float
{
    if (is_string($valor)) {
        $valor = str_replace(['R$', ' '], '', $valor);
        if (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }
    }

    return is_numeric($valor) ? (float) $valor : null;
}

function normalizarListaIds(mixed $valor): array
{
    if (!is_array($valor)) {
        return [];
    }

    $ids = [];
    foreach ($valor as $item) {
        $id = inteiroOuNull($item);
        if ($id !== null && $id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function criarSessao(PDO $pdo, int $contaId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $pdo->prepare('DELETE FROM sessoes_mobile WHERE expira_em <= NOW()')->execute();

    $stmt = $pdo->prepare(
        'INSERT INTO sessoes_mobile (conta_mobile_id, token_hash, expira_em)
         VALUES (:conta_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 DAY))'
    );
    $stmt->execute([
        ':conta_id' => $contaId,
        ':token_hash' => $hash,
    ]);

    return $token;
}

function buscarSessao(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT
            sm.id AS sessao_id,
            ccm.id AS conta_id,
            c.id AS cliente_id,
            c.nome,
            c.email,
            c.telefone_principal,
            c.cidade
         FROM sessoes_mobile sm
         JOIN cliente_contas_mobile ccm
           ON ccm.id = sm.conta_mobile_id
         JOIN clientes c
           ON c.id = ccm.cliente_id
         WHERE sm.token_hash = :token_hash
           AND sm.expira_em > NOW()
           AND ccm.ativo = TRUE
           AND c.ativo = TRUE
         LIMIT 1'
    );
    $stmt->execute([':token_hash' => $hash]);
    $sessao = $stmt->fetch();

    if ($sessao) {
        $pdo->prepare('UPDATE sessoes_mobile SET ultimo_uso_em = NOW() WHERE id = :id')
            ->execute([':id' => $sessao['sessao_id']]);
    }

    return $sessao ?: null;
}

function exigirSessao(PDO $pdo, array $dados): array
{
    $token = texto($dados, 'token');
    $sessao = buscarSessao($pdo, $token);

    if (!$sessao) {
        responder([
            'sucesso' => false,
            'mensagem' => 'Sessão inválida ou expirada. Entre novamente.',
        ], 401);
    }

    return $sessao;
}

function petPertenceAoCliente(PDO $pdo, int $petId, int $clienteId): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM pets
         WHERE id = :pet_id
           AND cliente_id = :cliente_id
           AND ativo = TRUE'
    );
    $stmt->execute([
        ':pet_id' => $petId,
        ':cliente_id' => $clienteId,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function sincronizarNotificacoesAgendamento(PDO $pdo, int $clienteId): void
{
    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            a.status,
            a.data_hora_inicio,
            p.id AS pet_id,
            p.nome AS pet,
            GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS servicos
         FROM agendamentos a
         JOIN pets p ON p.id = a.pet_id
         LEFT JOIN agendamento_itens ai ON ai.agendamento_id = a.id
         LEFT JOIN servicos s ON s.id = ai.servico_id
         WHERE p.cliente_id = :cliente_id
           AND a.origem = 'MOBILE'
           AND a.status IN ('CONFIRMADO', 'RECUSADO', 'CANCELADO')
         GROUP BY a.id, a.status, a.data_hora_inicio, p.id, p.nome"
    );
    $stmt->execute([':cliente_id' => $clienteId]);

    foreach ($stmt->fetchAll() as $agendamento) {
        $status = (string) $agendamento['status'];
        $titulo = match ($status) {
            'CONFIRMADO' => 'Agendamento confirmado',
            'RECUSADO' => 'Agendamento recusado',
            'CANCELADO' => 'Agendamento cancelado',
            default => 'Atualização do agendamento',
        };

        $dataHora = new DateTime((string) $agendamento['data_hora_inicio']);
        $servicos = $agendamento['servicos'] ?: 'Serviço';
        $mensagem = sprintf(
            '%s para %s: %s em %s às %s.',
            $titulo,
            $agendamento['pet'],
            $servicos,
            $dataHora->format('d/m/Y'),
            $dataHora->format('H:i')
        );

        $existe = $pdo->prepare(
            'SELECT COUNT(*)
             FROM notificacoes_mobile
             WHERE cliente_id = :cliente_id
               AND agendamento_id = :agendamento_id
               AND titulo = :titulo'
        );
        $existe->execute([
            ':cliente_id' => $clienteId,
            ':agendamento_id' => $agendamento['id'],
            ':titulo' => $titulo,
        ]);

        if ((int) $existe->fetchColumn() === 0) {
            $insert = $pdo->prepare(
                "INSERT INTO notificacoes_mobile
                    (cliente_id, pet_id, agendamento_id, titulo, mensagem, tipo)
                 VALUES
                    (:cliente_id, :pet_id, :agendamento_id, :titulo, :mensagem, 'AGENDAMENTO')"
            );
            $insert->execute([
                ':cliente_id' => $clienteId,
                ':pet_id' => $agendamento['pet_id'],
                ':agendamento_id' => $agendamento['id'],
                ':titulo' => $titulo,
                ':mensagem' => $mensagem,
            ]);
        }
    }
}

function servicoEhBanhoTosa(PDO $pdo, array $servicoIds): bool
{
    if (!$servicoIds) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($servicoIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT s.nome, cs.nome AS categoria
         FROM servicos s
         JOIN categorias_servico cs ON cs.id = s.categoria_id
         WHERE s.id IN ($placeholders)"
    );
    $stmt->execute($servicoIds);

    foreach ($stmt->fetchAll() as $item) {
        $texto = mb_strtolower(($item['categoria'] ?? '') . ' ' . ($item['nome'] ?? ''), 'UTF-8');
        if (str_contains($texto, 'banho') || str_contains($texto, 'tosa') || str_contains($texto, 'higien')) {
            return true;
        }
    }

    return false;
}

function horariosDisponiveis(PDO $pdo, string $data, array $servicoIds, int $duracaoMinutos = 60): array
{
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $data)) {
        return [];
    }

    if (!servicoEhBanhoTosa($pdo, $servicoIds)) {
        // Serviços que não usam a agenda-padrão de banho/tosa continuam com
        // horários livres definidos pela API. Depois vocês podem criar regras
        // próprias para cada serviço sem mexer nas telas do React Native.
        return ['09:00', '10:00', '11:00', '12:00', '14:00', '16:00'];
    }

    $diaSemana = (int) (new DateTime($data))->format('N');

    $stmt = $pdo->prepare(
        "SELECT id
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
        ':dia_semana' => $diaSemana,
        ':data_inicio' => $data,
        ':data_fim' => $data,
    ]);
    $padraoId = $stmt->fetchColumn();

    if (!$padraoId) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT TIME_FORMAT(horario, "%H:%i") AS horario, quantidade_vagas
         FROM agenda_padrao_banho_tosa_horarios
         WHERE agenda_padrao_id = :id
         ORDER BY horario'
    );
    $stmt->execute([':id' => $padraoId]);
    $slots = $stmt->fetchAll();

    if (!$slots) {
        return [];
    }

    $ocupacao = [];
    foreach ($slots as $slot) {
        $ocupacao[$slot['horario']] = 0;
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT
            a.id,
            a.data_hora_inicio,
            COALESCE(a.data_hora_fim, DATE_ADD(a.data_hora_inicio, INTERVAL 30 MINUTE)) AS data_hora_fim
         FROM agendamentos a
         JOIN agendamento_itens ai ON ai.agendamento_id = a.id
         JOIN servicos s ON s.id = ai.servico_id
         JOIN categorias_servico cs ON cs.id = s.categoria_id
         WHERE a.status NOT IN ('CANCELADO', 'RECUSADO')
           AND DATE(a.data_hora_inicio) = :data
           AND (
                LOWER(cs.nome) LIKE '%banho%'
                OR LOWER(cs.nome) LIKE '%tosa%'
                OR LOWER(s.nome) LIKE '%banho%'
                OR LOWER(s.nome) LIKE '%tosa%'
                OR LOWER(s.nome) LIKE '%higien%'
           )"
    );
    $stmt->execute([':data' => $data]);
    $agendamentos = $stmt->fetchAll();

    foreach ($slots as $slot) {
        $hora = $slot['horario'];
        $inicioSlot = new DateTime($data . ' ' . $hora . ':00');
        $fimSlot = (clone $inicioSlot)->modify('+30 minutes');

        foreach ($agendamentos as $agendamento) {
            $inicio = new DateTime($agendamento['data_hora_inicio']);
            $fim = new DateTime($agendamento['data_hora_fim']);
            if ($inicio < $fimSlot && $fim > $inicioSlot) {
                $ocupacao[$hora]++;
            }
        }
    }

    $quantidadeBlocos = max(1, (int) ceil($duracaoMinutos / 30));
    $resultado = [];

    foreach ($slots as $indice => $slot) {
        $inicio = DateTime::createFromFormat('H:i', $slot['horario']);
        if (!$inicio) {
            continue;
        }

        $valido = true;

        for ($bloco = 0; $bloco < $quantidadeBlocos; $bloco++) {
            if (!isset($slots[$indice + $bloco])) {
                $valido = false;
                break;
            }

            $esperado = (clone $inicio)->modify('+' . ($bloco * 30) . ' minutes')->format('H:i');
            $slotAtual = $slots[$indice + $bloco];
            $horaAtual = $slotAtual['horario'];
            $restantes = (int) $slotAtual['quantidade_vagas'] - (int) ($ocupacao[$horaAtual] ?? 0);

            if ($horaAtual !== $esperado || $restantes <= 0) {
                $valido = false;
                break;
            }
        }

        if ($valido) {
            $resultado[] = $slot['horario'];
        }
    }

    return $resultado;
}

$dados = lerEntrada();
$acao = texto($dados, 'acao');

try {
    switch ($acao) {
        case 'teste_conexao':
            $pdo->query('SELECT 1');
            responder([
                'sucesso' => true,
                'mensagem' => 'API e banco beauty_pet conectados com sucesso.',
            ]);

        case 'cadastro':
            $nome = texto($dados, 'nome');
            $email = mb_strtolower(texto($dados, 'email'), 'UTF-8');
            $senha = (string) ($dados['senha'] ?? '');

            if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
                responder([
                    'sucesso' => false,
                    'mensagem' => 'Informe nome, e-mail válido e uma senha com pelo menos 6 caracteres.',
                ], 422);
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM cliente_contas_mobile WHERE LOWER(email_login) = LOWER(:email)');
            $stmt->execute([':email' => $email]);
            if ((int) $stmt->fetchColumn() > 0) {
                responder([
                    'sucesso' => false,
                    'mensagem' => 'Já existe uma conta mobile cadastrada com este e-mail.',
                ], 409);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, nome
                 FROM clientes
                 WHERE LOWER(email) = LOWER(:email)
                 ORDER BY id
                 LIMIT 1'
            );
            $stmt->execute([':email' => $email]);
            $cliente = $stmt->fetch();
            $clienteJaExistia = (bool) $cliente;

            if ($cliente) {
                $clienteId = (int) $cliente['id'];
            } else {
                // telefone_principal já é obrigatório na base WEB.
                // No cadastro simplificado do mobile ele entra vazio e pode ser
                // completado depois na ficha do tutor pelo WEB.
                $stmt = $pdo->prepare(
                    'INSERT INTO clientes (nome, email, telefone_principal)
                     VALUES (:nome, :email, :telefone)'
                );
                $stmt->execute([
                    ':nome' => $nome,
                    ':email' => $email,
                    ':telefone' => '',
                ]);
                $clienteId = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM cliente_contas_mobile WHERE cliente_id = :cliente_id'
            );
            $stmt->execute([':cliente_id' => $clienteId]);
            if ((int) $stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                responder([
                    'sucesso' => false,
                    'mensagem' => 'Este tutor já possui uma conta mobile.',
                ], 409);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO cliente_contas_mobile
                    (cliente_id, email_login, senha_hash)
                 VALUES
                    (:cliente_id, :email, :senha_hash)'
            );
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':email' => $email,
                ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
            ]);

            $pdo->commit();
            responder([
                'sucesso' => true,
                'mensagem' => $clienteJaExistia
                    ? 'Conta criada e vinculada à ficha que já existia no Beauty Pet.'
                    : 'Cadastro realizado e ficha criada no sistema Beauty Pet.',
                'cliente_id' => $clienteId,
                'ficha_web_vinculada' => true,
            ], 201);

        case 'login':
            $email = mb_strtolower(texto($dados, 'email'), 'UTF-8');
            $senha = (string) ($dados['senha'] ?? '');

            $stmt = $pdo->prepare(
                'SELECT
                    ccm.id AS conta_id,
                    ccm.senha_hash,
                    c.id AS cliente_id,
                    c.nome,
                    c.email
                 FROM cliente_contas_mobile ccm
                 JOIN clientes c ON c.id = ccm.cliente_id
                 WHERE LOWER(ccm.email_login) = LOWER(:email)
                   AND ccm.ativo = TRUE
                   AND c.ativo = TRUE
                 LIMIT 1'
            );
            $stmt->execute([':email' => $email]);
            $conta = $stmt->fetch();

            if (!$conta || !password_verify($senha, $conta['senha_hash'])) {
                responder([
                    'sucesso' => false,
                    'mensagem' => 'E-mail ou senha inválidos.',
                ], 401);
            }

            $token = criarSessao($pdo, (int) $conta['conta_id']);
            $pdo->prepare('UPDATE cliente_contas_mobile SET ultimo_acesso_em = NOW() WHERE id = :id')
                ->execute([':id' => $conta['conta_id']]);

            responder([
                'sucesso' => true,
                'token' => $token,
                'cliente' => [
                    'id' => (int) $conta['cliente_id'],
                    'nome' => $conta['nome'],
                    'email' => $conta['email'],
                ],
            ]);

        case 'logout':
            $token = texto($dados, 'token');
            if ($token !== '') {
                $pdo->prepare('DELETE FROM sessoes_mobile WHERE token_hash = :hash')
                    ->execute([':hash' => hash('sha256', $token)]);
            }
            responder(['sucesso' => true]);

        case 'home':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            sincronizarNotificacoesAgendamento($pdo, $clienteId);

            $stmt = $pdo->prepare(
                "SELECT
                    a.id,
                    a.data_hora_inicio,
                    a.status,
                    p.nome AS pet,
                    GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS servicos
                 FROM agendamentos a
                 JOIN pets p ON p.id = a.pet_id
                 LEFT JOIN agendamento_itens ai ON ai.agendamento_id = a.id
                 LEFT JOIN servicos s ON s.id = ai.servico_id
                 WHERE p.cliente_id = :cliente_id
                   AND a.data_hora_inicio >= NOW()
                   AND a.status IN ('SOLICITADO', 'CONFIRMADO')
                 GROUP BY a.id, a.data_hora_inicio, a.status, p.nome
                 ORDER BY a.data_hora_inicio
                 LIMIT 1"
            );
            $stmt->execute([':cliente_id' => $clienteId]);
            $proximo = $stmt->fetch() ?: null;

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM notificacoes_mobile WHERE cliente_id = :cliente_id AND lida = FALSE'
            );
            $stmt->execute([':cliente_id' => $clienteId]);

            responder([
                'sucesso' => true,
                'cliente' => [
                    'id' => $clienteId,
                    'nome' => $sessao['nome'],
                ],
                'proximo_agendamento' => $proximo,
                'notificacoes_nao_lidas' => (int) $stmt->fetchColumn(),
            ]);

        case 'perfil':
        case 'meus_pets':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];

            $stmt = $pdo->prepare(
                'SELECT
                    id, nome, cpf, email, telefone_principal, telefone_reserva,
                    cep, logradouro, numero, complemento, bairro, cidade, estado,
                    referencia, observacoes
                 FROM clientes
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute([':id' => $clienteId]);
            $cliente = $stmt->fetch();

            $stmt = $pdo->prepare(
                'SELECT
                    p.id,
                    p.nome,
                    p.foto_url,
                    p.sexo,
                    p.cor,
                    p.peso,
                    p.porte,
                    p.data_nascimento,
                    p.castrado,
                    p.vacinacao_em_dia,
                    e.nome AS especie,
                    r.nome AS raca
                 FROM pets p
                 JOIN especies e ON e.id = p.especie_id
                 LEFT JOIN racas r ON r.id = p.raca_id
                 WHERE p.cliente_id = :cliente_id
                   AND p.ativo = TRUE
                 ORDER BY p.nome'
            );
            $stmt->execute([':cliente_id' => $clienteId]);
            $pets = $stmt->fetchAll();

            responder([
                'sucesso' => true,
                'cliente' => $cliente,
                'pets' => $pets,
            ]);

        case 'catalogo_pet':
            exigirSessao($pdo, $dados);

            $especies = $pdo->query(
                'SELECT id, nome
                 FROM especies
                 WHERE ativo = TRUE
                 ORDER BY nome'
            )->fetchAll();

            $racas = $pdo->query(
                'SELECT id, especie_id, nome
                 FROM racas
                 WHERE ativo = TRUE
                 ORDER BY nome'
            )->fetchAll();

            responder([
                'sucesso' => true,
                'especies' => $especies,
                'racas' => $racas,
            ]);

        case 'adicionar_pet':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            $nomePet = texto($dados, 'nome');
            $especieId = inteiroOuNull($dados['especie_id'] ?? null);
            $racaId = inteiroOuNull($dados['raca_id'] ?? null);
            $sexo = strtoupper(texto($dados, 'sexo')) ?: 'NAO_INFORMADO';
            $cor = texto($dados, 'cor');
            $dataNascimento = texto($dados, 'data_nascimento');
            $castrado = $dados['castrado'] ?? null;

            if ($nomePet === '') {
                responder([
                    'sucesso' => false,
                    'mensagem' => 'Informe pelo menos o nome do pet.',
                ], 422);
            }

            if ($especieId === null) {
                $stmt = $pdo->query(
                    "SELECT id
                     FROM especies
                     WHERE ativo = TRUE
                     ORDER BY CASE WHEN LOWER(nome) IN ('cão', 'cao') THEN 0 ELSE 1 END, id
                     LIMIT 1"
                );
                $especieId = inteiroOuNull($stmt->fetchColumn());
            }

            if ($especieId === null) {
                responder([
                    'sucesso' => false,
                    'mensagem' => 'Cadastre ao menos uma espécie no sistema web antes de adicionar o pet.',
                ], 422);
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM especies WHERE id = :id AND ativo = TRUE');
            $stmt->execute([':id' => $especieId]);
            if ((int) $stmt->fetchColumn() === 0) {
                responder(['sucesso' => false, 'mensagem' => 'Espécie inválida.'], 422);
            }

            if ($racaId !== null) {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM racas
                     WHERE id = :raca_id
                       AND especie_id = :especie_id
                       AND ativo = TRUE'
                );
                $stmt->execute([
                    ':raca_id' => $racaId,
                    ':especie_id' => $especieId,
                ]);
                if ((int) $stmt->fetchColumn() === 0) {
                    responder(['sucesso' => false, 'mensagem' => 'A raça não pertence à espécie selecionada.'], 422);
                }
            }

            if (!in_array($sexo, ['MACHO', 'FEMEA', 'NAO_INFORMADO'], true)) {
                $sexo = 'NAO_INFORMADO';
            }

            if ($dataNascimento !== '') {
                $dataValida = DateTime::createFromFormat('Y-m-d', $dataNascimento);
                if (!$dataValida || $dataValida->format('Y-m-d') !== $dataNascimento) {
                    responder(['sucesso' => false, 'mensagem' => 'Data de nascimento inválida.'], 422);
                }
            }

            if ($castrado !== null) {
                $castrado = filter_var($castrado, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
            $castradoBanco = $castrado === null ? null : ($castrado ? 1 : 0);

            $stmt = $pdo->prepare(
                'INSERT INTO pets
                    (cliente_id, especie_id, raca_id, nome, sexo, cor, data_nascimento, castrado)
                 VALUES
                    (:cliente_id, :especie_id, :raca_id, :nome, :sexo, :cor, :data_nascimento, :castrado)'
            );
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':especie_id' => $especieId,
                ':raca_id' => $racaId,
                ':nome' => $nomePet,
                ':sexo' => $sexo,
                ':cor' => $cor !== '' ? $cor : null,
                ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
                ':castrado' => $castradoBanco,
            ]);

            responder([
                'sucesso' => true,
                'mensagem' => 'Pet cadastrado e vinculado à ficha do tutor.',
                'pet_id' => (int) $pdo->lastInsertId(),
            ], 201);

        case 'servicos':
            exigirSessao($pdo, $dados);
            $stmt = $pdo->query(
                "SELECT
                    s.id,
                    s.nome,
                    s.descricao,
                    cs.nome AS categoria
                 FROM servicos s
                 JOIN categorias_servico cs ON cs.id = s.categoria_id
                 WHERE s.ativo = TRUE
                   AND cs.ativo = TRUE
                   AND LOWER(cs.nome) <> 'loja'
                 ORDER BY cs.nome, s.nome"
            );
            responder([
                'sucesso' => true,
                'servicos' => $stmt->fetchAll(),
            ]);

        case 'horarios_disponiveis':
            exigirSessao($pdo, $dados);
            $data = texto($dados, 'data');
            $servicoIds = normalizarListaIds($dados['servico_ids'] ?? []);
            $duracao = inteiroOuNull($dados['duracao_minutos'] ?? null) ?? 60;

            responder([
                'sucesso' => true,
                'horarios' => horariosDisponiveis($pdo, $data, $servicoIds, $duracao),
            ]);

        case 'solicitar_agendamento':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            $petId = inteiroOuNull($dados['pet_id'] ?? null);
            $servicoIds = normalizarListaIds($dados['servico_ids'] ?? []);
            $data = texto($dados, 'data');
            $horario = texto($dados, 'horario');
            $observacoes = texto($dados, 'observacoes');
            $duracao = inteiroOuNull($dados['duracao_minutos'] ?? null) ?? 60;

            if (!$petId || !petPertenceAoCliente($pdo, $petId, $clienteId)) {
                responder(['sucesso' => false, 'mensagem' => 'Selecione um pet válido.'], 422);
            }
            if (!$servicoIds) {
                responder(['sucesso' => false, 'mensagem' => 'Selecione pelo menos um serviço.'], 422);
            }
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $data) || !preg_match('/^\\d{2}:\\d{2}$/', $horario)) {
                responder(['sucesso' => false, 'mensagem' => 'Data ou horário inválido.'], 422);
            }

            $placeholders = implode(',', array_fill(0, count($servicoIds), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE id IN ($placeholders) AND ativo = TRUE");
            $stmt->execute($servicoIds);
            if ((int) $stmt->fetchColumn() !== count($servicoIds)) {
                responder(['sucesso' => false, 'mensagem' => 'Existe serviço inválido ou inativo.'], 422);
            }

            $disponiveis = horariosDisponiveis($pdo, $data, $servicoIds, $duracao);
            if (!in_array($horario, $disponiveis, true)) {
                responder(['sucesso' => false, 'mensagem' => 'Esse horário não está mais disponível. Atualize a tela e escolha outro.'], 409);
            }

            $inicio = new DateTime($data . ' ' . $horario . ':00');
            $fim = (clone $inicio)->modify('+' . $duracao . ' minutes');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO agendamentos
                    (pet_id, data_hora_inicio, data_hora_fim, origem, status, observacoes_cliente)
                 VALUES
                    (:pet_id, :inicio, :fim, 'MOBILE', 'SOLICITADO', :observacoes)"
            );
            $stmt->execute([
                ':pet_id' => $petId,
                ':inicio' => $inicio->format('Y-m-d H:i:s'),
                ':fim' => $fim->format('Y-m-d H:i:s'),
                ':observacoes' => $observacoes !== '' ? $observacoes : null,
            ]);
            $agendamentoId = (int) $pdo->lastInsertId();

            $stmtItem = $pdo->prepare(
                'INSERT INTO agendamento_itens (agendamento_id, servico_id)
                 VALUES (:agendamento_id, :servico_id)'
            );
            foreach ($servicoIds as $servicoId) {
                $stmtItem->execute([
                    ':agendamento_id' => $agendamentoId,
                    ':servico_id' => $servicoId,
                ]);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO notificacoes_mobile
                    (cliente_id, pet_id, agendamento_id, titulo, mensagem, tipo)
                 VALUES
                    (:cliente_id, :pet_id, :agendamento_id, 'Solicitação enviada', :mensagem, 'AGENDAMENTO')"
            );
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':pet_id' => $petId,
                ':agendamento_id' => $agendamentoId,
                ':mensagem' => 'Sua solicitação foi enviada ao Beauty Pet e aguarda confirmação.',
            ]);

            $pdo->commit();

            responder([
                'sucesso' => true,
                'mensagem' => 'Solicitação de agendamento enviada ao Beauty Pet.',
                'agendamento_id' => $agendamentoId,
            ], 201);

        case 'agenda_pessoal':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            $stmt = $pdo->prepare(
                'SELECT
                    e.id,
                    e.titulo,
                    e.descricao,
                    e.data_hora_inicio,
                    e.data_hora_fim,
                    e.lembrete_em,
                    e.status,
                    e.pet_id,
                    p.nome AS pet,
                    ce.nome AS categoria
                 FROM eventos_agenda_mobile e
                 LEFT JOIN pets p ON p.id = e.pet_id
                 LEFT JOIN categorias_evento_mobile ce ON ce.id = e.categoria_id
                 WHERE e.cliente_id = :cliente_id
                   AND e.status <> \'CANCELADO\'
                 ORDER BY e.data_hora_inicio'
            );
            $stmt->execute([':cliente_id' => $clienteId]);
            responder([
                'sucesso' => true,
                'eventos' => $stmt->fetchAll(),
            ]);

        case 'criar_evento':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            $titulo = texto($dados, 'titulo');
            $descricao = texto($dados, 'descricao');
            $data = texto($dados, 'data');
            $horario = texto($dados, 'horario');
            $petId = inteiroOuNull($dados['pet_id'] ?? null);
            $categoriaId = inteiroOuNull($dados['categoria_id'] ?? null);
            if ($titulo === '' || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $data) || !preg_match('/^\\d{2}:\\d{2}$/', $horario)) {
                responder(['sucesso' => false, 'mensagem' => 'Informe o evento, a data e o horário.'], 422);
            }
            if ($petId !== null && !petPertenceAoCliente($pdo, $petId, $clienteId)) {
                responder(['sucesso' => false, 'mensagem' => 'O pet selecionado não pertence a este tutor.'], 422);
            }

            if ($categoriaId === null) {
                $categoriaId = (int) $pdo->query("SELECT id FROM categorias_evento_mobile WHERE nome = 'Outros' LIMIT 1")->fetchColumn();
            }

            $stmt = $pdo->prepare(
                'INSERT INTO eventos_agenda_mobile
                    (cliente_id, pet_id, categoria_id, titulo, descricao, data_hora_inicio)
                 VALUES
                    (:cliente_id, :pet_id, :categoria_id, :titulo, :descricao, :inicio)'
            );
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':pet_id' => $petId,
                ':categoria_id' => $categoriaId ?: null,
                ':titulo' => $titulo,
                ':descricao' => $descricao !== '' ? $descricao : null,
                ':inicio' => $data . ' ' . $horario . ':00',
            ]);

            responder([
                'sucesso' => true,
                'mensagem' => 'Evento adicionado à sua agenda.',
                'evento_id' => (int) $pdo->lastInsertId(),
            ], 201);

        case 'excluir_evento':
            $sessao = exigirSessao($pdo, $dados);
            $eventoId = inteiroOuNull($dados['evento_id'] ?? null);
            if (!$eventoId) {
                responder(['sucesso' => false, 'mensagem' => 'Evento inválido.'], 422);
            }
            $stmt = $pdo->prepare(
                "UPDATE eventos_agenda_mobile
                 SET status = 'CANCELADO'
                 WHERE id = :id AND cliente_id = :cliente_id"
            );
            $stmt->execute([
                ':id' => $eventoId,
                ':cliente_id' => $sessao['cliente_id'],
            ]);
            responder(['sucesso' => true]);

        case 'categorias_gasto':
            exigirSessao($pdo, $dados);
            $stmt = $pdo->query('SELECT id, nome FROM categorias_gasto_mobile WHERE ativo = TRUE ORDER BY nome');
            responder(['sucesso' => true, 'categorias' => $stmt->fetchAll()]);

        case 'adicionar_gasto':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            $petId = inteiroOuNull($dados['pet_id'] ?? null);
            $categoriaId = inteiroOuNull($dados['categoria_id'] ?? null);
            $categoriaNomeRecebida = texto($dados, 'categoria_nome');
            $descricao = texto($dados, 'descricao');
            $valor = numeroMonetario($dados['valor'] ?? null);
            $dataGasto = texto($dados, 'data_gasto');

            if ($petId !== null && !petPertenceAoCliente($pdo, $petId, $clienteId)) {
                responder(['sucesso' => false, 'mensagem' => 'O pet selecionado não pertence a este tutor.'], 422);
            }
            if (!$categoriaId || $descricao === '' || $valor === null || $valor <= 0 || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dataGasto)) {
                responder(['sucesso' => false, 'mensagem' => 'Preencha categoria, descrição, valor e data corretamente.'], 422);
            }

            $dataValida = DateTime::createFromFormat('Y-m-d', $dataGasto);
            if (!$dataValida || $dataValida->format('Y-m-d') !== $dataGasto) {
                responder(['sucesso' => false, 'mensagem' => 'A data do gasto é inválida.'], 422);
            }

            $stmt = $pdo->prepare(
                'SELECT id, nome
                 FROM categorias_gasto_mobile
                 WHERE id = :id AND ativo = TRUE
                 LIMIT 1'
            );
            $stmt->execute([':id' => $categoriaId]);
            $categoriaSelecionada = $stmt->fetch();
            if (!$categoriaSelecionada) {
                responder(['sucesso' => false, 'mensagem' => 'A categoria selecionada é inválida.'], 422);
            }
            if (
                $categoriaNomeRecebida !== '' &&
                mb_strtolower($categoriaNomeRecebida, 'UTF-8') !==
                    mb_strtolower((string) $categoriaSelecionada['nome'], 'UTF-8')
            ) {
                responder([
                    'sucesso' => false,
                    'mensagem' => 'A categoria selecionada não corresponde ao cadastro do banco. Atualize a tela e tente novamente.',
                ], 409);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO gastos_pessoais_mobile
                    (cliente_id, pet_id, categoria_id, descricao, valor, data_gasto)
                 VALUES
                    (:cliente_id, :pet_id, :categoria_id, :descricao, :valor, :data_gasto)'
            );
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':pet_id' => $petId,
                ':categoria_id' => $categoriaId,
                ':descricao' => $descricao,
                ':valor' => $valor,
                ':data_gasto' => $dataGasto,
            ]);

            responder([
                'sucesso' => true,
                'mensagem' => 'Gasto adicionado.',
                'gasto_id' => (int) $pdo->lastInsertId(),
            ], 201);

        case 'resumo_financeiro':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            $ano = inteiroOuNull($dados['ano'] ?? null) ?? (int) date('Y');
            $mes = inteiroOuNull($dados['mes'] ?? null) ?? (int) date('n');

            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(valor), 0) AS total
                 FROM gastos_pessoais_mobile
                 WHERE cliente_id = ? AND YEAR(data_gasto) = ? AND MONTH(data_gasto) = ?'
            );
            $stmt->execute([$clienteId, $ano, $mes]);
            $totalPessoal = (float) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'SELECT
                    cg.id,
                    cg.nome,
                    COALESCE(SUM(g.valor), 0) AS total
                 FROM categorias_gasto_mobile cg
                 LEFT JOIN gastos_pessoais_mobile g
                   ON g.categoria_id = cg.id
                  AND g.cliente_id = ?
                  AND YEAR(g.data_gasto) = ?
                  AND MONTH(g.data_gasto) = ?
                 WHERE cg.ativo = TRUE
                 GROUP BY cg.id, cg.nome
                 ORDER BY cg.nome'
            );
            $stmt->execute([$clienteId, $ano, $mes]);
            $categorias = $stmt->fetchAll();
            foreach ($categorias as &$categoria) {
                $categoria['total'] = (float) $categoria['total'];
            }
            unset($categoria);

            $stmt = $pdo->prepare(
                'SELECT
                    COALESCE(SUM(valor_total), 0) AS total,
                    COUNT(*) AS quantidade
                 FROM vw_mobile_budget
                 WHERE cliente_id = ? AND ano = ? AND mes = ?'
            );
            $stmt->execute([$clienteId, $ano, $mes]);
            $budget = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT nome FROM pets WHERE cliente_id = :cliente_id AND ativo = TRUE ORDER BY id LIMIT 1');
            $stmt->execute([':cliente_id' => $clienteId]);
            $primeiroPet = $stmt->fetchColumn() ?: null;

            responder([
                'sucesso' => true,
                'ano' => $ano,
                'mes' => $mes,
                'total_pessoal' => $totalPessoal,
                'categorias' => $categorias,
                'budget' => [
                    'total' => (float) ($budget['total'] ?? 0),
                    'quantidade' => (int) ($budget['quantidade'] ?? 0),
                ],
                'total_geral' => $totalPessoal + (float) ($budget['total'] ?? 0),
                'primeiro_pet' => $primeiroPet,
            ]);

        case 'budget':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            $ano = inteiroOuNull($dados['ano'] ?? null) ?? (int) date('Y');
            $mes = inteiroOuNull($dados['mes'] ?? null) ?? (int) date('n');

            $stmt = $pdo->prepare(
                'SELECT
                    data_fechamento,
                    pet_id,
                    pet,
                    servico,
                    descricao,
                    valor_servico,
                    total_adicionais,
                    valor_total
                 FROM vw_mobile_budget
                 WHERE cliente_id = :cliente_id
                   AND ano = :ano
                   AND mes = :mes
                 ORDER BY data_fechamento DESC, pet, servico'
            );
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':ano' => $ano,
                ':mes' => $mes,
            ]);
            $itens = $stmt->fetchAll();
            foreach ($itens as &$item) {
                $item['valor_servico'] = (float) $item['valor_servico'];
                $item['total_adicionais'] = (float) $item['total_adicionais'];
                $item['valor_total'] = (float) $item['valor_total'];
            }
            unset($item);

            responder(['sucesso' => true, 'itens' => $itens]);

        case 'notificacoes':
            $sessao = exigirSessao($pdo, $dados);
            $clienteId = (int) $sessao['cliente_id'];
            sincronizarNotificacoesAgendamento($pdo, $clienteId);

            $stmt = $pdo->prepare(
                'SELECT id, titulo, mensagem, tipo, lida, criado_em
                 FROM notificacoes_mobile
                 WHERE cliente_id = :cliente_id
                 ORDER BY criado_em DESC, id DESC'
            );
            $stmt->execute([':cliente_id' => $clienteId]);
            $itens = $stmt->fetchAll();

            foreach ($itens as &$item) {
                $item['id'] = (int) $item['id'];
                $item['lida'] = (bool) $item['lida'];
            }
            unset($item);

            responder(['sucesso' => true, 'notificacoes' => $itens]);

        case 'marcar_notificacao_lida':
            $sessao = exigirSessao($pdo, $dados);
            $id = inteiroOuNull($dados['notificacao_id'] ?? null);
            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE notificacoes_mobile
                     SET lida = TRUE, lida_em = NOW()
                     WHERE id = :id AND cliente_id = :cliente_id'
                );
                $stmt->execute([
                    ':id' => $id,
                    ':cliente_id' => $sessao['cliente_id'],
                ]);
            }
            responder(['sucesso' => true]);

        case 'limpar_notificacoes':
            $sessao = exigirSessao($pdo, $dados);
            $stmt = $pdo->prepare('DELETE FROM notificacoes_mobile WHERE cliente_id = :cliente_id');
            $stmt->execute([':cliente_id' => $sessao['cliente_id']]);
            responder(['sucesso' => true]);

        default:
            responder([
                'sucesso' => false,
                'mensagem' => 'Ação inválida ou não informada.',
            ], 404);
    }
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responder([
        'sucesso' => false,
        'mensagem' => 'Erro interno na API.',
        'detalhe' => $erro->getMessage(),
    ], 500);
}
