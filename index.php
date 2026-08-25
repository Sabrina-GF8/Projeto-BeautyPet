<?php
require __DIR__ . '/config.php';

$tituloPagina = 'Dashboard';
$paginaAtiva = 'dashboard';

require __DIR__ . '/includes/topo.php';
?>

<div class="page dashboard-page">
    <h1>Dashboard</h1>
    <p>Cadastros de tutores, pets, fichas e usuários conectados ao MySQL.</p>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
