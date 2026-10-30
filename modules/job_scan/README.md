# Job scan

A saved job search finds job posts and matches each one with its CV. Posts get in three ways:

- **A scan**: one call to the model with its web search tool, on the search owner's key.
- **By hand**: a link or a pasted shortlist on the search's page, or `scan.php --add`.
- **From another tool**: `job_scan/api` with the search's token, or the ChatGPT connector at `job_scan/mcp/{token}`.

## Scanning by hand

"Scan now" on the search's page, or from the project folder:

```
php modules/job_scan/scan.php --search=3
```

To add a shortlist, such as the JSON array ChatGPT wrote, paste it under "Add a shortlist" on the page, or:

```
php modules/job_scan/scan.php --search=3 --add=shortlist.json
```

Set a search's "How often" to "Only when I scan it" and nothing else scans it.

## Scanning on a schedule (optional)

`php modules/job_scan/scan.php` scans the searches that are due by their "How often", runs the matches, and exits. No worker has to be running. Run it hourly: a search is only scanned when it is due.

Windows Task Scheduler, in PowerShell, catching up on runs missed while the PC was off or asleep:

```powershell
$action = New-ScheduledTaskAction -Execute php.exe -Argument 'modules\job_scan\scan.php' -WorkingDirectory 'C:\path\to\recruiter'
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 1)
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours 1)
Register-ScheduledTask -TaskName 'Recruiter job scan' -Action $action -Settings $settings -Trigger $trigger
```

`php.exe` has to be on the PATH of the account the task runs as; otherwise give its full path. Remove the task with `Unregister-ScheduledTask -TaskName 'Recruiter job scan'`.

cron:

```
0 * * * * cd /path/to/recruiter && php modules/job_scan/scan.php >> /var/log/recruiter-job-scan.log 2>&1
```

A server with a worker always running (`php modules/queue/runtime.php work`) needs neither: the queue looks for due searches itself every 15 minutes, and the two don't get in each other's way, because a search's scan is only queued once.
