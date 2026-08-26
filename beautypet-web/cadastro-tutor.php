<?php
require __DIR__ . '/config.php';

/*
 * Proteção extra: evita o erro de função indefinida caso um
 * funcoes.php antigo seja mantido por engano.
 */
if (!function_exists('cpfValido')) {
    function cpfValido(?string $cpf): bool
    {
        $cpf = preg_replace('/\D+/', '', $cpf ?? '');

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

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
}

$mensagem = '';
$tipoMensagem = '';
$erroCpf = false;
$dados = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = textoOuNull($_POST['nome'] ?? null);
    $cpf = somenteNumeros($_POST['cpf'] ?? null);
    $telefonePrincipal = somenteNumeros($_POST['telefone_principal'] ?? null);
    $telefoneReserva = somenteNumeros($_POST['telefone_reserva'] ?? null);
    $email = textoOuNull($_POST['email'] ?? null);
    $cep = somenteNumeros($_POST['cep'] ?? null);

    if ($nome === null || $telefonePrincipal === null) {
        $mensagem = 'Preencha o nome e o telefone principal.';
        $tipoMensagem = 'error';
    } elseif ($cpf !== null && !cpfValido($cpf)) {
        $mensagem = 'CPF inválido. Confira os números digitados.';
        $tipoMensagem = 'error';
        $erroCpf = true;
    } elseif ($cep !== null && strlen($cep) !== 8) {
        $mensagem = 'O CEP deve possuir 8 números.';
        $tipoMensagem = 'error';
    } else {
        try {
            $sql = '
                INSERT INTO clientes (
                    nome,
                    cpf,
                    email,
                    telefone_principal,
                    telefone_reserva,
                    cep,
                    logradouro,
                    numero,
                    complemento,
                    bairro,
                    cidade,
                    estado,
                    referencia,
                    observacoes
                ) VALUES (
                    :nome,
                    :cpf,
                    :email,
                    :telefone_principal,
                    :telefone_reserva,
                    :cep,
                    :logradouro,
                    :numero,
                    :complemento,
                    :bairro,
                    :cidade,
                    :estado,
                    :referencia,
                    :observacoes
                )
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome'               => $nome,
                ':cpf'                => $cpf,
                ':email'              => $email,
                ':telefone_principal' => $telefonePrincipal,
                ':telefone_reserva'   => $telefoneReserva,
                ':cep'                => $cep,
                ':logradouro'         => textoOuNull($_POST['logradouro'] ?? null),
                ':numero'             => textoOuNull($_POST['numero'] ?? null),
                ':complemento'        => textoOuNull($_POST['complemento'] ?? null),
                ':bairro'             => textoOuNull($_POST['bairro'] ?? null),
                ':cidade'             => textoOuNull($_POST['cidade'] ?? null),
                ':estado'             => textoOuNull($_POST['estado'] ?? null),
                ':referencia'         => textoOuNull($_POST['referencia'] ?? null),
                ':observacoes'        => textoOuNull($_POST['observacoes'] ?? null),
            ]);

            header('Location: cadastro-tutor.php?salvo=1');
            exit;
        } catch (PDOException $erro) {
            if ($erro->getCode() === '23000') {
                $mensagem = 'Já existe um tutor cadastrado com esse CPF.';
            } else {
                $mensagem = 'Não foi possível salvar o tutor.';
            }

            $tipoMensagem = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['salvo'])) {
    $mensagem = 'Tutor cadastrado com sucesso!';
    $tipoMensagem = 'success';
    $dados = [];
}

$totalTutores = (int) $pdo
    ->query('SELECT COUNT(*) FROM clientes WHERE ativo = TRUE')
    ->fetchColumn();
?>

<?php
$tituloPagina = 'Cadastro de tutor';
$paginaAtiva = 'cadastro-tutor';

require __DIR__ . '/includes/topo.php';
?>

