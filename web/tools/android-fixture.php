<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/db.php';
$id='90909090909';$name='Responsável teste Android';$pdo=db();
$q=$pdo->prepare('SELECT usuario_pais FROM user_pais WHERE cpf_pai=?');$q->execute([$id]);$existing=$q->fetchColumn();
if($existing!==false && $existing!==$name)throw new RuntimeException('CPF de teste já está em uso.');
if(in_array('--cleanup',$argv,true)){
 $pdo->prepare("DELETE FROM sessao WHERE cpf=? AND tipo='pai'")->execute([$id]);
 $pdo->prepare('UPDATE user_filho SET cpf_pai=NULL WHERE cpf_pai=?')->execute([$id]);
 $pdo->prepare('DELETE FROM user_pais WHERE cpf_pai=? AND usuario_pais=?')->execute([$id,$name]);
 echo "Conta temporária removida.\n";
}elseif($existing===false){
 // A separate test child avoids changing the real parent's link.
 $child='90909090908';
 $st=$pdo->prepare('SELECT usuario_filho FROM user_filho WHERE cpf_filho=?');$st->execute([$child]);$childName=$st->fetchColumn();
 if($childName!==false&&$childName!=='Joao teste Android')throw new RuntimeException('CPF do aluno de teste já está em uso.');
 $pdo->prepare("INSERT IGNORE INTO user_filho(cpf_filho,usuario_filho,idade_filho,senha,turma) VALUES (?,'Joao teste Android',12,?,'Teste')")->execute([$child,password_hash('Teste-android-php-2026',PASSWORD_DEFAULT)]);
 $pdo->prepare('INSERT INTO user_pais(cpf_pai,usuario_pais,senha,cpf_filho) VALUES (?,?,?,?)')->execute([$id,$name,password_hash('Teste-responsavel-integracao-2026',PASSWORD_DEFAULT),$child]);
 $pdo->prepare('UPDATE user_filho SET cpf_pai=? WHERE cpf_filho=?')->execute([$id,$child]);echo "Conta temporária preparada.\n";
}
if(in_array('--cleanup',$argv,true))$pdo->exec("DELETE FROM user_filho WHERE cpf_filho=90909090908 AND usuario_filho='Joao teste Android'");
