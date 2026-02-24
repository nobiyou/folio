$ErrorActionPreference = "Stop"

Write-Host "======================================="
Write-Host "Folio Release Gate"
Write-Host "======================================="

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

$excludeDirs = @("dist", "dist_pkg", "node_modules", ".git")
$phpFiles = Get-ChildItem -Path $root -Recurse -File -Filter *.php | Where-Object {
    $full = $_.FullName
    foreach ($d in $excludeDirs) {
        if ($full -like "*\$d\*") { return $false }
    }
    return $true
}

Write-Host "[1/4] PHP syntax check..."
$phpFailed = @()
foreach ($f in $phpFiles) {
    $output = & php -n -l $f.FullName 2>&1
    if ($LASTEXITCODE -ne 0 -or ($output -match "Parse error|Fatal error")) {
        $phpFailed += $f.FullName
    }
}
if ($phpFailed.Count -gt 0) {
    Write-Host "PHP syntax failed:"
    $phpFailed | ForEach-Object { Write-Host " - $_" }
    exit 1
}
Write-Host "PHP syntax check passed."

Write-Host "[2/4] Key JS syntax check..."
$jsTargets = @(
    "assets/js/folio-core.js",
    "assets/js/frontend-components.js",
    "assets/js/notifications.js",
    "assets/js/premium-content.js",
    "assets/js/admin-options.js"
)
$jsFailed = @()
foreach ($rel in $jsTargets) {
    $p = Join-Path $root $rel
    if (Test-Path $p) {
        & node --check $p 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) {
            $jsFailed += $rel
        }
    }
}
if ($jsFailed.Count -gt 0) {
    Write-Host "JS syntax failed:"
    $jsFailed | ForEach-Object { Write-Host " - $_" }
    exit 1
}
Write-Host "Key JS syntax check passed."

Write-Host "[3/4] I18N residual scan..."
$pyScript = @'
import re
import sys
from pathlib import Path

root = Path(".")
exclude = {"dist", "dist_pkg", "docs", "languages", ".git", "node_modules"}
exts = {".php", ".js"}
pat = re.compile(r"(['\"]).*?[\u4e00-\u9fff].*?\1")

rows = []
for p in root.rglob("*"):
    if any(part in exclude for part in p.parts):
        continue
    if p.suffix.lower() not in exts or p.name.endswith(".min.js"):
        continue
    txt = p.read_text(encoding="utf-8", errors="ignore").splitlines()
    for i, line in enumerate(txt, 1):
        s = line.strip()
        if s.startswith("//") or s.startswith("*") or s.startswith("/*") or s.startswith("<!--"):
            continue
        # Keep AI prompt corpus out of gate by project decision.
        if p.name == "class-ai-content-generator.php" and 330 <= i <= 560:
            continue
        if pat.search(line):
            rows.append((str(p), i, s))

if rows:
    print("I18N residual strings found:")
    for fp, ln, s in rows[:50]:
        print(f"{fp}:{ln}:{s}")
    print(f"TOTAL={len(rows)}")
    sys.exit(1)

print("I18N residual scan passed.")
'@

$tmpPy = [System.IO.Path]::GetTempFileName() + ".py"
Set-Content -Path $tmpPy -Value $pyScript -Encoding UTF8
& python $tmpPy
$pyExit = $LASTEXITCODE
Remove-Item -Path $tmpPy -Force -ErrorAction SilentlyContinue
if ($pyExit -ne 0) {
    exit 1
}

Write-Host "[4/4] Git status snapshot..."
& git status --short

Write-Host "======================================="
Write-Host "Release gate passed."
Write-Host "======================================="
exit 0
