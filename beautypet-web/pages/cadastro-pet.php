<?php // Executa esta instrução como parte da regra de negócio desta página.
/**
 * ARQUIVO: cadastro-pet.php
 * RESPONSABILIDADE: Cadastro e edição de pets.
 * Valida os dados do pet, vincula o animal ao tutor, processa a foto enviada e grava ou atualiza o cadastro no banco.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php'; // Carrega a configuração ou função compartilhada necessária nesta página.

// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = ''; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = ''; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
// Lê o identificador enviado pela URL para saber se a página está em modo de edição.
$editarId = inteiroOuNull($_GET['editar'] ?? $_POST['pet_id'] ?? null); // Armazena em editarId o valor usado nas próximas etapas deste fluxo.
// Indica se o formulário está alterando um registro existente em vez de criar um novo.
$modoEdicao = $editarId !== null; // Armazena em modoEdicao o valor usado nas próximas etapas deste fluxo.
// Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
$dados = $_POST; // Armazena em dados o valor usado nas próximas etapas deste fluxo.
// Guarda o caminho final da foto que será persistido no cadastro do pet.
$fotoSalva = null; // Armazena em fotoSalva o valor usado nas próximas etapas deste fluxo.
// Guarda o caminho da foto já cadastrada para mantê-la quando nenhuma nova imagem for enviada.
$fotoAtual = null; // Armazena em fotoAtual o valor usado nas próximas etapas deste fluxo.

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($modoEdicao && $_SERVER['REQUEST_METHOD'] === 'GET') { // Testa esta condição antes de executar o bloco correspondente.
    // Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare( // Prepara a consulta SQL com parâmetros separados para segurança.
        'SELECT p.*, r.nome AS raca -- Lê dados e inicia esta consulta.
         FROM pets p -- Define a origem principal dos dados desta consulta.
         LEFT JOIN racas r ON r.id = p.raca_id -- Relaciona outra tabela ou view a esta consulta.
         WHERE p.id = :id -- Filtra os registros conforme esta condição.
           AND p.ativo = TRUE -- Define a coluna ou expressão AND desta estrutura.
         LIMIT 1' // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $editarId]); // Executa a consulta preparada enviando os valores necessários.
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $dados = $stmt->fetch() ?: []; // Obtém uma linha retornada pelo banco.

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$dados) { // Testa esta condição antes de executar o bloco correspondente.
        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header('Location: fichas-pets.php'); // Envia um cabeçalho HTTP, geralmente para redirecionar o navegador.
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit; // Encerra a execução desta requisição.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    // Guarda o caminho da foto já cadastrada para mantê-la quando nenhuma nova imagem for enviada.
    $fotoAtual = urlFotoPet($dados['foto_url'] ?? null); // Armazena em fotoAtual o valor usado nas próximas etapas deste fluxo.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Testa esta condição antes de executar o bloco correspondente.
    // Guarda o nome informado no formulário após a normalização necessária.
    $nome = textoOuNull($_POST['nome'] ?? null); // Armazena em nome o valor usado nas próximas etapas deste fluxo.
    // Guarda o ID do tutor responsável vinculado ao pet ou à movimentação financeira.
    $clienteId = inteiroOuNull($_POST['cliente_id'] ?? null); // Armazena em clienteId o valor usado nas próximas etapas deste fluxo.
    // Guarda o ID da espécie selecionada para relacionar o pet ao cadastro de espécies.
    $especieId = inteiroOuNull($_POST['especie_id'] ?? null); // Armazena em especieId o valor usado nas próximas etapas deste fluxo.
    // Armazena em "raca" o valor necessário para as próximas etapas deste fluxo.
    $raca = textoOuNull($_POST['raca'] ?? null); // Armazena em raca o valor usado nas próximas etapas deste fluxo.
    // Armazena em "sexo" o valor necessário para as próximas etapas deste fluxo.
    $sexo = $_POST['sexo'] ?? 'NAO_INFORMADO'; // Armazena em sexo o valor usado nas próximas etapas deste fluxo.
    // Armazena em "peso" o valor necessário para as próximas etapas deste fluxo.
    $peso = textoOuNull($_POST['peso'] ?? null); // Armazena em peso o valor usado nas próximas etapas deste fluxo.
    // Armazena em "porte" o valor necessário para as próximas etapas deste fluxo.
    $porte = textoOuNull($_POST['porte'] ?? null); // Armazena em porte o valor usado nas próximas etapas deste fluxo.
    // Armazena em "castrado" o valor necessário para as próximas etapas deste fluxo.
    $castrado = inteiroOuNull($_POST['castrado'] ?? null); // Armazena em castrado o valor usado nas próximas etapas deste fluxo.
    // Guarda o caminho da foto já cadastrada para mantê-la quando nenhuma nova imagem for enviada.
    $fotoAtual = textoOuNull($_POST['foto_atual'] ?? null); // Armazena em fotoAtual o valor usado nas próximas etapas deste fluxo.

    // Verifica a condição abaixo antes de executar este bloco.
    if ($nome === null || $clienteId === null || $especieId === null) { // Testa esta condição antes de executar o bloco correspondente.
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Preencha o nome, a espécie e o tutor responsável.'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (!in_array($sexo, ['MACHO', 'FEMEA', 'NAO_INFORMADO'], true)) { // Executa esta instrução como parte da regra de negócio desta página.
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Selecione um sexo válido.'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($porte !== null && !in_array($porte, ['PEQUENO', 'MEDIO', 'GRANDE'], true)) { // Executa esta instrução como parte da regra de negócio desta página.
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Selecione um porte válido.'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($peso !== null && (!is_numeric($peso) || (float) $peso < 0)) { // Executa esta instrução como parte da regra de negócio desta página.
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Informe um peso válido.'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
    } else { // Executa esta instrução como parte da regra de negócio desta página.
        // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
        try { // Executa esta instrução como parte da regra de negócio desta página.
            // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
            $pdo->beginTransaction(); // Inicia uma transação para manter várias alterações atômicas.

            // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id = ? AND ativo = TRUE'); // Prepara a consulta SQL com parâmetros separados para segurança.
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([$clienteId]); // Executa a consulta preparada enviando os valores necessários.
            // Verifica a condição abaixo antes de executar este bloco.
            if (!$stmt->fetch()) { // Testa esta condição antes de executar o bloco correspondente.
                // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                throw new RuntimeException('O tutor selecionado não foi encontrado.'); // Lança uma exceção para interromper a operação com uma mensagem controlada.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Prepara uma consulta em "especies" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare('SELECT id FROM especies WHERE id = ? AND ativo = TRUE'); // Prepara a consulta SQL com parâmetros separados para segurança.
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([$especieId]); // Executa a consulta preparada enviando os valores necessários.
            // Verifica a condição abaixo antes de executar este bloco.
            if (!$stmt->fetch()) { // Testa esta condição antes de executar o bloco correspondente.
                // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                throw new RuntimeException('A espécie selecionada não foi encontrada.'); // Lança uma exceção para interromper a operação com uma mensagem controlada.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Guarda o identificador da raça quando o projeto utiliza uma tabela de raças relacionada.
            $racaId = null; // Armazena em racaId o valor usado nas próximas etapas deste fluxo.
            // Verifica a condição abaixo antes de executar este bloco.
            if ($raca !== null) { // Testa esta condição antes de executar o bloco correspondente.
                // Prepara a inclusão de um novo registro em "racas" usando os valores já validados.
                $stmt = $pdo->prepare( // Prepara a consulta SQL com parâmetros separados para segurança.
                    'INSERT INTO racas (especie_id, nome) -- Insere os registros definidos nesta tabela.
                     VALUES (:especie_id, :nome) -- Define a coluna ou expressão VALUES desta estrutura.
                     ON CONFLICT (especie_id, nome) -- Define como o PostgreSQL reage quando o registro já existe.
                     DO UPDATE SET ativo = TRUE -- Define a coluna ou expressão DO desta estrutura.
                     RETURNING id' // Executa esta instrução como parte da regra de negócio desta página.
                ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute([':especie_id' => $especieId, ':nome' => $raca]); // Executa a consulta preparada enviando os valores necessários.
                // O PostgreSQL devolve o ID tanto quando inseriu quanto quando atualizou a raça existente.
                $racaId = (int) $stmt->fetchColumn(); // Obtém a primeira coluna do resultado, normalmente um ID, total ou contagem.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Guarda o caminho final da foto que será persistido no cadastro do pet.
            $fotoSalva = salvarFotoPet($_FILES['foto'] ?? []); // Armazena em fotoSalva o valor usado nas próximas etapas deste fluxo.
            // Armazena em "fotoFinal" o valor necessário para as próximas etapas deste fluxo.
            $fotoFinal = $fotoSalva ?? $fotoAtual; // Armazena em fotoFinal o valor usado nas próximas etapas deste fluxo.

            // Tratamento correto do booleano castrado
// Tratamento dos campos booleanos para o PostgreSQL
$castradoInput = $_POST['castrado'] ?? '';
$castradoVal = ($castradoInput === '1') ? 'true' : (($castradoInput === '0') ? 'false' : null);

// Agrupa os parâmetros enviados à consulta preparada
$parametros = [
    ':cliente_id'                  => $clienteId,
    ':especie_id'                  => $especieId,
    ':raca_id'                     => $racaId,
    ':nome'                        => $nome,
    ':foto_url'                    => $fotoFinal,
    ':sexo'                        => $sexo,
    ':cor'                         => textoOuNull($_POST['cor'] ?? null),
    ':peso'                        => $peso !== null ? (float) $peso : null,
    ':porte'                       => $porte,
    ':data_nascimento'             => textoOuNull($_POST['data_nascimento'] ?? null),
    ':nascimento_aproximado'       => isset($_POST['nascimento_aproximado']) ? 'true' : 'false',
    ':castrado'                    => $castradoVal,
    ':vacinacao_em_dia'            => isset($_POST['vacinacao_em_dia']) ? 'true' : 'false',
    ':medicamentos'                => textoOuNull($_POST['medicamentos'] ?? null),
    ':observacoes_comportamentais' => textoOuNull($_POST['observacoes_comportamentais'] ?? null),
    ':observacoes_medicas'         => textoOuNull($_POST['observacoes_medicas'] ?? null),
];

            // Verifica a condição abaixo antes de executar este bloco.
            if ($modoEdicao) { // Testa esta condição antes de executar o bloco correspondente.
                // Prepara a inclusão de um novo registro em "pets" usando os valores já validados.
                $sql = 'UPDATE pets SET -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    cliente_id = :cliente_id, -- Define a coluna ou expressão cliente_id desta estrutura.
                    especie_id = :especie_id, -- Define a coluna ou expressão especie_id desta estrutura.
                    raca_id = :raca_id, -- Define a coluna ou expressão raca_id desta estrutura.
                    nome = :nome, -- Define a coluna ou expressão nome desta estrutura.
                    foto_url = :foto_url, -- Define a coluna ou expressão foto_url desta estrutura.
                    sexo = :sexo, -- Define a coluna ou expressão sexo desta estrutura.
                    cor = :cor, -- Define a coluna ou expressão cor desta estrutura.
                    peso = :peso, -- Define a coluna ou expressão peso desta estrutura.
                    porte = :porte, -- Define a coluna ou expressão porte desta estrutura.
                    data_nascimento = :data_nascimento, -- Define a coluna ou expressão data_nascimento desta estrutura.
                    nascimento_aproximado = :nascimento_aproximado, -- Define a coluna ou expressão nascimento_aproximado desta estrutura.
                    castrado = :castrado, -- Define a coluna ou expressão castrado desta estrutura.
                    vacinacao_em_dia = :vacinacao_em_dia, -- Define a coluna ou expressão vacinacao_em_dia desta estrutura.
                    medicamentos = :medicamentos, -- Define a coluna ou expressão medicamentos desta estrutura.
                    observacoes_comportamentais = :observacoes_comportamentais, -- Define a coluna ou expressão observacoes_comportamentais desta estrutura.
                    observacoes_medicas = :observacoes_medicas -- Define a coluna ou expressão observacoes_medicas desta estrutura.
                    WHERE id = :id'; // Executa esta instrução como parte da regra de negócio desta página.
                $parametros[':id'] = $editarId; // Executa esta instrução como parte da regra de negócio desta página.
                // Prepara a inclusão de um novo registro em "pets" usando os valores já validados.
                $stmt = $pdo->prepare($sql); // Prepara a consulta SQL com parâmetros separados para segurança.
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($parametros); // Executa a consulta preparada enviando os valores necessários.
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else { // Executa esta instrução como parte da regra de negócio desta página.
                // Prepara a inclusão de um novo registro em "pets" usando os valores já validados.
                $sql = 'INSERT INTO pets ( -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    cliente_id, especie_id, raca_id, nome, foto_url, sexo, cor, peso, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    porte, data_nascimento, nascimento_aproximado, castrado, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    vacinacao_em_dia, medicamentos, observacoes_comportamentais, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    observacoes_medicas -- Continua a instrução SQL iniciada nas linhas relacionadas.
                ) VALUES ( -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    :cliente_id, :especie_id, :raca_id, :nome, :foto_url, :sexo, :cor, :peso, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    :porte, :data_nascimento, :nascimento_aproximado, :castrado, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    :vacinacao_em_dia, :medicamentos, :observacoes_comportamentais, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    :observacoes_medicas -- Continua a instrução SQL iniciada nas linhas relacionadas.
                )'; // Executa esta instrução como parte da regra de negócio desta página.
                // Prepara a consulta SQL que será executada com parâmetros separados para manter segurança e clareza.
                $stmt = $pdo->prepare($sql); // Prepara a consulta SQL com parâmetros separados para segurança.
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($parametros); // Executa a consulta preparada enviando os valores necessários.
                // Lê o identificador enviado pela URL para saber se a página está em modo de edição.
                $editarId = ultimoIdInserido($pdo); // Armazena em editarId o valor usado nas próximas etapas deste fluxo.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
            $pdo->commit(); // Confirma as alterações feitas na transação.

            // Verifica a condição abaixo antes de executar este bloco.
            if ($fotoSalva !== null && $fotoAtual !== null && $fotoAtual !== $fotoSalva) { // Testa esta condição antes de executar o bloco correspondente.
                // Armazena em "arquivoAntigo" o valor necessário para as próximas etapas deste fluxo.
                $arquivoAntigo = __DIR__ . '/' . ltrim($fotoAtual, '/'); // Armazena em arquivoAntigo o valor usado nas próximas etapas deste fluxo.
                // Verifica a condição abaixo antes de executar este bloco.
                if (is_file($arquivoAntigo)) { // Testa esta condição antes de executar o bloco correspondente.
                    @unlink($arquivoAntigo); // Executa esta instrução como parte da regra de negócio desta página.
                } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Verifica a condição abaixo antes de executar este bloco.
            if ($modoEdicao) { // Testa esta condição antes de executar o bloco correspondente.
                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: ficha-pet.php?id=' . $editarId . '&editado=1'); // Envia um cabeçalho HTTP, geralmente para redirecionar o navegador.
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else { // Executa esta instrução como parte da regra de negócio desta página.
                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: cadastro-pet.php?salvo=1'); // Envia um cabeçalho HTTP, geralmente para redirecionar o navegador.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit; // Encerra a execução desta requisição.
        // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
        } catch (Throwable $erro) { // Executa esta instrução como parte da regra de negócio desta página.
            // Confere se existe uma transação aberta antes de tentar desfazê-la.
            if ($pdo->inTransaction()) { // Testa esta condição antes de executar o bloco correspondente.
                // Desfaz as alterações da transação atual porque alguma etapa falhou.
                $pdo->rollBack(); // Desfaz a transação quando ocorre uma falha.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Verifica a condição abaixo antes de executar este bloco.
            if ($fotoSalva !== null) { // Testa esta condição antes de executar o bloco correspondente.
                // Armazena em "arquivo" o valor necessário para as próximas etapas deste fluxo.
                $arquivo = __DIR__ . '/' . ltrim($fotoSalva, '/'); // Armazena em arquivo o valor usado nas próximas etapas deste fluxo.
                // Verifica a condição abaixo antes de executar este bloco.
                if (is_file($arquivo)) { // Testa esta condição antes de executar o bloco correspondente.
                    @unlink($arquivo); // Executa esta instrução como parte da regra de negócio desta página.
                } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = $erro instanceof RuntimeException // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
                ? $erro->getMessage() // Executa esta instrução como parte da regra de negócio desta página.
                : 'Não foi possível salvar o pet.'; // Executa esta instrução como parte da regra de negócio desta página.
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['salvo'])) { // Testa esta condição antes de executar o bloco correspondente.
    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = 'Pet cadastrado com sucesso!'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
    $tipoMensagem = 'success'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
    // Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
    $dados = []; // Armazena em dados o valor usado nas próximas etapas deste fluxo.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

// [BP-ALTERAÇÃO #10] Vindo da ficha do tutor (cadastro-pet.php?tutor=ID), o tutor já chega selecionado.
// Só vale para um cadastro novo aberto por GET; na edição o tutor continua sendo o do pet.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $editarId === null && empty($dados['cliente_id'])) {
    $tutorDaFichaId = inteiroOuNull($_GET['tutor'] ?? null);

    if ($tutorDaFichaId !== null) {
        $dados['cliente_id'] = $tutorDaFichaId;
    }
}

// Preserva o valor selecionado de "tutorSelecionadoId" para preencher o formulário corretamente.
$tutorSelecionadoId = inteiroOuNull($dados['cliente_id'] ?? null); // Armazena em tutorSelecionadoId o valor usado nas próximas etapas deste fluxo.
// Preserva o valor selecionado de "tutorSelecionado" para preencher o formulário corretamente.
$tutorSelecionado = buscarTutorParaCampo($pdo, $tutorSelecionadoId); // Armazena em tutorSelecionado o valor usado nas próximas etapas deste fluxo.
// Preserva o valor selecionado de "rotuloTutorSelecionado" para preencher o formulário corretamente.
$rotuloTutorSelecionado = $tutorSelecionado // Armazena em rotuloTutorSelecionado o valor usado nas próximas etapas deste fluxo.
    ? (string) $tutorSelecionado['nome'] // Executa esta instrução como parte da regra de negócio desta página.
    : ''; // Executa esta instrução como parte da regra de negócio desta página.

// Armazena a coleção "especies" carregada para ser percorrida e exibida/processada a seguir.
$especies = $pdo // Armazena em especies o valor usado nas próximas etapas deste fluxo.
    ->query('SELECT id, nome FROM especies WHERE ativo = TRUE ORDER BY nome') // Executa uma consulta direta ao PostgreSQL.
    // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
    ->fetchAll(); // Carrega todas as linhas retornadas pelo banco em uma lista.

// Conta quantos pets ativos existem para exibir o total na interface.
$totalPets = (int) $pdo // Armazena em totalPets o valor usado nas próximas etapas deste fluxo.
    ->query('SELECT COUNT(*) FROM pets WHERE ativo = TRUE') // Executa uma consulta direta ao PostgreSQL.
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    ->fetchColumn(); // Obtém a primeira coluna do resultado, normalmente um ID, total ou contagem.
?> 

<?php // Executa esta instrução como parte da regra de negócio desta página.
// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = $modoEdicao ? 'Editar pet' : 'Cadastro de pet'; // Armazena em tituloPagina o valor usado nas próximas etapas deste fluxo.
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'cadastro-pet'; // Armazena em paginaAtiva o valor usado nas próximas etapas deste fluxo.

// Define o(s) CSS desta página em assets/css/ (cadastro.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'cadastro';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php'; // Carrega a configuração ou função compartilhada necessária nesta página.
?> 

<div class="page registration-page"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                <!-- Bloco visual "page-heading" que organiza esta parte da interface. -->
                <div class="page-heading"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                    <div> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                        <h1><?= $modoEdicao ? 'Editar pet' : 'Cadastro de pets' ?></h1> <?php /* Exibe o título principal. */ ?>
                        <small>Beauty Pet</small> <?php /* Cria ou abre o elemento HTML small. */ ?>
                    </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                    <span class="counter"><strong><?= $totalPets ?></strong> pets cadastrados</span> <?php /* Cria um trecho em linha para texto ou ícone. */ ?>
                </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                <?php if ($mensagem !== ''): ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                    <div class="message <?= escapar($tipoMensagem) ?>"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                        <?= escapar($mensagem) ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                    </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                <?php endif; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>

                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                <form class="form-card" method="post" enctype="multipart/form-data" action="cadastro-pet.php<?= $modoEdicao ? '?editar=' . (int) $editarId : '' ?>"> <?php /* Abre um formulário para envio de dados. */ ?>
                    <?php if ($modoEdicao): ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                        <!-- Campo "pet_id" utilizado para enviar esta informação no formulário. -->
                        <input type="hidden" name="pet_id" value="<?= (int) $editarId ?>"> <?php /* Cria um campo para entrada de dados. */ ?>
                        <!-- Campo "foto_atual" utilizado para enviar esta informação no formulário. -->
                        <input type="hidden" name="foto_atual" value="<?= escapar($fotoAtual) ?>"> <?php /* Cria um campo para entrada de dados. */ ?>
                    <?php endif; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                    <h2><?= $modoEdicao ? 'Editar dados do pet' : 'Novo cadastro de pet' ?></h2> <?php /* Exibe um título de seção. */ ?>

                    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
                    <section class="form-section"> <?php /* Abre uma seção de conteúdo da página. */ ?>
                        <div class="form-grid"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                            <div class="photo-field"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "foto", indicando ao usuário qual informação deve ser preenchida. -->
                                <label class="photo-preview" id="photoPreview" for="foto" title="Adicionar ou trocar foto"> <?php /* Exibe o rótulo de um campo. */ ?>
                                    <?php if (!empty($fotoAtual)): ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        <img src="<?= escapar($fotoAtual) ?>" alt="Foto atual do pet"> <?php /* Exibe uma imagem. */ ?>
                                    <?php else: ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        <i class="fa-solid fa-paw"></i> <?php /* Cria ou abre o elemento HTML i. */ ?>
                                    <?php endif; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                </label> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                                <div class="photo-info"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                    <strong>Foto do pet</strong> <?php /* Cria ou abre o elemento HTML strong. */ ?>
                                    <small>Clique no quadrado ou selecione uma imagem JPG, PNG ou WEBP.</small> <?php /* Cria ou abre o elemento HTML small. */ ?>
                                    <!-- Campo "foto" utilizado para enviar esta informação no formulário. -->
                                    <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp"> <?php /* Cria um campo para entrada de dados. */ ?>
                                </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-6"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Nome do pet", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="nome">Nome do pet</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "nome" utilizado para enviar esta informação no formulário. -->
                                <input id="nome" name="nome" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['nome'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="Nome do pet" required> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Espécie", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="especie">Espécie</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Lista de seleção "especie_id" com as opções válidas para este dado. -->
                                <select id="especie" name="especie_id" required> <?php /* Cria uma lista de opções. */ ?>
                                    <option value="">Selecione</option> <?php /* Define uma opção selecionável. */ ?>

                                    <?php foreach ($especies as $especie): ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        <option value="<?= (int) $especie['id'] ?>" <?php /* Define uma opção selecionável. */ ?>
                                            <?= ((string) ($dados['especie_id'] ?? '') === (string) $especie['id']) ? 'selected' : '' ?>> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            <?= escapar($especie['nome']) ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        </option> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                                    <?php endforeach; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                </select> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Raça", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="raca">Raça</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "raca" utilizado para enviar esta informação no formulário. -->
                                <input id="raca" name="raca" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['raca'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="Raça do pet"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Cor", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="cor">Cor</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "cor" utilizado para enviar esta informação no formulário. -->
                                <input id="cor" name="cor" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['cor'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="Cor"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Sexo", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="sexo">Sexo</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Lista de seleção "sexo" com as opções válidas para este dado. -->
                                <select id="sexo" name="sexo" required> <?php /* Cria uma lista de opções. */ ?>
                                    <option value="NAO_INFORMADO" <?php /* Define uma opção selecionável. */ ?>
                                        <?= (($dados['sexo'] ?? 'NAO_INFORMADO') === 'NAO_INFORMADO') ? 'selected' : '' ?>> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        Não informado <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    </option> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                                    <option value="MACHO" <?php /* Define uma opção selecionável. */ ?>
                                        <?= (($dados['sexo'] ?? '') === 'MACHO') ? 'selected' : '' ?>> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        Macho <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    </option> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                                    <option value="FEMEA" <?php /* Define uma opção selecionável. */ ?>
                                        <?= (($dados['sexo'] ?? '') === 'FEMEA') ? 'selected' : '' ?>> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        Fêmea <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    </option> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                                </select> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Peso (kg)", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="peso">Peso (kg)</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "peso" utilizado para enviar esta informação no formulário. -->
                                <input id="peso" name="peso" type="number" min="0" step="0.01" data-mascara="decimal" <?php /* [BP-ALTERAÇÃO #6] Peso com vírgula (assets/js/mascaras.js). */ ?>
                                    <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['peso'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="Ex.: 10,5"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Porte", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="porte">Porte</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Lista de seleção "porte" com as opções válidas para este dado. -->
                                <select id="porte" name="porte"> <?php /* Cria uma lista de opções. */ ?>
                                    <option value="">Selecione</option> <?php /* Define uma opção selecionável. */ ?>
                                    <option value="PEQUENO" <?= (($dados['porte'] ?? '') === 'PEQUENO') ? 'selected' : '' ?>>Pequeno</option> <?php /* Define uma opção selecionável. */ ?>
                                    <option value="MEDIO" <?= (($dados['porte'] ?? '') === 'MEDIO') ? 'selected' : '' ?>>Médio</option> <?php /* Define uma opção selecionável. */ ?>
                                    <option value="GRANDE" <?= (($dados['porte'] ?? '') === 'GRANDE') ? 'selected' : '' ?>>Grande</option> <?php /* Define uma opção selecionável. */ ?>
                                </select> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-4"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Data de nascimento", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="nascimento">Data de nascimento</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "data_nascimento" utilizado para enviar esta informação no formulário. -->
                                <input id="nascimento" name="data_nascimento" type="date" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['data_nascimento'] ?? '') ?>"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-4"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Tutor responsável", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="tutorBusca">Tutor responsável</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <div <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                    class="search-picker" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    data-search-picker="tutor" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    data-required="1"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    <!-- Bloco visual "search-picker-input-wrap" que organiza esta parte da interface. -->
                                    <div class="search-picker-input-wrap"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                        <i class="fa-solid fa-magnifying-glass"></i> <?php /* Cria ou abre o elemento HTML i. */ ?>
                                        <input <?php /* Cria um campo para entrada de dados. */ ?>
                                            id="tutorBusca" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            data-search-input <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            type="search" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            autocomplete="off" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            value="<?= escapar($rotuloTutorSelecionado) ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            data-selected-label="<?= escapar($rotuloTutorSelecionado) ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            placeholder="Digite o nome, CPF, telefone ou e-mail do tutor" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            required> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        <input <?php /* Cria um campo para entrada de dados. */ ?>
                                            data-search-value <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            type="hidden" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            name="cliente_id" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            value="<?= $tutorSelecionadoId !== null ? (int) $tutorSelecionadoId : '' ?>"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                                    <!-- Bloco visual "search-picker-results" que organiza esta parte da interface. -->
                                    <div class="search-picker-results" data-search-results hidden></div> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                                <small class="field-help">Digite para pesquisar. O sistema não carrega milhares de tutores de uma vez.</small> <?php /* Cria ou abre o elemento HTML small. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-4"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Castrado(a)", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="castrado">Castrado(a)</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Lista de seleção "castrado" com as opções válidas para este dado. -->
                                <select id="castrado" name="castrado"> <?php /* Cria uma lista de opções. */ ?>
                                    <option value="">Não informado</option> <?php /* Define uma opção selecionável. */ ?>
                                    <option value="1" <?= (($dados['castrado'] ?? '') === '1') ? 'selected' : '' ?>>Sim</option> <?php /* Define uma opção selecionável. */ ?>
                                    <option value="0" <?= (($dados['castrado'] ?? '') === '0') ? 'selected' : '' ?>>Não</option> <?php /* Define uma opção selecionável. */ ?>
                                </select> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="checks-row"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <div class="checkbox-field"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                    <!-- Campo "nascimento_aproximado" utilizado para enviar esta informação no formulário. -->
                                    <input id="aproximado" name="nascimento_aproximado" type="checkbox" value="1" <?php /* Cria um campo para entrada de dados. */ ?>
                                        <?= isset($dados['nascimento_aproximado']) ? 'checked' : '' ?>> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    <!-- Rótulo do campo "Data de nascimento aproximada", indicando ao usuário qual informação deve ser preenchida. -->
                                    <label for="aproximado">Data de nascimento aproximada</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                                <div class="checkbox-field"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                    <!-- Campo "vacinacao_em_dia" utilizado para enviar esta informação no formulário. -->
                                    <input id="vacinado" name="vacinacao_em_dia" type="checkbox" value="1" <?php /* Cria um campo para entrada de dados. */ ?>
                                        <?= isset($dados['vacinacao_em_dia']) ? 'checked' : '' ?>> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    <!-- Rótulo do campo "Vacinação em dia", indicando ao usuário qual informação deve ser preenchida. -->
                                    <label for="vacinado">Vacinação em dia</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                        </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                    </section> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
                    <section class="form-section"> <?php /* Abre uma seção de conteúdo da página. */ ?>
                        <h3>Anamnese</h3> <?php /* Exibe um subtítulo. */ ?>

                        <div class="form-grid"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                            <div class="field span-6"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Comportamento", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="comportamento">Comportamento</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Área de texto "observacoes_comportamentais" para registrar observações livres sobre o registro. -->
                                <textarea id="comportamento" name="observacoes_comportamentais" <?php /* Cria ou abre o elemento HTML textarea. */ ?>
                                    placeholder="Bravo, sociável, agitado, medroso..."><?= escapar($dados['observacoes_comportamentais'] ?? '') ?></textarea> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-6"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Medicamentos", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="medicamentos">Medicamentos</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Área de texto "medicamentos" para registrar observações livres sobre o registro. -->
                                <textarea id="medicamentos" name="medicamentos" <?php /* Cria ou abre o elemento HTML textarea. */ ?>
                                    placeholder="Medicamentos de uso contínuo ou recente"><?= escapar($dados['medicamentos'] ?? '') ?></textarea> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-12"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Observações médicas", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="observacoesMedicas">Observações médicas</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Área de texto "observacoes_medicas" para registrar observações livres sobre o registro. -->
                                <textarea id="observacoesMedicas" name="observacoes_medicas" <?php /* Cria ou abre o elemento HTML textarea. */ ?>
                                    placeholder="Alergias, doenças, restrições e outras observações"><?= escapar($dados['observacoes_medicas'] ?? '') ?></textarea> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                        </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                    </section> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                    <div class="form-actions"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                        <?php if ($modoEdicao): ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            <a class="secondary-action-button" href="ficha-pet.php?id=<?= (int) $editarId ?>">Cancelar</a> <?php /* Cria um link de navegação. */ ?>
                        <?php endif; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                        <button class="save-button" type="submit"><?= $modoEdicao ? 'Salvar alterações' : 'Salvar' ?></button> <?php /* Cria um botão de ação. */ ?>
                    </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                </form> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

            <script>
document.addEventListener('DOMContentLoaded', () => {
    const pickers = document.querySelectorAll('[data-search-picker="tutor"]');

    pickers.forEach(picker => {
        const inputBusca = picker.querySelector('[data-search-input]');
        const inputHidden = picker.querySelector('[data-search-value]');
        const caixaResultados = picker.querySelector('[data-search-results]');
        let timer = null;

        if (!inputBusca || !inputHidden || !caixaResultados) return;

        // Ao digitar no campo de busca
        inputBusca.addEventListener('input', () => {
            clearTimeout(timer);
            const termo = inputBusca.value.trim();

            // Limpa o ID oculto caso o usuário altere o texto
            inputHidden.value = '';

            if (termo.length < 2) {
                caixaResultados.innerHTML = '';
                caixaResultados.hidden = true;
                return;
            }

            // Aguarda 300ms após parar de digitar (debounce)
            timer = setTimeout(() => {
                fetch(`buscar-tutores.php?q=${encodeURIComponent(termo)}`)
                    .then(res => res.json())
                    .then(tutores => {
                        caixaResultados.innerHTML = '';

                        if (tutores.length === 0) {
                            caixaResultados.innerHTML = '<div class="picker-item-empty">Nenhum tutor encontrado</div>';
                        } else {
                            tutores.forEach(tutor => {
                                const item = document.createElement('div');
                                item.classList.add('picker-item');
                                item.textContent = `${tutor.nome} ${tutor.cpf ? ' - CPF: ' + tutor.cpf : ''}`;
                                
                                // Evento ao clicar em um resultado
                                item.addEventListener('click', () => {
                                    inputBusca.value = tutor.nome;
                                    inputHidden.value = tutor.id; // Grava o cliente_id no campo oculto
                                    inputBusca.dataset.selectedLabel = tutor.nome;
                                    caixaResultados.hidden = true;
                                });

                                caixaResultados.appendChild(item);
                            });
                        }

                        caixaResultados.hidden = false;
                    })
                    .catch(() => {
                        caixaResultados.innerHTML = '<div class="picker-item-empty">Erro ao carregar dados</div>';
                        caixaResultados.hidden = false;
                    });
            }, 300);
        });

        // Fecha a lista suspensa se clicar fora do campo
        document.addEventListener('click', (e) => {
            if (!picker.contains(e.target)) {
                caixaResultados.hidden = true;
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../includes/rodape.php'; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
