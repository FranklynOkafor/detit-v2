# Build a clean WordPress.org candidate ZIP without Composer or external tools.
# Run from anywhere: powershell -ExecutionPolicy Bypass -File .\tools\build-release.ps1
#
# Why allowlist? It prevents smoke scripts, developer secrets, Git metadata,
# local builds and dependencies from reaching customers.

[CmdletBinding()]
param(
    [string] $Version = '2.0.0'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$slug = 'detit-product-content-generator-for-woocommerce'
$entryFile = Join-Path $root 'detit.php'
$readmeFile = Join-Path $root 'readme.txt'
$outputDirectory = Join-Path $root 'dist'
$archivePath = Join-Path $outputDirectory "detit-$Version.zip"

$pluginHeader = Get-Content -LiteralPath $entryFile -Raw
$readme = Get-Content -LiteralPath $readmeFile -Raw

if ($pluginHeader -notmatch '(?m)^\s*\*\s*Version:\s*([^\r\n]+)') {
    throw 'Missing Version header in detit.php.'
}
$pluginVersion = $Matches[1].Trim()

if ($readme -notmatch '(?m)^Stable tag:\s*([^\r\n]+)') {
    throw 'Missing Stable tag in readme.txt.'
}
$readmeVersion = $Matches[1].Trim()

if ($pluginVersion -ne $Version -or $readmeVersion -ne $Version) {
    throw "Version mismatch: ZIP $Version, plugin $pluginVersion, readme $readmeVersion."
}

$temp = Join-Path ([System.IO.Path]::GetTempPath()) ('detit-release-' + [Guid]::NewGuid().ToString('N'))
$package = Join-Path $temp $slug

try {
    New-Item -ItemType Directory -Path $package -Force | Out-Null

    foreach ($name in @('detit.php', 'readme.txt', 'uninstall.php')) {
        $source = Join-Path $root $name

        if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
            throw "Required release file missing: $name"
        }

        Copy-Item -LiteralPath $source -Destination $package
    }

    foreach ($name in @('src', 'assets')) {
        $source = Join-Path $root $name

        if (-not (Test-Path -LiteralPath $source -PathType Container)) {
            throw "Required release directory missing: $name"
        }

        Copy-Item -LiteralPath $source -Destination $package -Recurse
    }

    if (-not (Test-Path $outputDirectory)) {
        New-Item -ItemType Directory -Path $outputDirectory | Out-Null
    }

    if (Test-Path -LiteralPath $archivePath) {
        Remove-Item -LiteralPath $archivePath -Force
    }

    Compress-Archive -LiteralPath $package -DestinationPath $archivePath -CompressionLevel Optimal

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($archivePath)

    try {
        $names = @($zip.Entries | ForEach-Object { $_.FullName -replace '\\', '/' })

        foreach ($required in @(
            "$slug/detit.php",
            "$slug/readme.txt",
            "$slug/uninstall.php",
            "$slug/src/Plugin.php",
            "$slug/src/AI/WordPressAIClientGateway.php",
            "$slug/assets/js/product-generate.js"
        )) {
            if ($names -notcontains $required) {
                throw "Release ZIP is missing $required."
            }
        }

        $forbidden = @($names | Where-Object {
            $_ -match '/(tools|vendor|node_modules|\.git|\.github|docs)/' -or
            $_ -match '/(composer\.(json|lock)|\.env|\.log)$'
        })

        if ($forbidden.Count -gt 0) {
            throw "Unexpected developer files in ZIP: $($forbidden -join ', ')"
        }
    }
    finally {
        $zip.Dispose()
    }

    $size = (Get-Item -LiteralPath $archivePath).Length

    if ($size -gt 10MB) {
        throw 'Release ZIP is over WordPress.org recommended 10 MB submission limit.'
    }

    Write-Host 'RELEASE_PACKAGE_CONTENTS: PASS'
    Write-Host 'RELEASE_VERSION_MATCH: PASS'
    Write-Host 'RELEASE_DEVELOPER_FILES_EXCLUDED: PASS'
    Write-Host "RELEASE_ZIP: $archivePath"
    Write-Host "RELEASE_SIZE_BYTES: $size"
    Write-Host 'RESULT: PASS'
}
finally {
    if (Test-Path -LiteralPath $temp) {
        Remove-Item -LiteralPath $temp -Recurse -Force
    }
}
