<?php
/**
 * ARQUIVO: index.php
 * RESPONSABILIDADE: Página inicial do sistema web.
 * Carrega a estrutura principal e apresenta a área inicial do sistema Beauty Pet.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Dashboard';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'dashboard';

// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page dashboard-page">
    <h1>Dashboard</h1>
    <p>Cadastros de tutores, pets, fichas e usuários conectados ao MySQL.</p>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
