<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/api/db.php';
require dirname(__DIR__).'/api/integration.php';

// Administrative local command. Passwords arrive through stdin, never command arguments.
$pdo = db();
try {
    $input = preg_replace('/^\xEF\xBB\xBF/', '', stream_get_contents(STDIN));
    $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
    $cpf = $data['cpf'] ?? '';
    $child = $data['cpfFilho'] ?? '';
    $name = $data['nome'] ?? '';
    $password = $data['senha'] ?? '';
    if (!is_string($cpf) || !preg_match('/^\d{11}$/', $cpf) || (int)$cpf === 0
        || !is_string($child) || !preg_match('/^\d{11}$/', $child) || (int)$child === 0
        || !is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 100
        || !is_string($password) || strlen($password) < 10 || strlen($password) > 72) {
        throw new RuntimeException('Dados inválidos. Informe CPFs com 11 dígitos, nome e senha de 10 a 72 bytes.');
    }
    $pdo->beginTransaction();
    $student = consulta('SELECT cpf_pai FROM user_filho WHERE cpf_filho=? FOR UPDATE', [$child])->fetch();
    if (!$student) throw new RuntimeException('Cadastre o aluno primeiro pelo app Android.');
    if ($student['cpf_pai'] !== null && (int)$student['cpf_pai'] !== (int)$cpf) {
        throw new RuntimeException('O aluno já está vinculado a outro responsável. Nenhum vínculo foi alterado.');
    }
    $parent = consulta('SELECT senha FROM user_pais WHERE cpf_pai=? FOR UPDATE', [$cpf])->fetch();
    if ($parent) {
        if (!verificarSenhaPhp($password, $parent['senha'])) throw new RuntimeException('Senha do responsável existente incorreta.');
    } else {
        consulta('INSERT INTO user_pais(cpf_pai,usuario_pais,senha,cpf_filho) VALUES (?,?,?,?)',
            [$cpf, trim($name), password_hash($password, PASSWORD_DEFAULT), $child]);
    }
    consulta('UPDATE user_filho SET cpf_pai=? WHERE cpf_filho=?', [$cpf, $child]);
    $pdo->commit();
    echo "Responsável cadastrado e vinculado ao aluno no banco do Laragon.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, ($e instanceof PDOException ? 'Falha no banco. Nenhum cadastro foi alterado.' : $e->getMessage())."\n");
    exit(1);
}
