<?php
// Inclui configuração (conexão PDO, funções auxiliares) usada em toda a página
require __DIR__ . '/../config/config.php';

// Variáveis de mensagem de feedback (sucesso/erro) exibidas ao usuário
$mensagem = '';
$tipoMensagem = '';

// Fonte inicial dos dados do formulário: os dados enviados via POST
// (usados para repopular o formulário em caso de erro de validação)
$dados = $_POST;

// Guarda o caminho da foto salva em disco, para poder excluí-la
// caso ocorra algum erro após o upload (rollback do arquivo físico)
$fotoSalva = null;

// Bloco de processamento do formulário, executado apenas em requisições POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura e normaliza os campos obrigatórios/principais do formulário
    $nome = textoOuNull($_POST['nome'] ?? null);
    $clienteId = inteiroOuNull($_POST['cliente_id'] ?? null);
    $especieId = inteiroOuNull($_POST['especie_id'] ?? null);
    $raca = textoOuNull($_POST['raca'] ?? null);
    // Sexo tem valor padrão "NAO_INFORMADO" caso não seja enviado
    $sexo = $_POST['sexo'] ?? 'NAO_INFORMADO';
    $peso = textoOuNull($_POST['peso'] ?? null);
    $porte = textoOuNull($_POST['porte'] ?? null);
    $castrado = inteiroOuNull($_POST['castrado'] ?? null);

    // Validação: nome, cliente (tutor) e espécie são obrigatórios
    if ($nome === null || $clienteId === null || $especieId === null) {
        $mensagem = 'Preencha o nome, a espécie e o tutor responsável.';
        $tipoMensagem = 'error';
    } elseif (!in_array($sexo, ['MACHO', 'FEMEA', 'NAO_INFORMADO'], true)) {
        // Valida se o sexo enviado está entre os valores permitidos
        $mensagem = 'Selecione um sexo válido.';
        $tipoMensagem = 'error';
    } elseif ($porte !== null && !in_array($porte, ['PEQUENO', 'MEDIO', 'GRANDE'], true)) {
        // Porte é opcional, mas se informado deve ser um dos valores válidos
        $mensagem = 'Selecione um porte válido.';
        $tipoMensagem = 'error';
    } elseif ($peso !== null && (!is_numeric($peso) || (float) $peso < 0)) {
        // Peso é opcional, mas se informado deve ser numérico e não-negativo
        $mensagem = 'Informe um peso válido.';
        $tipoMensagem = 'error';
    } else {
        try {
            // Inicia transação: validações de FK, upload de foto e inserção
            // do pet devem ocorrer de forma consistente
            $pdo->beginTransaction();

            // Confirma que o tutor (cliente) informado existe e está ativo
            $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id = ? AND ativo = TRUE');
            $stmt->execute([$clienteId]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('O tutor selecionado não foi encontrado.');
            }

            // Confirma que a espécie informada existe e está ativa
            $stmt = $pdo->prepare('SELECT id FROM especies WHERE id = ? AND ativo = TRUE');
            $stmt->execute([$especieId]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('A espécie selecionada não foi encontrada.');
            }

            $racaId = null;

            if ($raca !== null) {
                // Cria a raça se ela ainda não existir para essa espécie, ou reaproveita
                // (e reativa) a raça já existente com o mesmo nome/espécie.
                // ON DUPLICATE KEY UPDATE + LAST_INSERT_ID(id) permite obter o ID
                // correto tanto no caso de inserção quanto no caso de raça já existente.
                $stmt = $pdo->prepare(
                    'INSERT INTO racas (especie_id, nome)
                     VALUES (:especie_id, :nome)
                     ON DUPLICATE KEY UPDATE
                        id = LAST_INSERT_ID(id),
                        ativo = TRUE'
                );

                $stmt->execute([
                    ':especie_id' => $especieId,
                    ':nome'       => $raca,
                ]);

                $racaId = (int) $pdo->lastInsertId();
            }

            // Processa o upload da foto do pet (se enviada), salvando o arquivo
            // em disco e retornando o caminho relativo a ser gravado no banco
            $fotoSalva = salvarFotoPet($_FILES['foto'] ?? []);

            // Query de inserção do novo pet, com todos os campos do cadastro completo
            $sql = '
                INSERT INTO pets (
                    cliente_id,
                    especie_id,
                    raca_id,
                    nome,
                    foto_url,
                    sexo,
                    cor,
                    peso,
                    porte,
                    data_nascimento,
                    nascimento_aproximado,
                    castrado,
                    vacinacao_em_dia,
                    medicamentos,
                    observacoes_comportamentais,
                    observacoes_medicas
                ) VALUES (
                    :cliente_id,
                    :especie_id,
                    :raca_id,
                    :nome,
                    :foto_url,
                    :sexo,
                    :cor,
                    :peso,
                    :porte,
                    :data_nascimento,
                    :nascimento_aproximado,
                    :castrado,
                    :vacinacao_em_dia,
                    :medicamentos,
                    :observacoes_comportamentais,
                    :observacoes_medicas
                )
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cliente_id'                  => $clienteId,
                ':especie_id'                  => $especieId,
                ':raca_id'                     => $racaId,
                ':nome'                        => $nome,
                ':foto_url'                    => $fotoSalva,
                ':sexo'                        => $sexo,
                ':cor'                         => textoOuNull($_POST['cor'] ?? null),
                ':peso'                        => $peso !== null ? (float) $peso : null,
                ':porte'                       => $porte,
                ':data_nascimento'             => textoOuNull($_POST['data_nascimento'] ?? null),
                // Checkbox: se marcado, vem no POST; senão assume 0
                ':nascimento_aproximado'       => isset($_POST['nascimento_aproximado']) ? 1 : 0,
                ':castrado'                    => $castrado,
                ':vacinacao_em_dia'            => isset($_POST['vacinacao_em_dia']) ? 1 : 0,
                ':medicamentos'                => textoOuNull($_POST['medicamentos'] ?? null),
                ':observacoes_comportamentais' => textoOuNull($_POST['observacoes_comportamentais'] ?? null),
                ':observacoes_medicas'         => textoOuNull($_POST['observacoes_medicas'] ?? null),
            ]);

            // Confirma a transação, persistindo raça (se nova) e o pet
            $pdo->commit();

            // Redireciona indicando sucesso, evitando reenvio do formulário
            // ao atualizar a página (padrão PRG - Post/Redirect/Get)
            header('Location: cadastro-pet.php?salvo=1');
            exit;
        } catch (Throwable $erro) {
            // Desfaz a transação do banco em caso de qualquer falha
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Se uma foto já havia sido salva em disco antes do erro,
            // remove o arquivo para não deixar imagem órfã sem pet associado
            if ($fotoSalva !== null) {
                $arquivo = __DIR__ . '/' . $fotoSalva;

                if (is_file($arquivo)) {
                    unlink($arquivo);
                }
            }

            // Usa a mensagem específica da exceção quando for RuntimeException
            // (erros de validação de tutor/espécie), senão exibe mensagem genérica
            $mensagem = $erro instanceof RuntimeException
                ? $erro->getMessage()
                : 'Não foi possível salvar o pet. Confira se a atualização do banco foi executada.';

            $tipoMensagem = 'error';
        }
    }
}

