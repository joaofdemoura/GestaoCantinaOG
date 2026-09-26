<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/db.php';

const INTERVALOS = ['09:00', '15:30'];
const VALIDADE_SESSAO = '+7 days';

function responder($dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function erro(string $mensagem, int $status = 400): void
{
    throw new HttpError($mensagem, $status);
}

/* ----------------------------------------------------------------------------
   Sessão
   ------------------------------------------------------------------------- */

function tokenRecebido(): string
{
    $cabecalho = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($cabecalho === '' && function_exists('getallheaders')) {
        $cabecalho = array_change_key_case(getallheaders())['authorization'] ?? '';
    }
    return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $cabecalho, $m) ? strtolower($m[1]) : '';
}

function usuarioAtual(): ?array
{
    static $usuario = false;
    if ($usuario === false) {
        $usuario = null;
        $token = tokenRecebido();
        if ($token !== '') {
            $st = db()->prepare('SELECT token, tipo, cpf FROM sessao WHERE token IN (?, ?) AND expira_em > NOW()');
            $st->execute([$token, hash('sha256', $token)]);
            $usuario = $st->fetch() ?: null;
        }
    }
    return $usuario;
}

function exigir(string ...$tipos): array
{
    $usuario = usuarioAtual();
    if (!$usuario) {
        erro('Faça login para continuar', 401);
    }
    if ($tipos && !in_array($usuario['tipo'], $tipos, true)) {
        erro('Acesso não permitido para este usuário', 403);
    }
    return $usuario;
}

function login(array $corpo): array
{
    limitarLogin();
    $tipo = $corpo['type'] ?? '';
    $login = trim((string) ($corpo['login'] ?? ''));
    $senha = (string) ($corpo['password'] ?? '');
    if ($login === '' || $senha === '') {
        erro('Informe login e senha');
    }

    $consultas = [
        'admin' => 'SELECT cpf_admin AS cpf, usuario_adm AS nome, senha FROM user_admin WHERE usuario_adm = ? OR cpf_admin = ?',
        'pai'   => 'SELECT cpf_pai AS cpf, usuario_pais AS nome, senha FROM user_pais WHERE cpf_pai = ? OR cpf_pai = ?',
        'filho' => 'SELECT cpf_filho AS cpf, usuario_filho AS nome, senha FROM user_filho WHERE cpf_filho = ? OR cpf_filho = ?',
    ];
    if (!isset($consultas[$tipo])) {
        erro('Tipo de usuário inválido');
    }

    $cpf = preg_replace('/\D/', '', $login);
    $st = db()->prepare($consultas[$tipo]);
    $st->execute([$login, $cpf === '' ? 0 : $cpf]);
    $usuario = $st->fetch();
    if (!$usuario || !verificarSenhaPhp($senha, $usuario['senha'])) {
        erro('Login ou senha incorretos', 401);
    }

    db()->prepare('DELETE FROM sessao WHERE expira_em <= NOW()')->execute();
    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO sessao (token, tipo, cpf, expira_em) VALUES (?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $tipo, $usuario['cpf'], date('Y-m-d H:i:s', strtotime(VALIDADE_SESSAO))]);

    return ['token' => $token, 'type' => $tipo, 'id' => (int) $usuario['cpf'], 'name' => $usuario['nome']];
}

