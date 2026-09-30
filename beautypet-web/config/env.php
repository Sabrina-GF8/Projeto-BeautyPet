<?php // Executa esta instrução como parte da regra de negócio desta página.

function carregarEnv($arquivo) // Define uma função reutilizável que concentra esta regra do sistema.
{ // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    if (!file_exists($arquivo)) { // Testa esta condição antes de executar o bloco correspondente.
        throw new Exception("Arquivo .env não encontrado."); // Lança uma exceção para interromper a operação com uma mensagem controlada.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Armazena em linhas o valor usado nas próximas etapas deste fluxo.

    foreach ($linhas as $linha) { // Percorre a coleção e processa cada registro individualmente.

        // Ignora comentários
        if (str_starts_with(trim($linha), '#')) { // Testa esta condição antes de executar o bloco correspondente.
            continue; // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Separa o nome da variável do valor
        [$nome, $valor] = array_pad(explode('=', $linha, 2), 2, null); // Executa esta instrução como parte da regra de negócio desta página.

        if ($nome !== null && $valor !== null) { // Testa esta condição antes de executar o bloco correspondente.
            $_ENV[trim($nome)] = trim($valor); // Executa esta instrução como parte da regra de negócio desta página.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
