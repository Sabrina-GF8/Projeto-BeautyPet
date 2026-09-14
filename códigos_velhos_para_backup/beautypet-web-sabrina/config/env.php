<?php

function carregarEnv($arquivo)
{
    if (!file_exists($arquivo)) {
        throw new Exception("Arquivo .env não encontrado.");
    }

    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($linhas as $linha) {

        // Ignora comentários
        if (str_starts_with(trim($linha), '#')) {
            continue;
        }

        // Separa o nome da variável do valor
        [$nome, $valor] = array_pad(explode('=', $linha, 2), 2, null);

        if ($nome !== null && $valor !== null) {
            $_ENV[trim($nome)] = trim($valor);
        }
    }
}