/* Filhos que o usuário logado pode ver: todos (admin), os seus (pai) ou ele mesmo (filho). */
function filhosPermitidos(array $usuario): ?array
{
    if ($usuario['tipo'] === 'admin') {
        return null;
    }
    if ($usuario['tipo'] === 'filho') {
        return [(int) $usuario['cpf']];
    }
    $st = db()->prepare('SELECT cpf_filho FROM user_filho WHERE cpf_pai = ?');
    $st->execute([$usuario['cpf']]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function exigirFilho(array $usuario, int $cpfFilho): void
{
    $permitidos = filhosPermitidos($usuario);
    if ($permitidos !== null && !in_array($cpfFilho, $permitidos, true)) {
        erro('Aluno não encontrado', 404);
    }
}

/* ----------------------------------------------------------------------------
   Consultas
   ------------------------------------------------------------------------- */

function listaIn(array $valores): string
{
    return implode(',', array_fill(0, count($valores), '?'));
}

function produtos(bool $soDisponiveis = false): array
{
    $sql = 'SELECT * FROM cardapio WHERE excluido = 0' . ($soDisponiveis ? ' AND disponivel = 1' : '') . ' ORDER BY nome_produto';
    return array_map(fn($p) => [
        'id'          => (int) $p['id_produto'],
        'name'        => $p['nome_produto'],
        'description' => $p['ds_produto'],
        'category'    => $p['categoria'],
        'flavor'      => $p['sabor'],
        'time'        => hhmm($p['hora']),
        'price'       => centavos($p['vl_produto']),
        'image'       => $p['imagem'],
        'available'   => (bool) $p['disponivel'],
    ], db()->query($sql)->fetchAll());
}

function alunos(?array $cpfs = null): array
{
    if ($cpfs === []) {
        return [];
    }
    $sql = 'SELECT f.*, p.usuario_pais FROM user_filho f LEFT JOIN user_pais p ON p.cpf_pai = f.cpf_pai';
    if ($cpfs !== null) {
        $sql .= ' WHERE f.cpf_filho IN (' . listaIn($cpfs) . ')';
    }
    $st = db()->prepare($sql . ' ORDER BY f.usuario_filho');
    $st->execute($cpfs ?? []);
    return array_map(fn($f) => [
        'id'       => (int) $f['cpf_filho'],
        'name'     => $f['usuario_filho'],
        'age'      => (int) $f['idade_filho'],
        'class'    => $f['turma'],
        'allergy'  => $f['restricao_alimentar'],
        'balance'  => centavos($f['saldo']),
        'parentId' => $f['cpf_pai'] === null ? null : (int) $f['cpf_pai'],
        'parent'   => $f['usuario_pais'] ?? '',
    ], $st->fetchAll());
}

function pedidos(array $filtros = []): array
{
    $sql = 'SELECT * FROM pedido WHERE 1 = 1';
    $params = [];
    if (!empty($filtros['id'])) {
        $sql .= ' AND id_pedido = ?';
        $params[] = $filtros['id'];
    }
    if (!empty($filtros['data'])) {
        $sql .= ' AND dt_pedido = ?';
        $params[] = $filtros['data'];
    }
    if (!empty($filtros['intervalo'])) {
        $sql .= ' AND horario_retirada = ?';
        $params[] = $filtros['intervalo'];
    }
    if (isset($filtros['alunos'])) {
        if ($filtros['alunos'] === []) {
            return [];
        }
        $sql .= ' AND cpf_filho IN (' . listaIn($filtros['alunos']) . ')';
        array_push($params, ...$filtros['alunos']);
    }
    $st = db()->prepare($sql . ' ORDER BY dt_pedido, id_pedido');
    $st->execute($params);
    $pedidos = $st->fetchAll();
    if (!$pedidos) {
        return [];
    }

    $ids = array_column($pedidos, 'id_pedido');
    $st = db()->prepare(
        'SELECT i.id_pedido, i.id_produto, i.quantidade, i.vl_unitario, i.observacao, c.nome_produto
           FROM pedido_item i
           JOIN cardapio c ON c.id_produto = i.id_produto
          WHERE i.id_pedido IN (' . listaIn($ids) . ')
          ORDER BY i.id_item'
    );
    $st->execute($ids);
    $itens = [];
    foreach ($st->fetchAll() as $i) {
        $itens[$i['id_pedido']][] = [
            'id'    => (int) $i['id_produto'],
            'name'  => $i['nome_produto'],
            'qty'   => (int) $i['quantidade'],
            'price' => centavos($i['vl_unitario']),
            'observation' => $i['observacao'],
        ];
    }

    return array_map(function ($p) use ($itens) {
        $lista = $itens[$p['id_pedido']] ?? [];
        return [
            'id'            => (int) $p['id_pedido'],
            'student'       => (int) $p['cpf_filho'],
            'date'          => $p['dt_pedido'],
            'interval'      => hhmm($p['horario_retirada']),
            'status'        => $p['status'],
            'paymentMethod' => $p['forma_pagamento'],
            'paymentStatus' => $p['pagamento_status'],
            'total'         => array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $lista)),
            'items'         => $lista,
        ];
    }, $pedidos);
}

function extrato(int $cpfFilho): array
{
    $st = db()->prepare('SELECT * FROM extrato WHERE cpf_filho = ? ORDER BY dt_extrato DESC, horario DESC, id_extrato DESC');
    $st->execute([$cpfFilho]);
    return array_map(fn($e) => [
        'id'     => (int) $e['id_extrato'],
        'date'   => $e['dt_extrato'],
        'time'   => hhmm($e['horario']),
        'type'   => $e['gasto'] < 0 ? 'estorno' : 'compra',
        'amount' => centavos($e['gasto']),
        'order'  => $e['id_pedido'] === null ? null : (int) $e['id_pedido'],
    ], $st->fetchAll());
}