// Após redirecionamento de sucesso (padrão PRG), exibe a mensagem de confirmação
// e limpa os dados do formulário para começar um novo cadastro em branco
if (isset($_GET['salvo'])) {
    $mensagem = 'Pet cadastrado com sucesso!';
    $tipoMensagem = 'success';
    $dados = [];
}

// Lista de tutores (clientes ativos) para popular o <select> de tutor responsável
$tutores = $pdo
    ->query('SELECT id, nome FROM clientes WHERE ativo = TRUE ORDER BY nome')
    ->fetchAll();

// Lista de espécies ativas para popular o <select> de espécie
$especies = $pdo
    ->query('SELECT id, nome FROM especies WHERE ativo = TRUE ORDER BY nome')
    ->fetchAll();

// Contagem total de pets ativos, exibida como indicador no topo da página
$totalPets = (int) $pdo
    ->query('SELECT COUNT(*) FROM pets WHERE ativo = TRUE')
    ->fetchColumn();
?>

<?php
// Metadados da página usados pelo template de topo (título da aba, item de menu ativo)
$tituloPagina = 'Cadastro de pet';
$paginaAtiva = 'cadastro-pet';

require __DIR__ . '/../includes/topo.php';
?>

<div class="page registration-page">
                <div class="page-heading">
                    <div>
                        <h1>Cadastro de pets</h1>
                        <small>Beauty Pet</small>
                    </div>
                    <!-- Contador de pets ativos cadastrados no sistema -->
                    <span class="counter"><strong><?= $totalPets ?></strong> pets cadastrados</span>
                </div>

                <?php if ($mensagem !== ''): ?>
                    <!-- Exibe a mensagem de feedback (sucesso ou erro) definida no processamento acima -->
                    <div class="message <?= escapar($tipoMensagem) ?>">
                        <?= escapar($mensagem) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário de cadastro. enctype multipart é necessário por causa
                     do campo de upload de foto (input type="file") -->
                <form class="form-card" method="post" enctype="multipart/form-data">
                    <h2>Novo cadastro de pet</h2>

                    <section class="form-section">
                        <div class="form-grid">
                            <!-- Campo de foto: label estilizado funciona como área clicável
                                 para abrir o seletor de arquivo (input real fica oculto/estilizado via CSS) -->
                            <div class="photo-field">
                                <label class="photo-preview" id="photoPreview" for="foto" title="Adicionar foto">
                                    <i class="fa-solid fa-paw"></i>
                                </label>

                                <div class="photo-info">
                                    <strong>Foto do pet</strong>
                                    <small>Clique no quadrado ou selecione uma imagem JPG, PNG ou WEBP.</small>
                                    <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                                </div>
                            </div>

                            <!-- Nome do pet: campo obrigatório -->
                            <div class="field span-6">
                                <label for="nome">Nome do pet</label>
                                <input id="nome" name="nome" type="text"
                                    value="<?= escapar($dados['nome'] ?? '') ?>"
                                    placeholder="Nome do pet" required>
                            </div>

                            <!-- Espécie: obrigatória, populada a partir da lista de espécies ativas -->
                            <div class="field span-3">
                                <label for="especie">Espécie</label>
                                <select id="especie" name="especie_id" required>
                                    <option value="">Selecione</option>

                                    <?php foreach ($especies as $especie): ?>
                                        <option value="<?= (int) $especie['id'] ?>"
                                            <?= ((string) ($dados['especie_id'] ?? '') === (string) $especie['id']) ? 'selected' : '' ?>>
                                            <?= escapar($especie['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Raça: campo de texto livre; se preenchida, é criada/reaproveitada
                                 automaticamente na tabela racas ao salvar o formulário -->
                            <div class="field span-3">
                                <label for="raca">Raça</label>
                                <input id="raca" name="raca" type="text"
                                    value="<?= escapar($dados['raca'] ?? '') ?>"
                                    placeholder="Raça do pet">
                            </div>

                            <div class="field span-3">
                                <label for="cor">Cor</label>
                                <input id="cor" name="cor" type="text"
                                    value="<?= escapar($dados['cor'] ?? '') ?>"
                                    placeholder="Cor">
                            </div>

                            <!-- Sexo: select com valor padrão "Não informado" pré-selecionado -->
                            <div class="field span-3">
                                <label for="sexo">Sexo</label>
                                <select id="sexo" name="sexo" required>
                                    <option value="NAO_INFORMADO"
                                        <?= (($dados['sexo'] ?? 'NAO_INFORMADO') === 'NAO_INFORMADO') ? 'selected' : '' ?>>
                                        Não informado
                                    </option>
                                    <option value="MACHO"
                                        <?= (($dados['sexo'] ?? '') === 'MACHO') ? 'selected' : '' ?>>
                                        Macho
                                    </option>
                                    <option value="FEMEA"
                                        <?= (($dados['sexo'] ?? '') === 'FEMEA') ? 'selected' : '' ?>>
                                        Fêmea
                                    </option>
                                </select>
                            </div>

                            <!-- Peso em kg, aceita casas decimais (step 0.01), opcional -->
                            <div class="field span-3">
                                <label for="peso">Peso (kg)</label>
                                <input id="peso" name="peso" type="number" min="0" step="0.01"
                                    value="<?= escapar($dados['peso'] ?? '') ?>"
                                    placeholder="Ex.: 10,5">
                            </div>

                            <!-- Porte: opcional, sem valor padrão selecionado -->
                            <div class="field span-3">
                                <label for="porte">Porte</label>
                                <select id="porte" name="porte">
                                    <option value="">Selecione</option>
                                    <option value="PEQUENO" <?= (($dados['porte'] ?? '') === 'PEQUENO') ? 'selected' : '' ?>>Pequeno</option>
                                    <option value="MEDIO" <?= (($dados['porte'] ?? '') === 'MEDIO') ? 'selected' : '' ?>>Médio</option>
                                    <option value="GRANDE" <?= (($dados['porte'] ?? '') === 'GRANDE') ? 'selected' : '' ?>>Grande</option>
                                </select>
                            </div>

                            <div class="field span-4">
                                <label for="nascimento">Data de nascimento</label>
                                <input id="nascimento" name="data_nascimento" type="date"
                                    value="<?= escapar($dados['data_nascimento'] ?? '') ?>">
                            </div>

                            <!-- Tutor responsável: obrigatório, populado a partir dos clientes ativos -->
                            <div class="field span-4">
                                <label for="tutor">Tutor responsável</label>
                                <select id="tutor" name="cliente_id" required>
                                    <option value="">Selecione o tutor</option>

                                    <?php foreach ($tutores as $tutor): ?>
                                        <option value="<?= (int) $tutor['id'] ?>"
                                            <?= ((string) ($dados['cliente_id'] ?? '') === (string) $tutor['id']) ? 'selected' : '' ?>>
                                            <?= escapar($tutor['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Castrado: three-state (vazio/não informado, sim, não) -->
                            <div class="field span-4">
                                <label for="castrado">Castrado(a)</label>
                                <select id="castrado" name="castrado">
                                    <option value="">Não informado</option>
                                    <option value="1" <?= (($dados['castrado'] ?? '') === '1') ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= (($dados['castrado'] ?? '') === '0') ? 'selected' : '' ?>>Não</option>
                                </select>
                            </div>

                            <!-- Checkboxes auxiliares: nascimento aproximado e vacinação em dia -->
                            <div class="checks-row">
                                <div class="checkbox-field">
                                    <input id="aproximado" name="nascimento_aproximado" type="checkbox" value="1"
                                        <?= isset($dados['nascimento_aproximado']) ? 'checked' : '' ?>>
                                    <label for="aproximado">Data de nascimento aproximada</label>
                                </div>

                                <div class="checkbox-field">
                                    <input id="vacinado" name="vacinacao_em_dia" type="checkbox" value="1"
                                        <?= isset($dados['vacinacao_em_dia']) ? 'checked' : '' ?>>
                                    <label for="vacinado">Vacinação em dia</label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Seção de anamnese: informações comportamentais e médicas do pet -->
                    <section class="form-section">
                        <h3>Anamnese</h3>

                        <div class="form-grid">
                            <div class="field span-6">
                                <label for="comportamento">Comportamento</label>
                                <textarea id="comportamento" name="observacoes_comportamentais"
                                    placeholder="Bravo, sociável, agitado, medroso..."><?= escapar($dados['observacoes_comportamentais'] ?? '') ?></textarea>
                            </div>

                            <div class="field span-6">
                                <label for="medicamentos">Medicamentos</label>
                                <textarea id="medicamentos" name="medicamentos"
                                    placeholder="Medicamentos de uso contínuo ou recente"><?= escapar($dados['medicamentos'] ?? '') ?></textarea>
                            </div>

                            <div class="field span-12">
                                <label for="observacoesMedicas">Observações médicas</label>
                                <textarea id="observacoesMedicas" name="observacoes_medicas"
                                    placeholder="Alergias, doenças, restrições e outras observações"><?= escapar($dados['observacoes_medicas'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <button class="save-button" type="submit">Salvar</button>
                    </div>
                </form>
            </div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
