<?php
declare(strict_types=1);

/**
 * Testes da API de Produtos.
 *
 * Sem framework e sem dependência: o script sobe o servidor embutido do PHP
 * num banco SQLite descartável (via PRODUTOS_DB), faz requisições HTTP de
 * verdade contra ele e derruba tudo no fim. É a mesma API que o usuário
 * consome — roteamento, códigos de status e JSON incluídos.
 *
 * Rodar:  php test_api.php
 */

$total = 0;
$falhas = [];

function checar(string $nome, callable $caso): void
{
    global $total, $falhas;
    $total++;
    try {
        $caso();
        echo "  ok   $nome\n";
    } catch (Throwable $e) {
        $falhas[] = "$nome — {$e->getMessage()}";
        echo "  FALHA $nome\n       {$e->getMessage()}\n";
    }
}

function igual(mixed $esperado, mixed $obtido, string $ctx = ''): void
{
    if ($esperado !== $obtido) {
        $e = var_export($esperado, true);
        $o = var_export($obtido, true);
        throw new RuntimeException(trim("$ctx esperava $e, veio $o"));
    }
}

function verdade(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

/** Faz uma requisição e devolve [status, corpo decodificado]. */
function pedir(string $metodo, string $rota, ?array $corpo = null): array
{
    global $BASE;

    $opcoes = [
        'http' => [
            'method'        => $metodo,
            'header'        => "Content-Type: application/json\r\n",
            'ignore_errors' => true,   // queremos ler o corpo dos 4xx também
            'timeout'       => 10,
        ],
    ];
    if ($corpo !== null) {
        $opcoes['http']['content'] = json_encode($corpo, JSON_UNESCAPED_UNICODE);
    }

    $bruto = @file_get_contents($BASE . $rota, false, stream_context_create($opcoes));

    $status = 0;
    foreach ($http_response_header ?? [] as $linha) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linha, $m)) {
            $status = (int) $m[1];
        }
    }
    return [$status, json_decode((string) $bruto, true)];
}

function produtoValido(array $extra = []): array
{
    return $extra + ['nome' => 'Headset Gamer', 'preco' => 289.90, 'estoque' => 7];
}

// ---------------------------------------------------------------------------
// Sobe o servidor embutido num banco temporário
// ---------------------------------------------------------------------------
$bancoTmp = tempnam(sys_get_temp_dir(), 'produtos_') . '.db';
$porta    = random_int(8300, 8999);
$BASE     = "http://127.0.0.1:$porta";
$raiz     = __DIR__;

// O servidor embutido registra cada requisição no stderr. Se isso for para
// um pipe que ninguém lê, o buffer enche e o servidor trava no meio da
// suíte — por isso o log vai para arquivo, não para pipe.
$logServidor = tempnam(sys_get_temp_dir(), 'srv_') . '.log';

$servidor = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$porta", '-t', $raiz, $raiz . '/index.php'],
    [1 => ['file', $logServidor, 'a'], 2 => ['file', $logServidor, 'a']],
    $canos,
    $raiz,
    ['PRODUTOS_DB' => $bancoTmp] + getenv()
);

if (!is_resource($servidor)) {
    fwrite(STDERR, "Não consegui subir o servidor embutido.\n");
    exit(1);
}

// Espera a porta responder (até ~10s).
$pronto = false;
for ($i = 0; $i < 100; $i++) {
    $s = @fsockopen('127.0.0.1', $porta, $errno, $errstr, 0.2);
    if ($s) { fclose($s); $pronto = true; break; }
    usleep(100_000);
}

$encerrar = function () use ($servidor, $bancoTmp, $logServidor) {
    proc_terminate($servidor);
    proc_close($servidor);
    @unlink($bancoTmp);
    @unlink($logServidor);
};

if (!$pronto) {
    fwrite(STDERR, "O servidor não respondeu na porta $porta.\n");
    $encerrar();
    exit(1);
}

// Popula o banco de teste com os exemplos do seed.
putenv("PRODUTOS_DB=$bancoTmp");
require __DIR__ . '/db.php';
$pdo = getConexao();
$pdo->exec('DELETE FROM produtos');
$stmt = $pdo->prepare('INSERT INTO produtos (nome, preco, estoque) VALUES (?, ?, ?)');
foreach ([['Notebook Dell', 3500.00, 12], ['Mouse Logitech', 120.50, 80]] as $p) {
    $stmt->execute($p);
}

echo "\nAPI de Produtos — testes\n\n";

// ---------------------------------------------------------------------------
echo "Rotas e listagem\n";

checar('GET / descreve a API', function () {
    [$st, $b] = pedir('GET', '/');
    igual(200, $st, 'status');
    igual('Produtos', $b['api']);
    verdade(is_array($b['endpoints']) && count($b['endpoints']) === 5, 'esperava 5 endpoints');
});

checar('GET /produtos lista o que está no banco', function () {
    [$st, $b] = pedir('GET', '/produtos');
    igual(200, $st, 'status');
    igual(2, count($b), 'quantidade');
    igual('Notebook Dell', $b[0]['nome']);
});

checar('rota inexistente devolve 404', function () {
    [$st, $b] = pedir('GET', '/nao-existe');
    igual(404, $st, 'status');
    verdade(isset($b['erro']), 'esperava mensagem de erro');
});

checar('método não permitido devolve 405', function () {
    igual(405, pedir('PATCH', '/produtos')[0], 'status');
});