/* ----------------------------------------------------------------------------
   Escrita
   ------------------------------------------------------------------------- */

function dadosProduto(array $corpo): array
{
    $nome = trim((string) ($corpo['name'] ?? ''));
    $preco = (int) ($corpo['price'] ?? 0);
    $hora = (string) ($corpo['time'] ?? '');
    if ($nome === '' || mb_strlen($nome) > 100) {
        erro('Nome inválido');
    }
    if ($preco <= 0) {
        erro('Preço inválido');
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        erro('Horário inválido');
    }
    return [
        $nome,
        trim((string) ($corpo['description'] ?? '')),
        reais($preco),
        $hora,
        trim((string) ($corpo['flavor'] ?? '')),
        trim((string) ($corpo['category'] ?? '')) ?: 'Outros',
    ];
}

/* Cria o pedido, desconta o saldo e registra no extrato numa única transação. */
function criarPedido(array $usuario, array $corpo): array { return pedidoUnificado($usuario, $corpo); }

function cancelarPedido(int $idPedido): array { return cancelarUnificado($idPedido); }

/* Apaga o item se ele nunca foi pedido; se já foi, só o marca como excluído para preservar o histórico. */
function excluirProduto(int $id): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM carrinho WHERE id_produto = ?')->execute([$id]);
        $st = $pdo->prepare('SELECT 1 FROM pedido_item WHERE id_produto = ? LIMIT 1');
        $st->execute([$id]);
        if ($st->fetch()) {
            $pdo->prepare('UPDATE cardapio SET excluido = 1, disponivel = 0 WHERE id_produto = ?')->execute([$id]);
            $modo = 'arquivado';
        } else {
            $pdo->prepare('DELETE FROM cardapio WHERE id_produto = ?')->execute([$id]);
            $modo = 'apagado';
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return ['ok' => true, 'mode' => $modo];
}

function exigirProduto(int $id): void
{
    $st = db()->prepare('SELECT 1 FROM cardapio WHERE id_produto = ? AND excluido = 0');
    $st->execute([$id]);
    if (!$st->fetch()) {
        erro('Produto não encontrado', 404);
    }
}

/* ----------------------------------------------------------------------------
   Rotas
   ------------------------------------------------------------------------- */

require __DIR__ . '/integration.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rota = preg_match('#/api/(.*)$#', $caminho, $m) ? trim($m[1], '/') : '';
$rota = preg_replace('#^index\.php/?#', '', $rota);
$partes = $rota === '' ? [] : explode('/', $rota);
$recurso = $partes[0] ?? '';
$id = isset($partes[1]) ? (int) $partes[1] : null;
$rawBody = file_get_contents('php://input');
$corpo = json_decode($rawBody, true) ?? [];

