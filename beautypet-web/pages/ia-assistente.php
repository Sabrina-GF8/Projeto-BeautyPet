<?php
/**
 * ARQUIVO: pages/ia-assistente.php
 * RESPONSABILIDADE: Devolver a resposta do Assistente em formato JSON para o Dashboard.
 *
 * Esta pagina nao tem visual proprio, exatamente como pages/ia-insights.php. Ela existe para que
 * a aba "Assistente" do Dashboard possa enviar a pergunta digitada e receber a resposta sem
 * recarregar a tela inteira.
 *
 * A pergunta chega por POST e serve apenas para escolher qual analise sera consultada.
 * Nenhuma parte do texto digitado entra em uma consulta ao banco.
 */

// Carrega o arquivo de configuracao do sistema, incluindo a conexao com o banco de dados.
require __DIR__ . '/../config/config.php';
// Carrega as funcoes que consultam e agrupam os dados das analises.
require __DIR__ . '/../config/analise.php';
// Carrega os algoritmos de aprendizado de maquina e mineracao de dados.
require __DIR__ . '/../config/ia-motor.php';
// Carrega as funcoes que interpretam o que os algoritmos descobriram.
require __DIR__ . '/../config/ia.php';
// Carrega o interpretador que entende a pergunta e escolhe a analise correspondente.
require __DIR__ . '/../config/assistente.php';

// Informa ao navegador que a resposta e um JSON codificado em UTF-8.
header('Content-Type: application/json; charset=utf-8');

try {
    // Le a pergunta enviada pelo formulario da aba Assistente.
    $pergunta = (string) ($_POST['pergunta'] ?? '');

    // Aceita tambem o envio em JSON, para o caso de a tela passar a usar esse formato no futuro.
    if ($pergunta === '') {
        // Le o corpo bruto do pedido, que e onde o JSON chega.
        $corpo = file_get_contents('php://input');

        // Tenta interpretar o corpo como JSON, sem quebrar quando ele vier vazio ou invalido.
        $dados = json_decode((string) $corpo, true);

        // Aproveita o valor apenas quando o corpo realmente era um JSON com a chave esperada.
        if (is_array($dados)) {
            $pergunta = (string) ($dados['pergunta'] ?? '');
        }
    }

    // Limita o tamanho do texto recebido, para que ninguem consiga sobrecarregar o interpretador.
    // Confere a extensao mbstring antes de usar, como o restante do sistema ja faz.
    $pergunta = function_exists('mb_substr')
        ? mb_substr(trim($pergunta), 0, 300, 'UTF-8')
        : substr(trim($pergunta), 0, 300);

    // Descobre o assunto da pergunta e monta a resposta com os dados do sistema.
    $resposta = assistenteResponder($pdo, $pergunta);

    // Devolve apenas o que a tela precisa desenhar.
    echo json_encode([
        'ok'        => true,
        'titulo'    => $resposta['titulo'] ?? '',
        'resposta'  => $resposta['resposta'] ?? '',
        'itens'     => $resposta['itens'] ?? [],
        'sugestoes' => $resposta['sugestoes'] ?? [],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    // Responde com o codigo de erro do servidor para que o Dashboard possa avisar o usuario.
    http_response_code(500);

    // Devolve uma mensagem curta, sem expor detalhes internos do sistema na tela.
    echo json_encode([
        'ok'       => false,
        'mensagem' => 'Não foi possível consultar os dados agora.',
    ], JSON_UNESCAPED_UNICODE);
}
