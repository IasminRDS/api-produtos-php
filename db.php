<?php
declare(strict_types=1);

/**
 * Conexão com o banco SQLite via PDO e criação da tabela.
 * SQLite não precisa de servidor: o banco é o arquivo produtos.db.
 */
function getConexao(): PDO
{
    // Por padrão o banco é o produtos.db ao lado deste arquivo. A variável
    // de ambiente PRODUTOS_DB permite apontar para outro caminho — é o que
    // os testes usam para rodar contra um banco descartável.
    $arquivo = getenv('PRODUTOS_DB') ?: __DIR__ . '/produtos.db';

    $pdo = new PDO('sqlite:' . $arquivo);

    // Erros como exceções + resultados como array associativo
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS produtos (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            nome    TEXT    NOT NULL,
            preco   REAL    NOT NULL,
            estoque INTEGER NOT NULL DEFAULT 0
        )
    ');

    return $pdo;
}
