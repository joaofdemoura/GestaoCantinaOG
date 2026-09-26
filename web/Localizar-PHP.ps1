$ErrorActionPreference='Stop'
if($env:CANTINA_PHP){
    if(!(Test-Path -LiteralPath $env:CANTINA_PHP -PathType Leaf)){throw 'CANTINA_PHP não aponta para um executável PHP.'}
    return $env:CANTINA_PHP
}
$command=Get-Command php.exe -ErrorAction SilentlyContinue
if($command){return $command.Source}
$versions=Get-ChildItem -LiteralPath 'C:/laragon/bin/php' -Directory -ErrorAction SilentlyContinue | ForEach-Object {
    if($_.Name -match '^php-(\d+\.\d+\.\d+)'){
        $candidate=Join-Path $_.FullName 'php.exe'
        if(Test-Path -LiteralPath $candidate){[pscustomobject]@{Version=[version]$Matches[1];Path=$candidate}}
    }
} | Sort-Object Version -Descending
$choice=$versions | Select-Object -First 1
if(!$choice){throw 'Instale PHP 8.3+ pelo Laragon ou defina CANTINA_PHP com o caminho de php.exe.'}
return $choice.Path