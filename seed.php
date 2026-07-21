<?php
declare(strict_types=1);

/**
 * Popula o banco com alguns produtos de exemplo.
 * Rode uma vez:  php seed.php
 */

require __DIR__ . '/db.php';

$pdo = getConexao();
$pdo->exec('DELETE FROM produtos');

$exemplos = [
    ['Notebook Dell',    3500.00, 12],
    ['Mouse Logitech',   120.50,  80],
    ['Teclado Mecânico', 320.00,  35],
    ['Monitor 24"',      950.00,  20],
    ['Webcam Full HD',   210.00,  15],
];

$stmt = $pdo->prepare('INSERT INTO produtos (nome, preco, estoque) VALUES (?, ?, ?)');
foreach ($exemplos as $p) {
    $stmt->execute($p);
}

echo count($exemplos) . " produtos inseridos com sucesso.\n";
