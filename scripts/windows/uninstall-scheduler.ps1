<#
    Removes the task created by install-scheduler.ps1. Run "As Administrator":
      powershell -ExecutionPolicy Bypass -File scripts\windows\uninstall-scheduler.ps1
#>
param([string]$TaskName = 'Mjeshi Scheduler')

$ErrorActionPreference = 'Stop'

if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "Task '$TaskName' imeondolewa. Kazi za kila siku hazitajiendesha tena." -ForegroundColor Yellow
} else {
    Write-Host "Hakuna task inayoitwa '$TaskName'."
}
