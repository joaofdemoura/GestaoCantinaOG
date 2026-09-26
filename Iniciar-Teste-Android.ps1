param([string]$WebRoot="$PSScriptRoot/web")
$ErrorActionPreference='Stop'
& "$PSScriptRoot/Iniciar-Cantina.ps1" -WebRoot $WebRoot
$sdk=if($env:ANDROID_HOME){$env:ANDROID_HOME}else{"$env:LOCALAPPDATA/Android/Sdk"}
$adb="$sdk/platform-tools/adb.exe"
$avdName='Cantina_Leve_API_36'
& "$PSScriptRoot/Configurar-Emulador.ps1" -SdkPath $sdk
$serial=$null
$devices=& $adb devices
foreach($line in $devices){
    if($line -match '^(emulator-\d+)\s+device$'){
        $candidate=$Matches[1]
        $name=(& $adb -s $candidate emu avd name | Select-Object -First 1)
        if($name -eq $avdName){$serial=$candidate;break}
        throw 'Feche o outro emulador antes de abrir o Cantina Leve, para evitar falta de memória.'
    }
}
if(!$serial){
    # Pre-authorize ADB for this local test emulator; physical USB devices are unaffected.
    $emulatorProcess=Start-Process -FilePath "$sdk/emulator/emulator.exe" -ArgumentList '-avd',$avdName,'-no-snapshot','-no-audio','-gpu','swiftshader','-skip-adb-auth' -WindowStyle Hidden -PassThru -RedirectStandardOutput "$env:TEMP/cantina-emulador.log" -RedirectStandardError "$env:TEMP/cantina-emulador-error.log"
}
Write-Host 'Aguardando o Android iniciar. Na primeira vez pode levar alguns minutos.'
$deadline=(Get-Date).AddMinutes(5)
$ready=$false
do {
    if($emulatorProcess -and $emulatorProcess.HasExited){throw 'A janela do emulador foi encerrada. Abra o atalho novamente e aguarde a inicialização.'}
    if(!$serial){
        foreach($line in (& $adb devices)){
            if($line -match '^(emulator-\d+)\s+device$'){
                $candidate=$Matches[1]
                if((& $adb -s $candidate emu avd name | Select-Object -First 1) -eq $avdName){$serial=$candidate;break}
            }
        }
    }
    if($serial){$ready=((& $adb -s $serial shell getprop sys.boot_completed 2>$null) -join '').Trim() -eq '1'}
    if(!$ready){Start-Sleep -Seconds 3}
}while(!$ready -and (Get-Date) -lt $deadline)
if(!$ready){throw 'O Android não terminou de iniciar. Consulte os logs cantina-emulador em TEMP.'}
foreach($setting in @('window_animation_scale','transition_animation_scale','animator_duration_scale')){& $adb -s $serial shell settings put global $setting 0}
& $adb -s $serial reverse tcp:8000 tcp:8000
$apk="$PSScriptRoot/app/build/outputs/apk/debug/app-debug.apk"
if(Test-Path -LiteralPath $apk){
    & $adb -s $serial install -r $apk
    if($LASTEXITCODE -ne 0){throw 'Não foi possível instalar o APK. Compile o app no Android Studio.'}
    & $adb -s $serial shell am start -W -n com.example.cantina/.TelaInicialActivity
    if($LASTEXITCODE -ne 0){throw 'Não foi possível abrir o app.'}
}
Write-Host 'Android pronto. No Android Studio, selecione Cantina Leve API 36 e clique em Run.'
