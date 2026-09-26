param([string]$WebRoot="$PSScriptRoot/web")
$ErrorActionPreference='Stop'
$php=& "$WebRoot/Localizar-PHP.ps1"
& $php "$WebRoot/tools/migrate-android.php"
if($LASTEXITCODE -ne 0){throw 'Falha ao preparar o banco. Inicie o MySQL no Laragon e confira api/config.php.'}
& "$PSScriptRoot/Iniciar-Cantina.ps1" -WebRoot $WebRoot