try {
    if (strlen($rawBody)>32768 || ($rawBody!=='' && (json_last_error()!==JSON_ERROR_NONE || !is_array($corpo)))) erro('JSON inválido.');
    rotasAndroid($rota, $metodo, $corpo);
    if ($metodo === 'POST' && $recurso === 'login') {
        responder(login($corpo));
    }

    if ($metodo === 'POST' && $recurso === 'logout') {
        exigir();
        consulta('DELETE FROM sessao WHERE token IN (?, ?)', [tokenRecebido(), hash('sha256', tokenRecebido())]);
        responder(['ok' => true]);
    }

    if ($metodo === 'GET' && $recurso === 'me') {
        $u = exigir();
        $tabelas = [
            'admin' => 'SELECT usuario_adm FROM user_admin WHERE cpf_admin = ?',
            'pai'   => 'SELECT usuario_pais FROM user_pais WHERE cpf_pai = ?',
            'filho' => 'SELECT usuario_filho FROM user_filho WHERE cpf_filho = ?',
        ];
        $st = db()->prepare($tabelas[$u['tipo']]);
        $st->execute([$u['cpf']]);
        $filhos = filhosPermitidos($u);
        responder([
            'type'     => $u['tipo'],
            'id'       => (int) $u['cpf'],
            'name'     => $st->fetchColumn(),
            'students' => $filhos === null ? [] : alunos($filhos),
        ]);
    }

    if ($metodo === 'GET' && $recurso === 'estado') {
        exigir('admin');
        responder(['products' => produtos(), 'students' => alunos(), 'orders' => pedidos()]);
    }

    if ($metodo === 'GET' && $recurso === 'produtos' && !$id) {
        $u = exigir();
        responder(produtos($u['tipo'] !== 'admin'));
    }

    if ($metodo === 'POST' && $recurso === 'produtos' && !$id) {
        exigir('admin');
        $st = db()->prepare(
            'INSERT INTO cardapio (nome_produto, ds_produto, vl_produto, hora, sabor, categoria, disponivel)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $st->execute(dadosProduto($corpo));
        responder(['id' => (int) db()->lastInsertId()], 201);
    }

    if ($metodo === 'PUT' && $recurso === 'produtos' && $id) {
        exigir('admin');
        exigirProduto($id);
        $st = db()->prepare(
            'UPDATE cardapio
                SET nome_produto = ?, ds_produto = ?, vl_produto = ?, hora = ?, sabor = ?, categoria = ?
              WHERE id_produto = ?'
        );
        $st->execute([...dadosProduto($corpo), $id]);
        responder(['ok' => true]);
    }

    if ($metodo === 'PATCH' && $recurso === 'produtos' && $id) {
        exigir('admin');
        if (!isset($corpo['available']) || !is_bool($corpo['available'])) {
            erro('Informe available como true ou false');
        }
        exigirProduto($id);
        db()->prepare('UPDATE cardapio SET disponivel = ? WHERE id_produto = ?')->execute([(int) $corpo['available'], $id]);
        responder(['ok' => true]);
    }

    if ($metodo === 'DELETE' && $recurso === 'produtos' && $id) {
        exigir('admin');
        exigirProduto($id);
        responder(excluirProduto($id));
    }

    if ($metodo === 'GET' && $recurso === 'alunos' && !$id) {
        $u = exigir();
        responder(alunos(filhosPermitidos($u)));
    }

    if ($metodo === 'GET' && $recurso === 'alunos' && $id && ($partes[2] ?? '') === 'extrato') {
        $u = exigir();
        exigirFilho($u, $id);
        responder(extrato($id));
    }

    if ($metodo === 'GET' && $recurso === 'pedidos' && !$id) {
        $u = exigir();
        $data = $_GET['data'] ?? null;
        $intervalo = $_GET['intervalo'] ?? null;
        if ($data && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            erro('Data inválida, use AAAA-MM-DD');
        }
        if ($intervalo && !preg_match('/^\d{2}:\d{2}$/', $intervalo)) {
            erro('Intervalo inválido, use HH:MM');
        }
        $filtros = ['data' => $data, 'intervalo' => $intervalo];
        $filhos = filhosPermitidos($u);
        if ($filhos !== null) {
            $filtros['alunos'] = $filhos;
        }
        responder(pedidos($filtros));
    }

    if ($metodo === 'POST' && $recurso === 'pedidos' && !$id) {
        $u = exigir('pai', 'filho');
        responder(criarPedido($u, $corpo), 201);
    }

    if ($metodo === 'PATCH' && $recurso === 'pedidos' && $id) {
        exigir('admin');
        if (($corpo['status'] ?? '') === 'Cancelado') {
            responder(cancelarPedido($id));
        }
        $proximo = ['Em preparo' => 'Pronto', 'Pronto' => 'Entregue'];
        $st = db()->prepare('SELECT status FROM pedido WHERE id_pedido = ?');
        $st->execute([$id]);
        $atual = $st->fetchColumn();
        if ($atual === false) {
            erro('Pedido não encontrado', 404);
        }
        $novo = $corpo['status'] ?? '';
        if (($proximo[$atual] ?? null) !== $novo) {
            erro("Não é possível mudar de \"$atual\" para \"$novo\"", 409);
        }
        $change = consulta('UPDATE pedido SET status = ? WHERE id_pedido = ? AND status = ?', [$novo, $id, $atual]);
        if (!$change->rowCount()) erro('O pedido foi alterado. Atualize a tela.',409);
        responder(['ok' => true, 'status' => $novo]);
    }

    erro('Rota não encontrada', 404);
} catch (HttpError $e) {
    if (db()->inTransaction()) db()->rollBack();
    responder(['erro'=>$e->getMessage(),'error'=>$e->getMessage()],$e->status);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(['erro'=>'Erro no banco. Verifique o MySQL do Laragon.','error'=>'Erro no banco. Verifique o MySQL do Laragon.'],500);
}
