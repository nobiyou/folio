$ErrorActionPreference = "Stop"

Write-Host "======================================="
Write-Host "Folio Release Package"
Write-Host "======================================="

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$zip = Join-Path $root ("dist_pkg\folio-full-workingtree-{0}.zip" -f $ts)

if (!(Test-Path (Join-Path $root "dist_pkg"))) {
    New-Item -ItemType Directory -Path (Join-Path $root "dist_pkg") -Force | Out-Null
}

$exclude = @(".git", "node_modules", "dist", "dist_pkg")

if (Test-Path $zip) {
    Remove-Item $zip -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$sourceFiles = Get-ChildItem -Path $root -Recurse -File -Force | Where-Object {
    $full = $_.FullName
    foreach ($d in $exclude) {
        if ($full -like "*\$d\*") { return $false }
    }
    return $true
}

$zipArchive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $sourceFiles) {
        $relativePath = $file.FullName.Substring($root.Length).TrimStart('\', '/')
        $entryName = $relativePath -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zipArchive,
            $file.FullName,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
} finally {
    $zipArchive.Dispose()
}

$pkg = Get-Item $zip
Write-Host "Package created:"
Write-Host " - Path: $($pkg.FullName)"
Write-Host " - Size: $($pkg.Length) bytes"
Write-Host " - Time: $($pkg.LastWriteTime)"

exit 0
