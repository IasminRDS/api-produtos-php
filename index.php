<?php
declare(strict_types=1);

/**
 * API REST de Produtos — PHP puro (sem framework).
 *
 * Rotas:
 *   GET    /produtos        lista todos
 *   GET    /produtos/{id}   retorna um
 *   POST   /produtos        cria
 *   PUT    /produtos/{id}   atualiza
 *   DELETE /produtos/{id}   remove
 *
 * Rode com:  php -S localhost:8000 index.php
 */

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

/** Envia uma resposta JSON e encerra. */
function resposta(mixed $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Lê e decodifica o corpo JSON da requisição. */
function corpoJson(): array
{
    $bruto = file_get_contents('php://input');
    $dados = json_decode($bruto ?: '[]', true);
    return is_array($dados) ? $dados : [];
}

/** Valida os campos de um produto. Retorna a lista de erros. */
function validar(array $d): array
{
    $erros = [];
    if (!isset($d['nome']) || trim((string) $d['nome']) === '') {
        $erros[] = "O campo 'nome' é obrigatório.";
    }
    if (!isset($d['preco']) || !is_numeric($d['preco']) || (float) $d['preco'] < 0) {
        $erros[] = "O campo 'preco' deve ser um número maior ou igual a zero.";
    }
    if (isset($d['estoque']) && (!is_numeric($d['estoque']) || (int) $d['estoque'] < 0)) {
        $erros[] = "O campo 'estoque' deve ser um inteiro maior ou igual a zero.";
    }
    return $erros;
}

$pdo     = getConexao();
$metodo  = $_SERVER['REQUEST_METHOD'];
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ---------- /produtos/{id} ----------
if (preg_match('#^/produtos/(\d+)$#', $caminho, $m)) {
    $id = (int) $m[1];

    $stmt = $pdo->prepare('SELECT * FROM produtos WHERE id = ?');
    $stmt->execute([$id]);
    $produto = $stmt->fetch();

    if ($metodo === 'GET') {
        $produto ? resposta($produto) : resposta(['erro' => 'Produto não encontrado.'], 404);
    }

    if ($metodo === 'PUT') {
        if (!$produto) {
            resposta(['erro' => 'Produto não encontrado.'], 404);
        }
        $d = corpoJson();
        if ($erros = validar($d)) {
            resposta(['erros' => $erros], 400);
        }
        $nome    = trim((string) $d['nome']);
        $preco   = (float) $d['preco'];
        $estoque = (int) ($d['estoque'] ?? 0);

        $stmt = $pdo->prepare('UPDATE produtos SET nome = ?, preco = ?, estoque = ? WHERE id = ?');
        $stmt->execute([$nome, $preco, $estoque, $id]);

        resposta([
            'id'      => $id,
            'nome'    => $nome,
            'preco'   => $preco,
            'estoque' => $estoque,
        ]);
    }

    if ($metodo === 'DELETE') {
        if (!$produto) {
            resposta(['erro' => 'Produto não encontrado.'], 404);
        }
        $pdo->prepare('DELETE FROM produtos WHERE id = ?')->execute([$id]);
        resposta(null, 204);
    }

    resposta(['erro' => 'Método não permitido.'], 405);
}

// ---------- /produtos ----------
if ($caminho === '/produtos') {
    if ($metodo === 'GET') {
        resposta($pdo->query('SELECT * FROM produtos ORDER BY id')->fetchAll());
    }

    if ($metodo === 'POST') {
        $d = corpoJson();
        if ($erros = validar($d)) {
            resposta(['erros' => $erros], 400);
        }
        $stmt = $pdo->prepare('INSERT INTO produtos (nome, preco, estoque) VALUES (?, ?, ?)');
        $stmt->execute([
            trim((string) $d['nome']),
            (float) $d['preco'],
            (int) ($d['estoque'] ?? 0),
        ]);
        $id = (int) $pdo->lastInsertId();
        resposta([
            'id'      => $id,
            'nome'    => trim((string) $d['nome']),
            'preco'   => (float) $d['preco'],
            'estoque' => (int) ($d['estoque'] ?? 0),
        ], 201);
    }

    resposta(['erro' => 'Método não permitido.'], 405);
}

// ---------- / (informações da API) ----------
if ($caminho === '/' || $caminho === '/index.php') {
    resposta([
        'api'       => 'Produtos',
        'versao'    => '1.0',
        'endpoints' => [
            'GET /produtos',
            'GET /produtos/{id}',
            'POST /produtos',
            'PUT /produtos/{id}',
            'DELETE /produtos/{id}',
        ],
    ]);
}

resposta(['erro' => 'Rota não encontrada.'], 404);