<div class="page registration-page">
                <div class="page-heading">
                    <div>
                        <h1>Cadastro de tutores</h1>
                        <small>Beauty Pet</small>
                    </div>
                    <span class="counter"><strong><?= $totalTutores ?></strong> tutores cadastrados</span>
                </div>

                <?php if ($mensagem !== ''): ?>
                    <div class="message <?= escapar($tipoMensagem) ?>">
                        <?= escapar($mensagem) ?>
                    </div>
                <?php endif; ?>

                <form class="form-card" method="post" action="cadastro-tutor.php">
                    <h2>Novo cadastro de tutor</h2>

                    <section class="form-section">
                        <div class="form-grid">
                            <div class="field span-12">
                                <label for="nome">Nome completo</label>
                                <input
                                    id="nome"
                                    name="nome"
                                    type="text"
                                    value="<?= escapar($dados['nome'] ?? '') ?>"
                                    placeholder="Nome do tutor"
                                    required>
                            </div>

                            <div class="field span-4">
                                <label for="cpf">CPF</label>
                                <input
                                    id="cpf"
                                    name="cpf"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="14"
                                    class="<?= $erroCpf ? 'input-error' : '' ?>"
                                    aria-invalid="<?= $erroCpf ? 'true' : 'false' ?>"
                                    value="<?= escapar($dados['cpf'] ?? '') ?>"
                                    placeholder="000.000.000-00">

                                <?php if ($erroCpf): ?>
                                    <small class="field-error">
                                        O CPF não passou na validação dos dígitos verificadores.
                                    </small>
                                <?php endif; ?>
                            </div>

                            <div class="field span-4">
                                <label for="telefone">Telefone</label>
                                <input
                                    id="telefone"
                                    name="telefone_principal"
                                    type="tel"
                                    value="<?= escapar($dados['telefone_principal'] ?? '') ?>"
                                    placeholder="(11) 99999-9999"
                                    required>
                            </div>

                            <div class="field span-4">
                                <label for="telefoneReserva">WhatsApp / telefone reserva</label>
                                <input
                                    id="telefoneReserva"
                                    name="telefone_reserva"
                                    type="tel"
                                    value="<?= escapar($dados['telefone_reserva'] ?? '') ?>"
                                    placeholder="(11) 99999-9999">
                            </div>

                            <div class="field span-12">
                                <label for="email">E-mail</label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="<?= escapar($dados['email'] ?? '') ?>"
                                    placeholder="email@exemplo.com">
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h3>Endereço</h3>

                        <div class="form-grid">
                            <div class="field span-9">
                                <label for="logradouro">Rua</label>
                                <input id="logradouro" name="logradouro" type="text"
                                    value="<?= escapar($dados['logradouro'] ?? '') ?>" placeholder="Rua">
                            </div>

                            <div class="field span-3">
                                <label for="numero">Número</label>
                                <input id="numero" name="numero" type="text"
                                    value="<?= escapar($dados['numero'] ?? '') ?>" placeholder="Número">
                            </div>

                            <div class="field span-3">
                                <label for="cep">CEP</label>
                                <input id="cep" name="cep" type="text" inputmode="numeric" maxlength="9"
                                    value="<?= escapar($dados['cep'] ?? '') ?>" placeholder="00000-000">
                            </div>

                            <div class="field span-3">
                                <label for="bairro">Bairro</label>
                                <input id="bairro" name="bairro" type="text"
                                    value="<?= escapar($dados['bairro'] ?? '') ?>" placeholder="Bairro">
                            </div>

                            <div class="field span-4">
                                <label for="cidade">Cidade</label>
                                <input id="cidade" name="cidade" type="text"
                                    value="<?= escapar($dados['cidade'] ?? '') ?>" placeholder="Cidade">
                            </div>

                            <div class="field span-2">
                                <label for="estado">Estado</label>
                                <select id="estado" name="estado">
                                    <option value="">UF</option>
                                    <?php
                                    $estados = [
                                        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS',
                                        'MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC',
                                        'SP','SE','TO'
                                    ];
                                    foreach ($estados as $estado):
                                    ?>
                                        <option value="<?= $estado ?>"
                                            <?= (($dados['estado'] ?? '') === $estado) ? 'selected' : '' ?>>
                                            <?= $estado ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field span-6">
                                <label for="complemento">Complemento</label>
                                <input id="complemento" name="complemento" type="text"
                                    value="<?= escapar($dados['complemento'] ?? '') ?>"
                                    placeholder="Apartamento, bloco, casa...">
                            </div>

                            <div class="field span-6">
                                <label for="referencia">Ponto de referência</label>
                                <input id="referencia" name="referencia" type="text"
                                    value="<?= escapar($dados['referencia'] ?? '') ?>"
                                    placeholder="Próximo a...">
                            </div>

                            <div class="field span-12">
                                <label for="observacoes">Observações</label>
                                <textarea id="observacoes" name="observacoes"
                                    placeholder="Observações sobre o tutor"><?= escapar($dados['observacoes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <button class="save-button" type="submit">Salvar</button>
                    </div>
                </form>
            </div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
