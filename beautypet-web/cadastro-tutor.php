<?php
// Inclui configuração (conexão PDO, funções auxiliares) usada em toda a página
require __DIR__ . '/config.php';

/*
 * Proteção extra: evita o erro de função indefinida caso um
 * funcoes.php antigo seja mantido por engano.
 */
// Define a função apenas se ela ainda não existir (evita erro de redeclaração
// caso já esteja definida em config.php/funcoes.php)
if (!function_exists('cpfValido')) {
    /**
     * Valida um CPF verificando os dois dígitos verificadores.
     * Retorna false para CPFs com tamanho incorreto ou com todos
     * os dígitos iguais (ex.: 111.111.111-11), que são inválidos
     * mesmo que a conta dos dígitos verificadores "acidentalmente" bata.
     */
    function cpfValido(?string $cpf): bool
    {
        // Remove qualquer caractere que não seja dígito (pontos, traço, espaços)
        $cpf = preg_replace('/\D+/', '', $cpf ?? '');

        // CPF deve ter exatamente 11 dígitos e não pode ser uma sequência
        // de dígitos repetidos (ex.: 000.000.000-00, 111.111.111-11 etc.)
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Calcula e confere os dois dígitos verificadores (posições 9 e 10,
        // índice 0-based), usando o algoritmo padrão de módulo 11 do CPF
        for ($posicao = 9; $posicao <= 10; $posicao++) {
            $soma = 0;

            // Soma ponderada dos dígitos anteriores à posição do dígito verificador
            for ($indice = 0; $indice < $posicao; $indice++) {
                $soma += (int) $cpf[$indice] * (($posicao + 1) - $indice);
            }

            $digito = (10 * $soma) % 11;
            // Regra do CPF: se o resultado for 10, o dígito verificador é 0
            $digito = $digito === 10 ? 0 : $digito;

            // Se o dígito calculado não bater com o dígito informado no CPF, é inválido
            if ((int) $cpf[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }
}

// Variáveis de mensagem de feedback (sucesso/erro) exibidas ao usuário
$mensagem = '';
$tipoMensagem = '';
// Flag específica para destacar visualmente o campo CPF quando inválido
$erroCpf = false;
// Fonte inicial dos dados do formulário: os dados enviados via POST
// (usados para repopular o formulário em caso de erro de validação)
$dados = $_POST;

// Bloco de processamento do formulário, executado apenas em requisições POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura e normaliza os campos principais do formulário
    $nome = textoOuNull($_POST['nome'] ?? null);
    // CPF e telefones são armazenados já sem formatação (apenas números)
    $cpf = somenteNumeros($_POST['cpf'] ?? null);
    $telefonePrincipal = somenteNumeros($_POST['telefone_principal'] ?? null);
    $telefoneReserva = somenteNumeros($_POST['telefone_reserva'] ?? null);
    $email = textoOuNull($_POST['email'] ?? null);
    $cep = somenteNumeros($_POST['cep'] ?? null);

    // Nome e telefone principal são obrigatórios
    if ($nome === null || $telefonePrincipal === null) {
        $mensagem = 'Preencha o nome e o telefone principal.';
        $tipoMensagem = 'error';
    } elseif ($cpf !== null && !cpfValido($cpf)) {
        // CPF é opcional, mas se informado deve passar na validação dos dígitos verificadores
        $mensagem = 'CPF inválido. Confira os números digitados.';
        $tipoMensagem = 'error';
        $erroCpf = true;
    } elseif ($cep !== null && strlen($cep) !== 8) {
        // CEP é opcional, mas se informado deve ter exatamente 8 dígitos
        $mensagem = 'O CEP deve possuir 8 números.';
        $tipoMensagem = 'error';
    } else {
        try {
            // Query de inserção do novo tutor (cliente), com dados pessoais e endereço
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

            // Redireciona indicando sucesso, evitando reenvio do formulário
            // ao atualizar a página (padrão PRG - Post/Redirect/Get)
            header('Location: cadastro-tutor.php?salvo=1');
            exit;
        } catch (PDOException $erro) {
            // Código de erro '23000' indica violação de restrição de unicidade/integridade
            // (nesse caso, CPF duplicado, já que a coluna deve ter uma constraint UNIQUE)
            if ($erro->getCode() === '23000') {
                $mensagem = 'Já existe um tutor cadastrado com esse CPF.';
            } else {
                $mensagem = 'Não foi possível salvar o tutor.';
            }

            $tipoMensagem = 'error';
        }
    }
}

// Após redirecionamento de sucesso (padrão PRG), exibe a mensagem de confirmação
// e limpa os dados do formulário para começar um novo cadastro em branco
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['salvo'])) {
    $mensagem = 'Tutor cadastrado com sucesso!';
    $tipoMensagem = 'success';
    $dados = [];
}

// Contagem total de tutores (clientes) ativos, exibida como indicador no topo da página
$totalTutores = (int) $pdo
    ->query('SELECT COUNT(*) FROM clientes WHERE ativo = TRUE')
    ->fetchColumn();
?>

<?php
// Metadados da página usados pelo template de topo (título da aba, item de menu ativo)
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
                    <!-- Contador de tutores ativos cadastrados no sistema -->
                    <span class="counter"><strong><?= $totalTutores ?></strong> tutores cadastrados</span>
                </div>

                <?php if ($mensagem !== ''): ?>
                    <!-- Exibe a mensagem de feedback (sucesso ou erro) definida no processamento acima -->
                    <div class="message <?= escapar($tipoMensagem) ?>">
                        <?= escapar($mensagem) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário de cadastro de tutor -->
                <form class="form-card" method="post" action="cadastro-tutor.php">
                    <h2>Novo cadastro de tutor</h2>

                    <section class="form-section">
                        <div class="form-grid">
                            <!-- Nome completo: campo obrigatório -->
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

                            <!-- CPF: opcional, mas validado quando preenchido.
                                 Recebe classe/atributo de erro visual quando a validação falha -->
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
                                    <!-- Mensagem específica de erro de CPF, exibida abaixo do campo -->
                                    <small class="field-error">
                                        O CPF não passou na validação dos dígitos verificadores.
                                    </small>
                                <?php endif; ?>
                            </div>

                            <!-- Telefone principal: campo obrigatório -->
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

                            <!-- Telefone reserva/WhatsApp: opcional -->
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

                    <!-- Seção de endereço: todos os campos são opcionais, exceto validação
                         de formato do CEP quando preenchido -->
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

                            <!-- Estado (UF): select com todas as 27 unidades federativas do Brasil,
                                 geradas a partir de um array fixo definido inline -->
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
