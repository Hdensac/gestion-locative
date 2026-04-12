<#
Script: setup-vhost.ps1
But: ajouter une entrée hosts et créer un vhost Apache pour Laragon
Usage (PowerShell Admin):
  powershell -ExecutionPolicy Bypass -File .\scripts\setup-vhost.ps1

Param(
    [string]$ProjectPath = "C:\laragon\www\gestion-locative",
    [string]$HostName = "gestion-locative.test",
    [string]$LaragonPath = "C:\laragon"
)

function Assert-Admin {
    $isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $isAdmin) {
        Write-Error "Ce script doit être exécuté en tant qu'administrateur. Ouvrez PowerShell en mode administrateur et relancez-le."
        exit 1
    }
}

Assert-Admin

# Vérifier que le projet existe
if (-not (Test-Path -Path $ProjectPath)) {
    Write-Error "Le chemin du projet '$ProjectPath' n'existe pas. Vérifiez le paramètre -ProjectPath."
    exit 1
}

$hostsPath = Join-Path -Path $env:windir -ChildPath 'System32\drivers\etc\hosts'
$entry = "127.0.0.1 `t$HostName"

if (-not (Select-String -Path $hostsPath -Pattern [regex]::Escape($HostName) -Quiet)) {
    "`n# Ajouté par setup-vhost.ps1 - $(Get-Date)" | Out-File -FilePath $hostsPath -Encoding ASCII -Append
    $entry | Out-File -FilePath $hostsPath -Encoding ASCII -Append
    Write-Host "Entrée ajoutée au fichier hosts : $entry"
} else {
    Write-Host "Le fichier hosts contient déjà une entrée pour $HostName"
}

# Déterminer le dossier des sites-enabled de Laragon
$sitesEnabled = Join-Path -Path $LaragonPath -ChildPath 'etc\apache2\sites-enabled'
if (-not (Test-Path -Path $sitesEnabled)) {
    New-Item -ItemType Directory -Path $sitesEnabled -Force | Out-Null
}

$vhostFile = Join-Path -Path $sitesEnabled -ChildPath ("$HostName.conf")
$vhostContent = @"
<VirtualHost *:80>
    ServerName $HostName
    DocumentRoot "$ProjectPath"
    <Directory "$ProjectPath">
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog "${LaragonPath}/log/apache/$HostName-error.log"
    CustomLog "${LaragonPath}/log/apache/$HostName-access.log" common
</VirtualHost>
"@

Set-Content -Path $vhostFile -Value $vhostContent -Encoding UTF8
Write-Host "Fichier vhost créé : $vhostFile"

Write-Host "Opération terminée. Redémarrez Laragon (Menu → Restart) ou redémarrez Apache pour appliquer le vhost."
Write-Host "Ouvrez ensuite : http://$HostName"

# Fin du script
