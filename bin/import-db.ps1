# Local dev: drop and recreate the recruiter database from db/schema.sql.
param(
    [string]$MysqlPath = "mysql",
    [string]$User = "root",
    [string]$Password = ""
)

$Schema = Join-Path $PSScriptRoot "..\db\schema.sql"

function Test-Command ($Name) {
    return (Get-Command $Name -ErrorAction SilentlyContinue)
}

$MysqlExe = $MysqlPath
if (-not (Test-Command $MysqlExe)) {
    if ($MysqlPath -eq "mysql" -and (Test-Command "mariadb")) {
        $MysqlExe = "mariadb"
    } else {
        # Check common locations (Herd, MariaDB and MySQL installers)
        $CommonPaths = @(
            "$env:USERPROFILE\.config\herd\bin\mysql.exe",
            "$env:USERPROFILE\.config\herd\bin\mariadb.exe",
            "C:\Program Files\MariaDB*\bin\mariadb.exe",
            "C:\Program Files\MariaDB*\bin\mysql.exe",
            "C:\Program Files\MySQL\MySQL Server*\bin\mysql.exe"
        )

        $Found = $false
        foreach ($Path in $CommonPaths) {
            $ResolvedPaths = Resolve-Path $Path -ErrorAction SilentlyContinue
            foreach ($RP in @($ResolvedPaths)) {
                if ($RP -and (Test-Path $RP.Path)) {
                    $MysqlExe = $RP.Path
                    $Found = $true
                    break
                }
            }
            if ($Found) {
                Write-Host "Using $MysqlExe" -ForegroundColor Green
                break
            }
        }

        if (-not $Found) {
            Write-Error "MySQL/MariaDB client not found in PATH or common locations. Pass its path with -MysqlPath."
            exit 1
        }
    }
}

Write-Host "Recreating recruiter from $Schema..." -NoNewline

$ClientArgs = @("-h", "127.0.0.1", "-u", $User)
if ($Password -ne "") { $ClientArgs += "-p$Password" }

$Content = "DROP DATABASE IF EXISTS recruiter;`nCREATE DATABASE recruiter CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;`nUSE recruiter;`n" + (Get-Content $Schema -Raw)
$Output = $Content | & $MysqlExe @ClientArgs 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host " [FAILED]" -ForegroundColor Red
    Write-Host $Output -ForegroundColor Yellow
    exit 1
}

Write-Host " [OK]" -ForegroundColor Green
