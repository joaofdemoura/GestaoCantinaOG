param([string]$SdkPath="${env:LOCALAPPDATA}/Android/Sdk")
$ErrorActionPreference='Stop'
$name='Cantina_Leve_API_36'
$avdRoot=if($env:ANDROID_AVD_HOME){$env:ANDROID_AVD_HOME}else{"$env:USERPROFILE/.android/avd"}
$avdPath=Join-Path $avdRoot "$name.avd"
$iniPath=Join-Path $avdRoot "$name.ini"
if((Test-Path -LiteralPath "$avdPath/config.ini") -and (Test-Path -LiteralPath $iniPath)){return}
if((Test-Path -LiteralPath $avdPath) -or (Test-Path -LiteralPath $iniPath)){throw 'Existe uma configuração incompleta do Cantina Leve. Confira-a no Device Manager.'}
$systemImage=Join-Path $SdkPath 'system-images/android-36/google_apis_playstore/x86_64/system.img'
if(!(Test-Path -LiteralPath $systemImage)){throw 'Instale a imagem Android 16/API 36 Google Play x86_64 no SDK Manager antes de criar o emulador.'}
New-Item -ItemType Directory -Path $avdPath -Force | Out-Null
Copy-Item -LiteralPath "$PSScriptRoot/android-emulator/config.ini" -Destination "$avdPath/config.ini"
[IO.File]::WriteAllLines($iniPath,@('avd.ini.encoding=UTF-8',"path=$avdPath",'target=android-36'),[Text.UTF8Encoding]::new($false))
Write-Host 'Cantina Leve criado: 2,5 GB de RAM, 2 núcleos e tela 720x1280.'