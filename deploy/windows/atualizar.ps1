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

    # O diretório public\storage costuma ser um junction/symlink local do Laravel (storage:link).
    # O scp do Windows pode falhar ao copiar esse reparse point, então convertemos para pasta real
    # antes do upload. No servidor o storage:link é recriado durante deploy/scripts/atualizar.sh.
    $publicStorage = Join-Path $LocalRoot "public\storage"
    if (Test-Path $publicStorage) {
        $item = Get-Item $publicStorage -Force
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            Write-Host "  Ajustando public\storage (junction -> pasta real)..." -ForegroundColor DarkYellow
            Remove-Item $publicStorage -Force
            New-Item -ItemType Directory -Force -Path $publicStorage | Out-Null
        }
    }

    # -tt força pseudo-TTY: necessário quando o sudo pede senha via SSH
    ssh -tt $Remote "rm -rf $Staging; mkdir -p $Staging"

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

    $copyCmd = "sudo cp -a $Staging/. $RemoteDir/; sudo chown -R www-data:www-data $RemoteDir/storage $RemoteDir/bootstrap/cache; sudo chmod -R ug+rwx $RemoteDir/storage $RemoteDir/bootstrap/cache; sudo chmod -R u+rwX,go+rX $RemoteDir/deploy; rm -rf $Staging"
    ssh -tt $Remote $copyCmd

    Write-Host "  (.env NAO e enviado - fica so no servidor)" -ForegroundColor DarkGray
}
else {
    Write-Host "[1/2] Pulando envio de arquivos (-SomenteServidor)" -ForegroundColor DarkGray
}

Write-Host "[2/2] Rodando atualizar.sh na VM..." -ForegroundColor Yellow
$updateCmd = "cd $RemoteDir; sudo bash deploy/scripts/atualizar.sh"
ssh -tt $Remote $updateCmd

Write-Host ""
Write-Host "Pronto: http://192.168.5.6" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Cyan
