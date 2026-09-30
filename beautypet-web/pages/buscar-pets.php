<?php
// Exemplo de inclusão dentro do arquivo busca-registros.php

$tipo = $_GET['tipo'] ?? '';

if ($tipo === 'pet') {
    $q = trim($_GET['q'] ?? '');

    if (mb_strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    // Consulta pets pelo nome do pet ou nome do tutor vinculado
    $stmt = $pdo->prepare("
        SELECT 
            p.id AS pet_id,
            p.nome AS pet_nome,
            t.nome AS tutor_nome
        FROM pets p
        INNER JOIN tutores t ON t.id = p.tutor_id
        WHERE p.nome LIKE :q OR t.nome LIKE :q
        ORDER BY p.nome ASC
        LIMIT 15
    ");
    
    $stmt->execute([':q' => '%' . $q . '%']);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formata o retorno JSON para exibir no seletor
    $resultados = array_map(function($pet) {
        return [
            'id'    => $pet['pet_id'],
            'label' => $pet['pet_nome'] . ' (Tutor: ' . $pet['tutor_nome'] . ')'
        ];
    }, $pets);

    header('Content-Type: application/json');
    echo json_encode($resultados);
    exit;
}