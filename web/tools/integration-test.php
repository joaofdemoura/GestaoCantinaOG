<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/db.php';
$pdo=db();$base=getenv('CANTINA_API_URL')?:'http://127.0.0.1:8000/api';$seed=random_int(80000000000,89999999990);
$child=(string)$seed;$other=(string)($seed+1);$parent=(string)($seed+2);$admin=(string)($seed+3);$password=bin2hex(random_bytes(16));$product=null;$passed=0;
function apiTest(string $path,string $method='GET',?array $body=null,?string $token=null):array{
 global $base;$ch=curl_init($base.$path);$headers=['Content-Type: application/json'];if($token)$headers[]='Authorization: Bearer '.$token;
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>15]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));$text=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($text===false)throw new RuntimeException(curl_error($ch));curl_close($ch);
 $data=json_decode($text,true);if(!is_array($data))throw new RuntimeException("Resposta inválida HTTP $status em $path: ".substr($text,0,250));return ['status'=>$status,'data'=>$data];
}
function check($actual,$expected,string $label):void{global $passed;if($actual!==$expected)throw new RuntimeException($label.' esperado='.json_encode($expected).' recebido='.json_encode($actual));$passed++;}
function sqlTest($sql,$args=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($args);return $s;}
function parentCliTest(array $data):int {
 $process=proc_open([PHP_BINARY,__DIR__.'/cadastrar-responsavel.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($process))throw new RuntimeException('Falha ao abrir o comando PHP.');
 fwrite($pipes[0],json_encode($data));fclose($pipes[0]);
 stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);
 return proc_close($process);
}
try{
 check(apiTest('/android/health')['data']['backend'],'php','Saúde PHP');
 sqlTest('INSERT INTO user_admin(cpf_admin,usuario_adm,senha) VALUES (?,?,?)',[$admin,'TestePHP'.$admin,password_hash($password,PASSWORD_DEFAULT)]);
 $a=apiTest('/login','POST',['type'=>'admin','login'=>'TestePHP'.$admin,'password'=>$password]);check($a['status'],200,'Login web');$at=$a['data']['token'];
 $signup=['cpf'=>$child,'nome'=>'Aluno integração PHP','idade'=>12,'senha'=>$password,'turma'=>'7º A'];
 check(apiTest('/android/alunos','POST',array_diff_key($signup,['turma'=>true]))['status'],400,'Turma obrigatória');
 $r=apiTest('/android/alunos','POST',$signup);check($r['status'],201,'Cadastro Android');$ct=$r['data']['token'];
 sqlTest('INSERT INTO user_filho(cpf_filho,usuario_filho,idade_filho,senha,turma) VALUES (?,?,?,?,?)',[$other,'Segundo filho PHP',10,password_hash($password,PASSWORD_DEFAULT),'5º B']);
 $parentData=['cpf'=>$parent,'cpfFilho'=>$child,'nome'=>'Pai integração PHP','senha'=>$password];
 check(parentCliTest($parentData),0,'Cadastro de responsável com PHP');
 $parentData['cpfFilho']=$other;check(parentCliTest($parentData),0,'Vínculo do segundo filho com PHP');
 $parentData['senha']='Senha-errada-teste';check(parentCliTest($parentData),1,'Senha inválida não altera responsável');
 $parentData['cpf']=$admin;check(parentCliTest($parentData),1,'Vínculo existente não pode ser transferido');
 $parentLogin=['cpf'=>$parent,'senha'=>$password,'cpfFilho'=>$other];
 check(apiTest('/android/responsavel/login','POST',['cpf'=>$parent,'senha'=>$password])['status'],400,'CPF do filho obrigatório no login');
 check(apiTest('/android/responsavel/login','POST',array_replace($parentLogin,['cpfFilho'=>'123']))['status'],400,'CPF do filho incompleto recusado');
 check(apiTest('/android/responsavel/login','POST',array_replace($parentLogin,['senha'=>'incorreta']))['status'],401,'CPF do filho não substitui a senha do pai');
 $sessions=(int)sqlTest("SELECT COUNT(*) FROM sessao WHERE tipo='pai' AND cpf=?",[$parent])->fetchColumn();
 check(apiTest('/android/responsavel/login','POST',array_replace($parentLogin,['cpfFilho'=>$admin]))['status'],403,'Login recusa filho sem vínculo');
 check((int)sqlTest("SELECT COUNT(*) FROM sessao WHERE tipo='pai' AND cpf=?",[$parent])->fetchColumn(),$sessions,'Filho sem vínculo não cria sessão');
 $p=apiTest('/android/responsavel/login','POST',$parentLogin);
 check($p['data']['alunoId']??null,$other,'Login seleciona o filho informado');
 check($p['data']['alunoNome']??null,'Segundo filho PHP','Nome corresponde ao filho informado');check($p['status'],200,'Login pais');$pt=$p['data']['token'];check(count($p['data']['filhos']),2,'Dois filhos');
 check(apiTest('/estado','GET',null,$ct)['status'],403,'Aluno não acessa admin');
 check(apiTest('/android/responsavel/carteira','GET',null,$ct)['status'],403,'Aluno não acessa carteira dos pais');
 check(apiTest('/android/responsavel/filho?aluno=22222222201','GET',null,$pt)['status'],404,'Pais não acessam outro filho');
 check(apiTest('/android/responsavel/filho?aluno='.$other,'GET',null,$pt)['data']['filho']['cpf_filho'],$other,'Seleção do segundo filho');
 $definition=['name'=>'Produto integração PHP','description'=>'Fixture temporária','category'=>'Teste','flavor'=>'Teste','time'=>'09:00','price'=>250];
 $pr=apiTest('/produtos','POST',$definition,$at);check($pr['status'],201,'Admin cria produto');$product=$pr['data']['id'];sqlTest('UPDATE cardapio SET qt_produto=10 WHERE id_produto=?',[$product]);
 $menu=apiTest('/android/cardapio','GET',null,$ct)['data']['produtos'];$match=array_values(array_filter($menu,fn($p)=>(int)$p['id']===$product));check($match[0]['precoCentavos'],250,'Produto admin aparece no Android');
 $credit=['valorCentavos'=>1000,'requestId'=>bin2hex(random_bytes(12))];$wallet='/android/responsavel/carteira';$suffix='?aluno='.$child;
 check(apiTest($wallet.'/creditos'.$suffix,'POST',$credit,$pt)['status'],201,'Crédito PHP');check(apiTest($wallet.'/creditos'.$suffix,'POST',$credit,$pt)['status'],200,'Crédito idempotente');
 check(apiTest($wallet.'?aluno='.$other,'GET',null,$pt)['data']['saldoCentavos'],0,'Saldo separado por filho');
 check(apiTest('/estado','GET',null,$at)['status'],200,'Estado web atualizado');
 $students=apiTest('/alunos','GET',null,$at)['data'];$s=array_values(array_filter($students,fn($s)=>(string)$s['id']===$child));check($s[0]['balance'],1000,'Saldo do Android no painel');check($s[0]['class'],'7º A','Turma no painel');
 $limits=['limiteDiarioCentavos'=>250,'limiteMensalCentavos'=>500];check(apiTest($wallet.'/limites'.$suffix,'PUT',$limits,$pt)['status'],200,'Limites PHP');
 $order=['requestId'=>bin2hex(random_bytes(12)),'metodo'=>'Saldo','recreio'=>'09:00','totalCentavos'=>500,'itens'=>[['id'=>(string)$product,'quantidade'=>2,'observacao'=>'Sem molho']]];
 check(apiTest('/android/pedidos','POST',$order,$ct)['status'],409,'Limite bloqueia compra');$order['itens'][0]['quantidade']=1;$order['totalCentavos']=250;
 $o=apiTest('/android/pedidos','POST',$order,$ct);check($o['status'],201,'Pedido Android criado');$oid=$o['data']['idPedido'];check(apiTest('/android/pedidos','POST',$order,$ct)['data']['idPedido'],$oid,'Pedido idempotente');
 $orders=apiTest('/pedidos','GET',null,$at)['data'];$view=array_values(array_filter($orders,fn($p)=>$p['id']===$oid));check($view[0]['items'][0]['observation'],'Sem molho','Observação chega ao painel');check($view[0]['paymentStatus'],'Pago','Pagamento com saldo aparece pago no painel');
 check(apiTest('/pedidos/'.$oid,'PATCH',['status'=>'Pronto'],$at)['status'],200,'Admin prepara pedido');
 check(apiTest('/android/pedidos','GET',null,$ct)['data']['pedidos'][0]['status_pedido'],'Pronto','Status web chega ao Android');
 check(apiTest('/pedidos/'.$oid,'PATCH',['status'=>'Cancelado'],$at)['data']['refunded'],250,'Estorno de saldo');
 check(apiTest('/pedidos/'.$oid,'PATCH',['status'=>'Cancelado'],$at)['status'],409,'Sem estorno duplicado');
 check(apiTest($wallet.$suffix,'GET',null,$pt)['data']['saldoCentavos'],1000,'Saldo devolvido');check((int)sqlTest('SELECT qt_produto FROM cardapio WHERE id_produto=?',[$product])->fetchColumn(),10,'Estoque devolvido');
 $order['requestId']=bin2hex(random_bytes(12));$order['metodo']='Pix';$o=apiTest('/android/pedidos','POST',$order,$ct);check($o['status'],201,'Pix pendente');
 check(apiTest($wallet.$suffix,'GET',null,$pt)['data']['saldoCentavos'],1000,'Pix não debita saldo');check(apiTest('/pedidos/'.$o['data']['idPedido'],'PATCH',['status'=>'Cancelado'],$at)['data']['refunded'],0,'Pix não gera estorno indevido');
 apiTest($wallet.'/limites'.$suffix,'PUT',['limiteDiarioCentavos'=>0,'limiteMensalCentavos'=>null],$pt);
 check(apiTest('/pedidos','POST',['student'=>(int)$child,'interval'=>'09:00','items'=>[['id'=>$product,'qty'=>1]]],$pt)['status'],409,'Rota web respeita limite');
 apiTest('/produtos/'.$product,'PATCH',['available'=>false],$at);$menu=apiTest('/android/cardapio','GET',null,$ct)['data']['produtos'];check(count(array_filter($menu,fn($p)=>(int)$p['id']===$product)),0,'Indisponível some do app');
 check(apiTest('/android/login','POST',['cpf'=>$child,'senha'=>'incorreta','turma'=>'Outra'])['status'],401,'Turma exige senha');check(apiTest('/android/login','POST',['cpf'=>$child,'senha'=>$password,'turma'=>'8º C'])['data']['turma'],'8º C','Turma persistida');
 check(apiTest('/android/logout','POST',null,$pt)['status'],200,'Logout');check(apiTest($wallet.$suffix,'GET',null,$pt)['status'],401,'Sessão revogada');
 echo "OK: $passed verificações PHP/Android/painel.\n";
}finally{
 sqlTest('DELETE FROM sessao WHERE cpf IN (?,?,?,?)',[$child,$other,$parent,$admin]);
 sqlTest('DELETE FROM integracao_movimento WHERE cpf_filho IN (?,?)',[$child,$other]);sqlTest('DELETE FROM integracao_pedido WHERE cpf_filho IN (?,?)',[$child,$other]);
 sqlTest('DELETE FROM extrato WHERE cpf_filho IN (?,?)',[$child,$other]);sqlTest('DELETE FROM pedido WHERE cpf_filho IN (?,?)',[$child,$other]);
 sqlTest('UPDATE user_filho SET cpf_pai=NULL WHERE cpf_filho IN (?,?)',[$child,$other]);sqlTest('DELETE FROM user_pais WHERE cpf_pai=?',[$parent]);sqlTest('DELETE FROM user_filho WHERE cpf_filho IN (?,?)',[$child,$other]);sqlTest('DELETE FROM user_admin WHERE cpf_admin=?',[$admin]);if($product)sqlTest('DELETE FROM cardapio WHERE id_produto=?',[$product]);
 echo "Registros temporários removidos.\n";
}
