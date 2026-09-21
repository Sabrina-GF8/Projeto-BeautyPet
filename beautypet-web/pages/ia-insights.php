<?php
/**
 * ARQUIVO: pages/ia-insights.php
 * RESPONSABILIDADE: Devolver a analise inteligente em formato JSON para o Dashboard.
 *
 * Esta pagina nao tem visual proprio. Ela existe para que o botao "Atualizar analise" do Dashboard
 * possa pedir uma nova leitura dos dados sem recarregar a tela inteira, evitando que o usuario
 * fique esperando enquanto as consultas e a interpretacao acontecem.
 */

// Carrega o arquivo de configuracao do sistema, incluindo a conexao com o banco de dados.
require __DIR__ . '/../config/config.php';
// Carrega as funcoes que consultam e agrupam os dados das analises.
require __DIR__ . '/../config/analise.php';
// Carrega os algoritmos de aprendizado de maquina e mineracao de dados.
require __DIR__ . '/../config/ia-motor.php';
// Carrega as funcoes que interpretam o que os algoritmos descobriram.
require __DIR__ . '/../config/ia.php';

// Informa ao navegador que a resposta e um JSON codificado em UTF-8.
header('Content-Type: application/json; charset=utf-8');

// Verifica se o pedido exige descartar a analise guardada e refazer tudo do zero.
$forcarAtualizacao = ($_GET['atualizar'] ?? '') === '1';

/**
 * Deixa passar apenas o que a tela deve mostrar: o titulo e a conclusao.
 *
 * As analises guardam tambem a evidencia numerica e o nome do algoritmo que produziu
 * cada conclusao. Isso serve para conferencia de quem programa e por isso nao vai para
 * a tela: para quem atende, o sistema fala do movimento da casa, e nao de si mesmo.
 */
function apenasAConclusao(array $itens): array
{
    // Percorre os itens devolvendo somente os campos que a tela desenha.
    return array_map(
        static fn(array $item): array => [
            'tipo'   => $item['tipo'] ?? 'neutro',
            'titulo' => $item['titulo'] ?? '',
            'texto'  => $item['texto'] ?? '',
        ],
        $itens
    );
}

try {
    // Executa as analises e gera as observacoes, respeitando o pedido de atualizacao.
    $analise = gerarAnaliseInteligente($pdo, $forcarAtualizacao);

    // Devolve apenas o que a tela precisa desenhar, sem repetir a base de dados inteira.
    echo json_encode([
        'ok'          => true,
        'resumo'      => $analise['resumo'] ?? '',
        'observacoes' => apenasAConclusao($analise['observacoes'] ?? []),
        'promocoes'   => apenasAConclusao($analise['promocoes'] ?? []),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    // Responde com o codigo de erro do servidor para que o Dashboard possa avisar o usuario.
    http_response_code(500);

    // Devolve uma mensagem curta, sem expor detalhes internos do sistema na tela.
    echo json_encode([
        'ok'       => false,
        'mensagem' => 'Não foi possível gerar a análise agora.',
    ], JSON_UNESCAPED_UNICODE);
}