// ---------------------------------------------------------------------------
echo "\nCRUD\n";

checar('POST cria e devolve 201 com id', function () {
    [$st, $b] = pedir('POST', '/produtos', produtoValido());
    igual(201, $st, 'status');
    verdade(isset($b['id']) && $b['id'] > 0, 'esperava um id');
    igual('Headset Gamer', $b['nome']);
});

checar('GET /produtos/{id} devolve o produto criado', function () {
    $id = pedir('POST', '/produtos', produtoValido(['nome' => 'Cadeira']))[1]['id'];
    [$st, $b] = pedir('GET', "/produtos/$id");
    igual(200, $st, 'status');
    igual('Cadeira', $b['nome']);
});

checar('GET de id inexistente devolve 404', function () {
    igual(404, pedir('GET', '/produtos/999999')[0], 'status');
});

checar('PUT atualiza os campos', function () {
    $id = pedir('POST', '/produtos', produtoValido())[1]['id'];
    [$st, $b] = pedir('PUT', "/produtos/$id", produtoValido(['nome' => 'Renomeado', 'estoque' => 99]));
    igual(200, $st, 'status');
    igual('Renomeado', $b['nome']);
    igual(99, pedir('GET', "/produtos/$id")[1]['estoque'], 'estoque persistido');
});

checar('PUT em id inexistente devolve 404', function () {
    igual(404, pedir('PUT', '/produtos/999999', produtoValido())[0], 'status');
});

checar('DELETE devolve 204 e o produto some', function () {
    $id = pedir('POST', '/produtos', produtoValido())[1]['id'];
    igual(204, pedir('DELETE', "/produtos/$id")[0], 'status do delete');
    igual(404, pedir('GET', "/produtos/$id")[0], 'status depois de remover');
});

checar('DELETE repetido devolve 404 na segunda vez', function () {
    $id = pedir('POST', '/produtos', produtoValido())[1]['id'];
    pedir('DELETE', "/produtos/$id");
    igual(404, pedir('DELETE', "/produtos/$id")[0], 'status');
});

// ---------------------------------------------------------------------------
echo "\nValidação\n";

checar('nome vazio é recusado com 400', function () {
    [$st, $b] = pedir('POST', '/produtos', produtoValido(['nome' => '   ']));
    igual(400, $st, 'status');
    verdade(isset($b['erros']), 'esperava a lista de erros');
});

checar('preço negativo é recusado', function () {
    igual(400, pedir('POST', '/produtos', produtoValido(['preco' => -1]))[0], 'status');
});

checar('preço não numérico é recusado', function () {
    igual(400, pedir('POST', '/produtos', produtoValido(['preco' => 'caro']))[0], 'status');
});

checar('estoque negativo é recusado', function () {
    igual(400, pedir('POST', '/produtos', produtoValido(['estoque' => -5]))[0], 'status');
});

checar('corpo vazio é recusado', function () {
    igual(400, pedir('POST', '/produtos', [])[0], 'status');
});

checar('payload inválido não cria registro', function () {
    $antes = count(pedir('GET', '/produtos')[1]);
    pedir('POST', '/produtos', produtoValido(['preco' => -3]));
    igual($antes, count(pedir('GET', '/produtos')[1]), 'total de produtos');
});

// ---------------------------------------------------------------------------
echo "\nSegurança — prepared statements\n";

checar('id com SQL injection não derruba a tabela', function () {
    // A rota casa com \d+, então isto nem chega ao banco: vira 404 de rota.
    $antes = count(pedir('GET', '/produtos')[1]);
    igual(404, pedir('GET', "/produtos/1;%20DROP%20TABLE%20produtos")[0], 'status');
    igual($antes, count(pedir('GET', '/produtos')[1]), 'a tabela continua de pé');
});

checar('nome com comando SQL é gravado como texto, não executado', function () {
    $ataque = "'; DROP TABLE produtos; --";
    $antes  = count(pedir('GET', '/produtos')[1]);

    $id = pedir('POST', '/produtos', produtoValido(['nome' => $ataque]))[1]['id'];

    // Se o parâmetro não estivesse ligado, a tabela teria sumido aqui.
    [$st, $b] = pedir('GET', "/produtos/$id");
    igual(200, $st, 'status');
    igual($ataque, $b['nome'], 'o nome voltou literal');
    igual($antes + 1, count(pedir('GET', '/produtos')[1]), 'total de produtos');
});

checar('aspas e acentos sobrevivem à ida e volta', function () {
    $nome = 'Monitor 24" — José D\'Ávila';
    $id = pedir('POST', '/produtos', produtoValido(['nome' => $nome]))[1]['id'];
    igual($nome, pedir('GET', "/produtos/$id")[1]['nome'], 'nome');
});

checar('UPDATE com aspas no nome não quebra a query', function () {
    $id = pedir('POST', '/produtos', produtoValido())[1]['id'];
    $nome = "Teclado' OR '1'='1";
    igual(200, pedir('PUT', "/produtos/$id", produtoValido(['nome' => $nome]))[0], 'status');
    igual($nome, pedir('GET', "/produtos/$id")[1]['nome'], 'nome');
});

// ---------------------------------------------------------------------------
$encerrar();

echo "\n" . str_repeat('-', 52) . "\n";
if ($falhas) {
    echo count($falhas) . " de $total teste(s) falharam:\n";
    foreach ($falhas as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "$total testes, todos passaram.\n";
exit(0);
