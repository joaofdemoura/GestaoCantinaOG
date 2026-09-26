param([string]$WebRoot="$PSScriptRoot/web")
$ErrorActionPreference='Stop'
$php=& "$WebRoot/Localizar-PHP.ps1"
$cpf=Read-Host 'CPF do responsável (11 dígitos)'
$nome=Read-Host 'Nome do responsável'
$filho=Read-Host 'CPF do filho já cadastrado'
$secure=Read-Host 'Senha do responsável (mínimo 10 caracteres; use a atual se ele já existir)' -AsSecureString
$previousEncoding=$OutputEncoding
try {
    $OutputEncoding=[System.Text.UTF8Encoding]::new($false)
    $plain=[System.Net.NetworkCredential]::new('', $secure).Password
    @{cpf=$cpf;nome=$nome;cpfFilho=$filho;senha=$plain} | ConvertTo-Json -Compress | & $php "$WebRoot/tools/cadastrar-responsavel.php"
    if($LASTEXITCODE -ne 0){throw 'Cadastro não concluído. Confira a mensagem acima.'}
} finally {$plain=$null;$secure=$null;$OutputEncoding=$previousEncoding}