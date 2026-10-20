# Lanza scripts/deploy.sh con Git Bash desde PowerShell (guía: scripts/README.md).
# En Windows, "bash" a secas abre WSL (System32\bash.exe), no Git Bash.
#   .\scripts\deploy.ps1 [--check | --apply | --rollback | --init] [--seed=Clase] ...
$gitBash = Join-Path $env:ProgramFiles 'Git\bin\bash.exe'
if (-not (Test-Path $gitBash)) {
  $git = (Get-Command git -ErrorAction SilentlyContinue).Source
  if ($git) { $gitBash = Join-Path (Split-Path (Split-Path $git)) 'bin\bash.exe' }
}
if (-not (Test-Path $gitBash)) { Write-Error 'No se encontró Git Bash. Instala Git for Windows.'; exit 1 }
& $gitBash (Join-Path $PSScriptRoot 'deploy.sh') @args
exit $LASTEXITCODE
