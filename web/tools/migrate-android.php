<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/db.php';
$pdo=db();
$backupDir=(getenv('LOCALAPPDATA')?:sys_get_temp_dir()).'/CantinaBackups';
if(!is_dir($backupDir))mkdir($backupDir,0700,true);
function backupDatabase(PDO $db,string $path):void{
 $out=fopen($path,'xb');fwrite($out,"-- Backup local; contém dados privados.\nSET FOREIGN_KEY_CHECKS=0;\n");
 foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
  $schema=$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];fwrite($out,"DROP TABLE IF EXISTS `$table`;\n$schema;\n");
  foreach($db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $row){$values=array_map(fn($v)=>$v===null?'NULL':$db->quote((string)$v),array_values($row));fwrite($out,"INSERT INTO `$table` (`".implode('`,`',array_keys($row))."`) VALUES (".implode(',',$values).");\n");}
 }fwrite($out,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($out);
}
$stamp=date('Ymd-His').'-'.bin2hex(random_bytes(3));backupDatabase($pdo,"$backupDir/web-$stamp.sql");
function addColumn(PDO $db,string $table,string $column,string $definition):void{
 $q=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$table,$column]);
 if(!$q->fetchColumn())$db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}
addColumn($pdo,'user_filho','limite_diario','INT NULL');addColumn($pdo,'user_filho','limite_mensal','INT NULL');
$pdo->exec("ALTER TABLE user_filho MODIFY turma VARCHAR(30) NOT NULL DEFAULT ''");
addColumn($pdo,'cardapio','qt_produto','INT NULL');addColumn($pdo,'pedido_item','observacao',"VARCHAR(400) NOT NULL DEFAULT ''");
addColumn($pdo,'pedido','pagamento_status',"VARCHAR(30) NOT NULL DEFAULT 'Pago'");
$pdo->exec('CREATE TABLE IF NOT EXISTS integracao_pedido(cpf_filho BIGINT NOT NULL,chave VARCHAR(64) NOT NULL,corpo_hash CHAR(64) NOT NULL,id_pedido INT NOT NULL,PRIMARY KEY(cpf_filho,chave),FOREIGN KEY(cpf_filho) REFERENCES user_filho(cpf_filho),FOREIGN KEY(id_pedido) REFERENCES pedido(id_pedido))');
$pdo->exec('CREATE TABLE IF NOT EXISTS integracao_movimento(id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,cpf_filho BIGINT NOT NULL,cpf_responsavel BIGINT NULL,valor_centavos INT NOT NULL,tipo VARCHAR(30) NOT NULL,chave VARCHAR(64) NULL,id_pedido INT NULL,criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY recarga_unica(cpf_filho,chave),FOREIGN KEY(cpf_filho) REFERENCES user_filho(cpf_filho))');
$pdo->exec('CREATE TABLE IF NOT EXISTS integracao_origem(tipo VARCHAR(30) NOT NULL,origem VARCHAR(80) NOT NULL,destino VARCHAR(80) NOT NULL,PRIMARY KEY(tipo,origem))');
$pdo->exec('CREATE TABLE IF NOT EXISTS integracao_login(chave CHAR(64) NOT NULL PRIMARY KEY,tentativas INT NOT NULL,criado_em DATETIME NOT NULL)');
echo "Esquema PHP atualizado. Backup: $backupDir/web-$stamp.sql\n";
$legacy=$argv[1]??null;if(!$legacy)exit;
$c=json_decode(file_get_contents($legacy),true,512,JSON_THROW_ON_ERROR);
$old=new PDO("mysql:host=127.0.0.1;port={$c['dbPort']};dbname=gestao_cantina;charset=utf8mb4",'cantina_app',$c['dbPassword'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
backupDatabase($old,"$backupDir/android-$stamp.sql");
function runSql(PDO $p,string $s,array $v=[]):PDOStatement{$q=$p->prepare($s);$q->execute($v);return $q;}
function mapped(PDO $p,string $type,$id){return runSql($p,'SELECT destino FROM integracao_origem WHERE tipo=? AND origem=?',[$type,(string)$id])->fetchColumn();}
function mapping(PDO $p,string $type,$old,$new):void{runSql($p,'INSERT INTO integracao_origem(tipo,origem,destino) VALUES (?,?,?)',[$type,(string)$old,(string)$new]);}
$counts=['alunos'=>0,'pais'=>0,'produtos'=>0,'pedidos'=>0];$pdo->beginTransaction();
try{
 foreach($old->query('SELECT f.*,c.saldo_centavos,c.limite_diario,c.limite_mensal FROM user_filho f LEFT JOIN carteira c ON c.cpf_filho=f.cpf_filho') as $f){
  if(mapped($pdo,'aluno',$f['cpf_filho']))continue;
  if(runSql($pdo,'SELECT 1 FROM user_filho WHERE cpf_filho=?',[$f['cpf_filho']])->fetchColumn())throw new RuntimeException('CPF de aluno já existe no painel; conciliação necessária.');
  runSql($pdo,'INSERT INTO user_filho(cpf_filho,usuario_filho,idade_filho,senha,turma,saldo,limite_diario,limite_mensal) VALUES (?,?,?,?,?,?,?,?)',[$f['cpf_filho'],$f['usuario_filho'],$f['idade_filho'],$f['senha'],$f['turma']??'',reais((int)($f['saldo_centavos']??0)),$f['limite_diario'],$f['limite_mensal']]);mapping($pdo,'aluno',$f['cpf_filho'],$f['cpf_filho']);$counts['alunos']++;
 }
 foreach($old->query('SELECT * FROM user_pais') as $p){
  if(mapped($pdo,'pai',$p['cpf_pai']))continue;
  if(runSql($pdo,'SELECT 1 FROM user_pais WHERE cpf_pai=?',[$p['cpf_pai']])->fetchColumn())throw new RuntimeException('CPF de responsável já existe no painel; conciliação necessária.');
  runSql($pdo,'INSERT INTO user_pais(cpf_pai,usuario_pais,senha,cpf_filho) VALUES (?,?,?,?)',[$p['cpf_pai'],$p['usuario_pais'],$p['senha'],$p['cpf_filho']]);
  runSql($pdo,'UPDATE user_filho SET cpf_pai=? WHERE cpf_filho=?',[$p['cpf_pai'],$p['cpf_filho']]);mapping($pdo,'pai',$p['cpf_pai'],$p['cpf_pai']);$counts['pais']++;
 }
 foreach($old->query('SELECT * FROM cardapio') as $p){
  if(mapped($pdo,'produto',$p['id_produto']))continue;
  runSql($pdo,'INSERT INTO cardapio(nome_produto,ds_produto,vl_produto,imagem,hora,sabor,qt_produto) VALUES (?,?,?,?,?,?,?)',[$p['nome_produto'],$p['ds_produto'],$p['vl_produto'],$p['imagem'],$p['hora'],$p['sabor'],$p['qt_produto']]);mapping($pdo,'produto',$p['id_produto'],$pdo->lastInsertId());$counts['produtos']++;
 }
 foreach($old->query('SELECT p.*,pg.forma_pagamento,pg.status_pagamento FROM pedido p LEFT JOIN pagamento pg ON pg.id_pedido=p.id_pedido ORDER BY p.id_pedido') as $p){
  if(mapped($pdo,'pedido',$p['id_pedido']))continue;
  $status=in_array($p['status_pedido'],['Pronto','Entregue','Cancelado'],true)?$p['status_pedido']:'Em preparo';
  $method=strtolower($p['forma_pagamento']??'')==='pix'?'Pix':($p['forma_pagamento']??'Saldo');
  runSql($pdo,'INSERT INTO pedido(cpf_filho,dt_pedido,horario_retirada,status,forma_pagamento,pagamento_status) VALUES (?,?,?,?,?,?)',[$p['cpf_filho'],$p['dt_pedido'],$p['horario_recreio'],$status,$method,$p['status_pagamento']??'Pendente']);$new=$pdo->lastInsertId();mapping($pdo,'pedido',$p['id_pedido'],$new);
  foreach(runSql($old,'SELECT * FROM item_pedido WHERE id_pedido=?',[$p['id_pedido']]) as $i)runSql($pdo,'INSERT INTO pedido_item(id_pedido,id_produto,quantidade,vl_unitario,observacao) VALUES (?,?,?,?,?)',[$new,mapped($pdo,'produto',$i['id_produto']),$i['quantidade'],$i['valor_unitario'],$i['observacao']??'']);
  $counts['pedidos']++;
 }
 foreach($old->query('SELECT * FROM extrato') as $e){if(mapped($pdo,'extrato',$e['id_extrato']))continue;runSql($pdo,'INSERT INTO extrato(cpf_filho,id_pedido,gasto,horario,dt_extrato) VALUES (?,?,?,?,?)',[$e['cpf_filho'],mapped($pdo,'pedido',$e['id_pedido']),$e['gasto'],$e['horario'],$e['dt_extrato']]);mapping($pdo,'extrato',$e['id_extrato'],$pdo->lastInsertId());}
 foreach($old->query('SELECT * FROM carteira_movimento') as $m){if(mapped($pdo,'movimento',$m['id']))continue;runSql($pdo,'INSERT INTO integracao_movimento(cpf_filho,cpf_responsavel,valor_centavos,tipo,chave,id_pedido,criado_em) VALUES (?,?,?,?,?,?,?)',[$m['cpf_filho'],$m['cpf_responsavel'],$m['valor_centavos'],$m['tipo'],$m['request_key'],$m['id_pedido']?mapped($pdo,'pedido',$m['id_pedido']):null,$m['criado_em']]);mapping($pdo,'movimento',$m['id'],$pdo->lastInsertId());}
 // Sessions and old request hashes are deliberately not imported: the apps log in again.
 $pdo->commit();echo 'Importação concluída: '.json_encode($counts,JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
