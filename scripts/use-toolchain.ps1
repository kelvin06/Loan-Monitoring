$ErrorActionPreference = 'Stop'
$php84 = 'C:\laragon\bin\php\php-8.4.5-Win32-vs17-x64'
if (-not (Test-Path (Join-Path $php84 'php.exe'))) { throw "PHP 8.4.5 is not installed at $php84" }
$env:Path = "$php84;$env:Path"
$phpOutput = (& php -v | Select-Object -First 1)
$phpVersion = [regex]::Match($phpOutput, 'PHP (\d+\.\d+)').Groups[1].Value
$nodeVersion = (& node -p 'process.versions.node').Trim()
if ($phpVersion -ne '8.4') { throw "Expected PHP 8.4, found $phpVersion" }
if ([int]($nodeVersion.Split('.')[0]) -lt 26) { throw "Expected Node.js 26+, found $nodeVersion" }
Write-Host "Toolchain ready: $phpOutput, Node.js $nodeVersion"
