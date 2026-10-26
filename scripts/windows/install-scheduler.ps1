<#
    Registers one Windows scheduled task that runs "php artisan schedule:run"
    every minute. Laravel then runs, at their own times:
      - queue:work            every minute   (sends queued SMS)
      - mjeshi:backup         01:00          (daily backup)
      - mjeshi:enforce-membership 06:00      (members who stopped paying)
      - mjeshi:send-reminders 08:00          (dues and contribution reminders)

    Run once, in PowerShell opened "As Administrator":
      powershell -ExecutionPolicy Bypass -File scripts\windows\install-scheduler.ps1

    The task runs as SYSTEM, the same account as the WAMP Apache service,
    so files it writes in storage\ stay readable by the web site. It runs
    hidden, whether or not anyone is signed in to Windows.
#>
param(
    [string]$PhpPath = 'C:\wamp64\bin\php\php8.4.15\php.exe',
    [string]$ProjectPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [string]$TaskName = 'Mjeshi Scheduler'
)

$ErrorActionPreference = 'Stop'

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    throw 'Fungua PowerShell kama Administrator (Run as administrator), kisha endesha tena.'
}
if (-not (Test-Path $PhpPath)) {
    throw "PHP haikupatikana: $PhpPath. Tumia -PhpPath 'C:\wamp64\bin\php\phpX.Y.Z\php.exe'."
}
$artisan = Join-Path $ProjectPath 'artisan'
if (-not (Test-Path $artisan)) {
    throw "artisan haikupatikana ndani ya $ProjectPath."
}

$action = New-ScheduledTaskAction -Execute $PhpPath -Argument "`"$artisan`" schedule:run" -WorkingDirectory $ProjectPath
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10) `
    -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description 'Mfumo wa Kikundi: php artisan schedule:run kila dakika (SMS, ukaguzi, ukumbusho, backup).' -Force | Out-Null

Start-ScheduledTask -TaskName $TaskName
Start-Sleep -Seconds 8
$info = Get-ScheduledTaskInfo -TaskName $TaskName

Write-Host ''
Write-Host "Task '$TaskName' imesajiliwa." -ForegroundColor Green
Write-Host "  PHP:        $PhpPath"
Write-Host "  Mradi:      $ProjectPath"
Write-Host "  Mara ya mwisho: $($info.LastRunTime)  matokeo: $($info.LastTaskResult)  (0 = sawa, 267009 = bado inaendelea)"
Write-Host "  Inayofuata: $($info.NextRunTime)"
Write-Host ''
Write-Host 'Kuangalia ratiba:  php artisan schedule:list'
Write-Host 'Kuiondoa:          powershell -ExecutionPolicy Bypass -File scripts\windows\uninstall-scheduler.ps1'
