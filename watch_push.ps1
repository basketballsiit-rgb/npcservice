# watch_push.ps1
# Script to monitor local files and auto-commit & push to GitHub

$path_to_watch = "c:\xampp\htdocs\npcservice"
$filter = "*.*"

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $path_to_watch
$watcher.Filter = $filter
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true

$action = {
    $path = $Event.SourceEventArgs.FullPath
    $changeType = $Event.SourceEventArgs.ChangeType
    $name = $Event.SourceEventArgs.Name

    # Exclude .git, logs, and temp files
    if ($name -match "\\\.git" -or $name -match "\.git\\" -or $name -eq "deploy_log.txt" -or $name -eq "watch_push.ps1") {
        return
    }

    Write-Host "Change detected in $name ($changeType). Waiting for file write to complete..." -ForegroundColor Cyan
    Start-Sleep -Milliseconds 1500 # Wait to ensure file write is finished

    # Check if there are actual changes in git
    $status = git status --porcelain
    if ($status) {
        Write-Host "Changes detected. Committing and pushing..." -ForegroundColor Yellow
        git add .
        git commit -m "Auto-commit: change in $name at $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        git push
        Write-Host "Successfully pushed to GitHub!" -ForegroundColor Green
    } else {
        Write-Host "No changes to commit." -ForegroundColor Gray
    }
}

# Register events
$handlers = @()
$handlers += Register-ObjectEvent $watcher "Changed" -Action $action
$handlers += Register-ObjectEvent $watcher "Created" -Action $action
$handlers += Register-ObjectEvent $watcher "Deleted" -Action $action
$handlers += Register-ObjectEvent $watcher "Renamed" -Action $action

Write-Host "--- Monitoring $path_to_watch for changes ---" -ForegroundColor Green
Write-Host "The script will automatically commit and push any changes you make in real-time." -ForegroundColor Green
Write-Host "Press Ctrl+C to exit and stop monitoring." -ForegroundColor Yellow

try {
    while ($true) {
        Start-Sleep -Seconds 1
    }
} finally {
    # Clean up event registrations on exit
    foreach ($handler in $handlers) {
        Unregister-Event -SourceIdentifier $handler.Name
    }
    $watcher.Dispose()
    Write-Host "Stopped monitoring." -ForegroundColor Red
}
