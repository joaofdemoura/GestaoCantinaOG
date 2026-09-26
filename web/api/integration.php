<?php
// Shared business rules for the web panel and the Android adapter.
class HttpError extends RuntimeException {
    public function __construct(string $message, public int $status = 400) { parent::__construct($message); }
}
function consulta(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql); $st->execute($params); return $st;
}
function textoCampo($value, string $label, int $max): string {
    if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) erro("$label inválido.");
    return trim($value);
}
function dinheiroCampo($value, bool $nullable = false): ?int {
    if ($nullable && $value === null) return null;
    if (!is_int($value) || $value < 0 || $value > 100000000) erro('Valor inválido. Máximo: R$ 1.000.000,00.');
    return $value;
}
function verificarSenhaPhp(string $senha, string $hash): bool {
    if (preg_match('/^scrypt:([a-f0-9]{32}):([a-f0-9]{128})$/', $hash, $m)) {
        // Parameters used by the previous Android service: N=16384, r=8, p=1.
        return hash_equals($m[2], bin2hex(sodium_crypto_pwhash_scryptsalsa208sha256(64, $senha, $m[1], 524288, 16777216)));
    }
    return password_verify($senha, $hash);
}
function limitarLogin(): void {
    $key = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . gmdate('YmdHi'));
    consulta('INSERT INTO integracao_login (chave,tentativas,criado_em) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE tentativas=tentativas+1', [$key]);
    if ((int) consulta('SELECT tentativas FROM integracao_login WHERE chave=?', [$key])->fetchColumn() > 30) erro('Muitas tentativas. Aguarde um minuto.',429);
    consulta('DELETE FROM integracao_login WHERE criado_em < NOW() - INTERVAL 10 MINUTE');
}
function filhoAndroid(array $u): int {
    $ids = filhosPermitidos($u);
    if (!$ids) erro('Nenhum aluno vinculado.',403);
    $requested = $_GET['aluno'] ?? null;
    if ($requested !== null && (!is_string($requested) || !preg_match('/^\d{1,11}$/',$requested))) erro('Aluno inválido.');
    $id = $requested === null ? $ids[0] : (int)$requested;
    exigirFilho($u,$id); return $id;
}
function alunosAndroid(array $u): array {
    return array_map(fn($a)=>['cpf_filho'=>str_pad((string)$a['id'],11,'0',STR_PAD_LEFT),
        'usuario_filho'=>$a['name'],'idade_filho'=>$a['age'],'turma'=>$a['class']],alunos(filhosPermitidos($u)));
}
function sessaoAndroid(array $u, string $nome, ?int $alunoSelecionado = null): array {
    $children=alunosAndroid($u);$first=$children[0]??null;
    if($alunoSelecionado!==null){
        $selected=array_values(array_filter($children,fn($child)=>(int)$child['cpf_filho']===$alunoSelecionado));
        if(!$selected)erro('CPF do filho não está vinculado a este responsável.',403);
        $first=$selected[0];
    }
    $token=bin2hex(random_bytes(32));
    $expires=date('Y-m-d H:i:s',time()+($u['tipo']==='pai'?900:86400));
    consulta('INSERT INTO sessao(token,tipo,cpf,expira_em) VALUES (?,?,?,?)',[hash('sha256',$token),$u['tipo'],$u['cpf'],$expires]);
    return ['token'=>$token,'perfil'=>$u['tipo']==='pai'?'responsavel':'aluno','nome'=>$nome,
        'alunoId'=>$first['cpf_filho']??'','alunoNome'=>$first['usuario_filho']??'','turma'=>$first['turma']??'','filhos'=>$children];
}
function loginAndroid(array $body, bool $parent): array {
    limitarLogin();
    $cpf=$body['cpf']??'';$senha=$body['senha']??'';
    if(!is_string($cpf)||!preg_match('/^\d{11}$/',$cpf)||!is_string($senha)||strlen($senha)>128) erro('CPF ou senha inválidos.');
    $child=null;
    if($parent){
        $childCpf=$body['cpfFilho']??'';
        if(!is_string($childCpf)||!preg_match('/^\d{11}$/',$childCpf))erro('Informe os 11 dígitos do CPF do filho.');
        $child=(int)$childCpf;
    }
    $table=$parent?'user_pais':'user_filho';$key=$parent?'cpf_pai':'cpf_filho';$name=$parent?'usuario_pais':'usuario_filho';
    $user=consulta("SELECT * FROM $table WHERE $key=?",[$cpf])->fetch();
    if(!$user || !verificarSenhaPhp($senha,$user['senha'])) erro('CPF ou senha incorretos.',401);
    if(str_starts_with($user['senha'],'scrypt:')) consulta("UPDATE $table SET senha=? WHERE $key=?",[password_hash($senha,PASSWORD_DEFAULT),$cpf]);
    if(!$parent && isset($body['turma']) && $body['turma']!=='') consulta('UPDATE user_filho SET turma=? WHERE cpf_filho=?',[textoCampo($body['turma'],'Turma',30),$cpf]);
    return sessaoAndroid(['tipo'=>$parent?'pai':'filho','cpf'=>$cpf],$user[$name],$child);
}
function cadastrarAndroid(array $body): array {
    limitarLogin();$cpf=$body['cpf']??'';$idade=$body['idade']??null;$senha=$body['senha']??'';
    if(!is_string($cpf)||!preg_match('/^\d{11}$/',$cpf)) erro('Informe os 11 dígitos do CPF.');
    if(!is_int($idade)||$idade<1||$idade>120)erro('Idade inválida.');
    if(!is_string($senha)||strlen($senha)<6||strlen($senha)>128)erro('Use uma senha entre 6 e 128 caracteres.');
    $nome=textoCampo($body['nome']??null,'Nome',100);$turma=textoCampo($body['turma']??null,'Turma',30);
    try {consulta('INSERT INTO user_filho(cpf_filho,usuario_filho,idade_filho,senha,turma) VALUES (?,?,?,?,?)',[$cpf,$nome,$idade,password_hash($senha,PASSWORD_DEFAULT),$turma]);}
    catch(PDOException $e){if(($e->errorInfo[1]??0)===1062)erro('CPF já cadastrado. Use Entrar.',409);throw $e;}
    return sessaoAndroid(['tipo'=>'filho','cpf'=>$cpf],$nome);
}
function pedidoUnificado(array $usuario,array $body): array {
    $child=$body['student']??($usuario['tipo']==='filho'?(int)$usuario['cpf']:0);
    if(!is_int($child)||$child<=0)erro('Aluno inválido.');exigirFilho($usuario,$child);
    $interval=$body['interval']??'';$method=$body['paymentMethod']??'Saldo';$items=$body['items']??null;$key=$body['requestId']??null;
    if(!in_array($interval,INTERVALOS,true)||!in_array($method,['Saldo','Pix','Cartão'],true))erro('Intervalo ou pagamento inválido.');
    if(!is_array($items)||!array_is_list($items)||!$items||count($items)>100)erro('Carrinho inválido.');
    if($key!==null&&(!is_string($key)||!preg_match('/^[a-zA-Z0-9_-]{8,64}$/',$key)))erro('Identificador de pedido inválido.');
    $normalized=[];$qty=[];
    foreach($items as $item){
        if(!is_array($item))erro('Item inválido.');$id=$item['id']??0;$q=$item['qty']??0;$obs=$item['observation']??'';
        if(!is_int($id)||$id<=0||!is_int($q)||$q<1||$q>999||!is_string($obs)||mb_strlen($obs)>400)erro('Item ou observação inválidos.');
        $qty[$id]=($qty[$id]??0)+$q;$normalized[]=['id'=>$id,'qty'=>$q,'observation'=>$obs];
    }
    $expected=$body['totalCentavos']??null;
    if($expected!==null)dinheiroCampo($expected);
    $hash=hash('sha256',json_encode([$child,$interval,$method,$normalized,$expected]));$pdo=db();$pdo->beginTransaction();
    try{
        $student=consulta('SELECT * FROM user_filho WHERE cpf_filho=? FOR UPDATE',[$child])->fetch();if(!$student)erro('Aluno não encontrado.',404);
        if($key!==null){$prior=consulta('SELECT * FROM integracao_pedido WHERE cpf_filho=? AND chave=?',[$child,$key])->fetch();
            if($prior){if(!hash_equals($prior['corpo_hash'],$hash))erro('Pedido já enviado com outros dados.',409);$pdo->commit();return ['order'=>pedidos(['id'=>$prior['id_pedido']])[0],'balance'=>centavos($student['saldo']),'repetido'=>true];}}
        ksort($qty,SORT_NUMERIC);$products=[];$total=0;
        foreach($qty as $id=>$q){$p=consulta('SELECT * FROM cardapio WHERE id_produto=? FOR UPDATE',[$id])->fetch();
            if(!$p||$p['excluido']||!$p['disponivel']||($p['qt_produto']!==null&&(int)$p['qt_produto']<$q))erro('Produto indisponível ou estoque insuficiente.',409);
            $products[$id]=$p;$total+=centavos($p['vl_produto'])*$q;}
        dinheiroCampo($total);
        if($expected!==null&&$expected!==$total)erro('O preço mudou. Atualize o carrinho.',409);
        $spent=consulta("SELECT COALESCE(SUM(CASE WHEN p.dt_pedido=CURDATE() THEN i.quantidade*i.vl_unitario ELSE 0 END),0) diario,COALESCE(SUM(i.quantidade*i.vl_unitario),0) mensal FROM pedido p JOIN pedido_item i ON i.id_pedido=p.id_pedido WHERE p.cpf_filho=? AND p.status<>'Cancelado' AND p.dt_pedido>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND p.dt_pedido<=CURDATE()",[$child])->fetch();
        foreach(['diario','mensal'] as $period){if($student['limite_'.$period]!==null&&centavos($spent[$period])+$total>(int)$student['limite_'.$period])erro('Limite '.$period.' definido pelo responsável excedido.',409);}
        $balance=centavos($student['saldo']);if($method==='Saldo'&&$balance<$total)erro('Saldo insuficiente.',409);
        consulta('INSERT INTO pedido(cpf_filho,dt_pedido,horario_retirada,status,forma_pagamento,pagamento_status) VALUES (?,CURDATE(),?,?,?,?)',[$child,$interval,'Em preparo',$method,$method==='Saldo'?'Pago':'Pendente']);$order=(int)$pdo->lastInsertId();
        foreach($normalized as $i)consulta('INSERT INTO pedido_item(id_pedido,id_produto,quantidade,vl_unitario,observacao) VALUES (?,?,?,?,?)',[$order,$i['id'],$i['qty'],$products[$i['id']]['vl_produto'],$i['observation']]);
        foreach($qty as $id=>$q)consulta('UPDATE cardapio SET qt_produto=qt_produto-? WHERE id_produto=? AND qt_produto IS NOT NULL',[$q,$id]);
        if($method==='Saldo'){
            consulta('UPDATE user_filho SET saldo=saldo-? WHERE cpf_filho=?',[reais($total),$child]);
            consulta('INSERT INTO extrato(cpf_filho,id_pedido,gasto,horario,dt_extrato) VALUES (?,?,?,CURTIME(),CURDATE())',[$child,$order,reais($total)]);
            consulta("INSERT INTO integracao_movimento(cpf_filho,valor_centavos,tipo,id_pedido) VALUES (?,?,'Compra',?)",[$child,-$total,$order]);$balance-=$total;}
        if($key!==null)consulta('INSERT INTO integracao_pedido(cpf_filho,chave,corpo_hash,id_pedido) VALUES (?,?,?,?)',[$child,$key,$hash,$order]);
        $pdo->commit();return ['order'=>pedidos(['id'=>$order])[0],'balance'=>$balance,'repetido'=>false];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function cancelarUnificado(int $id): array {
    $child=consulta('SELECT cpf_filho FROM pedido WHERE id_pedido=?',[$id])->fetchColumn();if(!$child)erro('Pedido não encontrado.',404);
    $pdo=db();$pdo->beginTransaction();
    try{
        consulta('SELECT cpf_filho FROM user_filho WHERE cpf_filho=? FOR UPDATE',[$child]);
        $p=consulta('SELECT * FROM pedido WHERE id_pedido=? FOR UPDATE',[$id])->fetch();
        if(!in_array($p['status'],['Em preparo','Pronto'],true))erro('Pedido não pode ser cancelado.',409);
        $refund=(!$p['forma_pagamento']||$p['forma_pagamento']==='Saldo')?centavos(consulta('SELECT COALESCE(SUM(gasto),0) FROM extrato WHERE id_pedido=?',[$id])->fetchColumn()):0;
        consulta("UPDATE pedido SET status='Cancelado',pagamento_status=? WHERE id_pedido=?",[$refund>0?'Estornado':'Cancelado',$id]);
        $items=consulta('SELECT id_produto,SUM(quantidade) qty FROM pedido_item WHERE id_pedido=? GROUP BY id_produto ORDER BY id_produto',[$id])->fetchAll();
        foreach($items as $i)consulta('UPDATE cardapio SET qt_produto=qt_produto+? WHERE id_produto=? AND qt_produto IS NOT NULL',[$i['qty'],$i['id_produto']]);
        if($refund>0){consulta('UPDATE user_filho SET saldo=saldo+? WHERE cpf_filho=?',[reais($refund),$child]);
            consulta('INSERT INTO extrato(cpf_filho,id_pedido,gasto,horario,dt_extrato) VALUES (?,?,?,CURTIME(),CURDATE())',[$child,$id,reais(-$refund)]);
            consulta("INSERT INTO integracao_movimento(cpf_filho,valor_centavos,tipo,id_pedido) VALUES (?,?,'Estorno',?)",[$child,$refund,$id]);}
        $pdo->commit();return ['ok'=>true,'status'=>'Cancelado','refunded'=>$refund];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function pedidosAndroid(int $child): array {
    return array_map(function($p){$details=consulta('SELECT pagamento_status FROM pedido WHERE id_pedido=?',[$p['id']])->fetch();
        return ['id_pedido'=>$p['id'],'status_pedido'=>$p['status'],'dt_pedido'=>$p['date'],'horario_recreio'=>$p['interval'],
            'forma_pagamento'=>$p['paymentMethod'],'status_pagamento'=>$details['pagamento_status'],'totalCentavos'=>$p['total'],
            'itens'=>array_map(fn($i)=>['nome'=>$i['name'],'quantidade'=>$i['qty'],'precoCentavos'=>$i['price'],'observacao'=>$i['observation']??''],$p['items'])];
    },array_reverse(pedidos(['alunos'=>[$child]])));
}
function carteiraAndroid(int $child): array {
    $w=consulta('SELECT saldo,limite_diario,limite_mensal FROM user_filho WHERE cpf_filho=?',[$child])->fetch();
    $moves=consulta('SELECT id,valor_centavos,tipo,id_pedido,criado_em FROM integracao_movimento WHERE cpf_filho=? ORDER BY id DESC LIMIT 50',[$child])->fetchAll();
    return ['saldoCentavos'=>centavos($w['saldo']),'limiteDiarioCentavos'=>$w['limite_diario']===null?null:(int)$w['limite_diario'],
        'limiteMensalCentavos'=>$w['limite_mensal']===null?null:(int)$w['limite_mensal'],'movimentos'=>$moves];
}
function creditoAndroid(int $child,array $user,array $body): array {
    $value=dinheiroCampo($body['valorCentavos']??null);$key=$body['requestId']??null;
    if(!$value||!is_string($key)||!preg_match('/^[a-zA-Z0-9_-]{8,64}$/',$key))erro('Valor ou identificador de recarga inválido.');
    $pdo=db();$pdo->beginTransaction();
    try{
        $balance=centavos(consulta('SELECT saldo FROM user_filho WHERE cpf_filho=? FOR UPDATE',[$child])->fetchColumn());
        $previous=consulta('SELECT valor_centavos,cpf_responsavel FROM integracao_movimento WHERE cpf_filho=? AND chave=?',[$child,$key])->fetch();
        if($previous){if((int)$previous['valor_centavos']!==$value||(string)$previous['cpf_responsavel']!==(string)$user['cpf'])erro('Identificador já usado em outra recarga.',409);$pdo->commit();return ['saldoCentavos'=>$balance,'repetido'=>true];}
        dinheiroCampo($balance+$value);
        consulta('UPDATE user_filho SET saldo=saldo+? WHERE cpf_filho=?',[reais($value),$child]);
        consulta("INSERT INTO integracao_movimento(cpf_filho,cpf_responsavel,valor_centavos,tipo,chave) VALUES (?,?,?,'Credito local',?)",[$child,$user['cpf'],$value,$key]);
        $pdo->commit();return ['saldoCentavos'=>$balance+$value,'repetido'=>false];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function rotasAndroid(string $route,string $method,array $body): void {
    if(!str_starts_with($route,'android/'))return;
    $path=substr($route,8);
    if($method==='GET'&&$path==='health'){consulta('SELECT 1');responder(['ok'=>true,'backend'=>'php','database'=>'gestao_cantina']);}
    if($method==='POST'&&$path==='login')responder(loginAndroid($body,false));
    if($method==='POST'&&$path==='responsavel/login')responder(loginAndroid($body,true));
    if($method==='POST'&&$path==='alunos')responder(cadastrarAndroid($body),201);
    $u=exigir('pai','filho');
    if($method==='POST'&&$path==='logout'){consulta('DELETE FROM sessao WHERE token IN (?,?)',[tokenRecebido(),hash('sha256',tokenRecebido())]);responder(['ok'=>true]);}
    if(str_starts_with($path,'responsavel/')){
        $u=exigir('pai');$child=filhoAndroid($u);
        if($method==='GET'&&$path==='responsavel/filho'){$children=alunosAndroid($u);$chosen=array_values(array_filter($children,fn($f)=>(int)$f['cpf_filho']===$child));responder(['filho'=>$chosen[0],'filhos'=>$children]);}
        if($method==='GET'&&$path==='responsavel/pedidos')responder(['pedidos'=>pedidosAndroid($child)]);
        if($method==='GET'&&$path==='responsavel/carteira')responder(carteiraAndroid($child));
        if($method==='POST'&&$path==='responsavel/carteira/creditos'){$r=creditoAndroid($child,$u,$body);responder($r,$r['repetido']?200:201);}
        if($method==='PUT'&&$path==='responsavel/carteira/limites'){
            if(!array_key_exists('limiteDiarioCentavos',$body)||!array_key_exists('limiteMensalCentavos',$body))erro('Informe os dois limites.');
            $d=dinheiroCampo($body['limiteDiarioCentavos'],true);$m=dinheiroCampo($body['limiteMensalCentavos'],true);
            consulta('UPDATE user_filho SET limite_diario=?,limite_mensal=? WHERE cpf_filho=?',[$d,$m,$child]);responder(['limiteDiarioCentavos'=>$d,'limiteMensalCentavos'=>$m]);}
        erro('Rota não encontrada.',404);
    }
    $u=exigir('filho');$child=(int)$u['cpf'];
    if($method==='GET'&&$path==='cardapio'){
        $rows=consulta('SELECT * FROM cardapio WHERE excluido=0 AND disponivel=1 ORDER BY id_produto')->fetchAll();
        responder(['produtos'=>array_map(fn($p)=>['id'=>(string)$p['id_produto'],'nome'=>$p['nome_produto'],'descricao'=>$p['ds_produto'],
            'precoCentavos'=>centavos($p['vl_produto']),'estoque'=>$p['qt_produto']===null?999:(int)$p['qt_produto'],'sabor'=>$p['sabor'],'horario'=>$p['hora'],'imagem'=>$p['imagem']],$rows)]);}
    if($method==='GET'&&$path==='pedidos')responder(['pedidos'=>pedidosAndroid($child)]);
    if($method==='POST'&&$path==='pedidos'){
        $items=$body['itens']??null;if(!is_array($items)||!array_is_list($items))erro('Carrinho inválido.');$mapped=[];
        foreach($items as $i){if(!is_array($i)||!preg_match('/^[1-9][0-9]*$/',(string)($i['id']??'')))erro('Item inválido.');$mapped[]=['id'=>(int)$i['id'],'qty'=>$i['quantidade']??null,'observation'=>$i['observacao']??''];}
        if(!isset($body['requestId'],$body['totalCentavos']))erro('Identificador e total são obrigatórios.');
        $r=pedidoUnificado($u,['student'=>$child,'interval'=>$body['recreio']??null,'paymentMethod'=>$body['metodo']??null,
            'items'=>$mapped,'requestId'=>$body['requestId'],'totalCentavos'=>$body['totalCentavos']]);
        responder(['idPedido'=>$r['order']['id'],'totalCentavos'=>$r['order']['total'],'status'=>$r['order']['status'],'repetido'=>$r['repetido']],$r['repetido']?200:201);}
    erro('Rota não encontrada.',404);
}
