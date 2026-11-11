$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

function Invoke-Compose {
    & docker compose @args
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose failed with exit code $LASTEXITCODE" }
}

if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
Invoke-Compose build app
$taskEnvironment = Get-Content -LiteralPath '.env' -Raw
if ($taskEnvironment -match '(?m)^APP_KEY=\s*$') {
    $taskKey = (& docker compose run --rm --no-deps app php artisan key:generate --show | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $taskKey -notmatch '^base64:[A-Za-z0-9+/=]+$') { throw 'Unable to generate APP_KEY' }
    $taskEnvironment = [regex]::Replace($taskEnvironment, '(?m)^APP_KEY=[^\r\n]*', "APP_KEY=$taskKey")
    [IO.File]::WriteAllText((Join-Path $PWD '.env'), $taskEnvironment, [Text.UTF8Encoding]::new($false))
}
Invoke-Compose run --rm app php artisan migrate --force
Invoke-Compose up -d --wait app worker scheduler
Write-Host 'API is ready. The following local demo token expires in seven days:'
Invoke-Compose run --rm --no-deps app php artisan demo:token
