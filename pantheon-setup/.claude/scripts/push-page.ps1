<#
.SYNOPSIS
  Upload edited block markup (from pull-page.ps1) back to the hayley Multidev.
.EXAMPLE
  .\push-page.ps1 -Id 123
  .\push-page.ps1 -Id 123 -Force   # overwrite even if the page changed since you pulled it
#>
param(
    [Parameter(Mandatory)] [int] $Id,
    [ValidateSet('pages', 'posts')] [string] $Type = 'pages',
    [switch] $Force
)
. (Join-Path $PSScriptRoot 'wp-common.ps1')

$file = Get-ChildItem -Path $Script:PagesDir -Filter ('{0}-*.html' -f $Id) -ErrorAction SilentlyContinue |
        Select-Object -First 1
if (-not $file) { throw "No pulled file for id $Id in $($Script:PagesDir). Run pull-page.ps1 -Id $Id first." }

$content = [IO.File]::ReadAllText($file.FullName, [Text.Encoding]::UTF8)

# Basic block-markup sanity check: opening and closing block comments should balance.
$opens  = ([regex]::Matches($content, '<!--\s+wp:[a-z0-9/-]+(\s+\{.*?\})?\s+-->')).Count
$closes = ([regex]::Matches($content, '<!--\s+/wp:[a-z0-9/-]+\s+-->')).Count
if ($opens -ne $closes) {
    throw "Block markup looks unbalanced ($opens opening vs $closes closing block comments). Not pushing."
}

# Conflict check: has the page been edited on the Multidev since we pulled it?
$metaFile = $file.FullName + '.meta.json'
if ((Test-Path $metaFile) -and -not $Force) {
    $meta    = Get-Content $metaFile -Raw | ConvertFrom-Json
    $current = Invoke-Wp "$Type/$($Id)?context=edit&_fields=modified_gmt"
    if ($current.modified_gmt -ne $meta.modified_gmt) {
        throw "Page $Id was changed on the site since you pulled it ($($meta.modified_gmt) -> $($current.modified_gmt)). Pull again, or use -Force."
    }
}

$result = Invoke-Wp -Path "$Type/$Id" -Method 'POST' -Body @{ content = $content }

# Refresh the stored timestamp so the next push compares against this version.
$meta = @{ id = $result.id; type = $Type; modified_gmt = $result.modified_gmt; file = $file.FullName } | ConvertTo-Json
Write-Utf8NoBom $metaFile $meta

"Pushed $Type $Id ($($result.status)). Check it: $($result.link)"
"Editor: $($Script:WpBase)/wp-admin/post.php?post=$Id&action=edit"
