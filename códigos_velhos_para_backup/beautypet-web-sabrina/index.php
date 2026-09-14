<?php
// Importa o arquivo de configuração do sistema.
// O __DIR__ representa o diretório onde este arquivo PHP está localizado.
// Adicionada a barra '/' antes de 'config/config.php' para montar o caminho correto.
require __DIR__ . '/config/config.php';

// Define o título que será utilizado para identificar a página.
$tituloPagina = 'Dashboard';

// Define qual página está ativa no sistema.
// Essa informação pode ser utilizada pelo menu para destacar o Dashboard.
$paginaAtiva = 'dashboard';

// Importa o arquivo responsável pelo topo da página.
require __DIR__ . '/includes/topo.php';
?>

<!--
    Container principal da página Dashboard.
    As classes "page" e "dashboard-page" permitem aplicar estilos CSS
    específicos para essa área.
-->
<div class="page dashboard-page">

    <!-- Título principal da página -->
    <h1>Dashboard</h1>

    <!-- Descrição das informações disponíveis no sistema -->
    <p>Cadastros de tutores, pets, fichas e usuários conectados ao MySQL.</p>

</div>

<?php
// Importa o arquivo responsável pelo rodapé da página.
require __DIR__ . '/includes/rodape.php';
?>