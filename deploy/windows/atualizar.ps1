# Atualiza o ERP na VM: envia codigo do PC + roda atualizar.sh
# Uso:
#   cd C:\Users\User\Documents\ERP_Acesso
#   powershell -ExecutionPolicy Bypass -File .\deploy\windows\atualizar.ps1
#
# Opcional: -SomenteServidor  (sem enviar arquivos)

param(
    [switch]$SomenteServidor
)

$ErrorActionPreference = "Stop"

$LocalRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$Remote    = "jose@192.168.5.6"
$RemoteDir = "/var/www/ERP-Acesso"
$Staging   = "/tmp/erp-acesso-sync"

$Pastas = @(
    "app", "bootstrap", "config", "database", "lang", "public",
    "resources", "routes", "deploy", "stack"
)

$Arquivos = @(
    "artisan", "composer.json", "composer.lock",
    "package.json", "package-lock.json", "vite.config.js"
)

Write-Host "============================================" -ForegroundColor Cyan
Write-Host " Atualizar ERP -> $RemoteDir" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan

if (-not $SomenteServidor) {
    Write-Host "[1/2] Enviando codigo..." -ForegroundColor Yellow

    ssh $Remote "rm -rf $Staging; mkdir -p $Staging"

    foreach ($pasta in $Pastas) {
        $origem = Join-Path $LocalRoot $pasta
        if (Test-Path $origem) {
            Write-Host "  -> $pasta"
            scp -r $origem "${Remote}:${Staging}/"
        }
    }

    foreach ($arquivo in $Arquivos) {
        $origem = Join-Path $LocalRoot $arquivo
        if (Test-Path $origem) {
            Write-Host "  -> $arquivo"
            scp $origem "${Remote}:${Staging}/"
        }
    }

    $copyCmd = "sudo cp -a $Staging/. $RemoteDir/; sudo chown -R www-data:www-data $RemoteDir; sudo chmod -R ug+rwx $RemoteDir/storage $RemoteDir/bootstrap/cache; rm -rf $Staging"
    ssh $Remote $copyCmd

    Write-Host "  (.env NAO e enviado - fica so no servidor)" -ForegroundColor DarkGray
}
else {
    Write-Host "[1/2] Pulando envio de arquivos (-SomenteServidor)" -ForegroundColor DarkGray
}

Write-Host "[2/2] Rodando atualizar.sh na VM..." -ForegroundColor Yellow
$updateCmd = "cd $RemoteDir; sudo bash deploy/scripts/atualizar.sh"
ssh $Remote $updateCmd

Write-Host ""
Write-Host "Pronto: http://192.168.5.6" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Cyan
