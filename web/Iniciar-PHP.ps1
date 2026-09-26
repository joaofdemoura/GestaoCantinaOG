$ErrorActionPreference='Stop'
$web=$PSScriptRoot
$php=& "$PSScriptRoot/Localizar-PHP.ps1"
try {$health=Invoke-RestMethod 'http://127.0.0.1:8000/api/android/health' -TimeoutSec 3} catch {$health=$null}
if(!$health) {
    Start-Process -FilePath $php -ArgumentList '-S','127.0.0.1:8000','router.php' -WorkingDirectory $web -WindowStyle Hidden -RedirectStandardOutput "$env:TEMP/cantina-php.log" -RedirectStandardError "$env:TEMP/cantina-php-error.log"
    Start-Sleep -Seconds 2
    $health=Invoke-RestMethod 'http://127.0.0.1:8000/api/android/health'
}
if($health.backend -ne 'php'){throw 'A porta 8000 não respondeu como API PHP da cantina.'}
$adb="$env:LOCALAPPDATA/Android/Sdk/platform-tools/adb.exe"
if(Test-Path $adb){
    $devices=& $adb devices
    foreach($line in $devices){if($line -match '^(\S+)\s+device$'){& $adb -s $Matches[1] reverse tcp:8000 tcp:8000}}
}
Write-Host 'Painel: http://localhost:8000/dist/ — Android e web conectados via PHP ao MySQL do Laragon.'