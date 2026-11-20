<#
.SYNOPSIS
  Download a page's Gutenberg block markup from the hayley Multidev, or list pages.
.EXAMPLE
  .\pull-page.ps1 -List
  .\pull-page.ps1 -Id 123
  .\pull-page.ps1 -Id 45 -Type posts
#>
param(
    [int] $Id,
    [switch] $List,
    [ValidateSet('pages', 'posts')] [string] $Type = 'pages'
)
. (Join-Path $PSScriptRoot 'wp-common.ps1')

if ($List) {
    $page = 1
    do {
        $items = Invoke-Wp "$($Type)?context=edit&per_page=100&page=$page&status=publish,draft,pending,private&_fields=id,status,slug,title"
        foreach ($i in $items) {
            '{0,6}  {1,-8} {2,-40} {3}' -f $i.id, $i.status, $i.slug, $i.title.raw
        }
        $page++
    } while ($items.Count -eq 100)
    return
}

if (-not $Id) { throw 'Give -Id <number>, or -List to see pages.' }

$item = Invoke-Wp "$Type/$($Id)?context=edit&_fields=id,slug,status,title,content,modified_gmt,link"
if (-not (Test-Path $Script:PagesDir)) { New-Item -ItemType Directory -Path $Script:PagesDir | Out-Null }

$slug = if ($item.slug) { $item.slug } else { 'untitled' }
$file = Join-Path $Script:PagesDir ('{0}-{1}.html' -f $item.id, $slug)
Write-Utf8NoBom $file $item.content.raw

# Remember what we pulled, so push-page can warn if someone edited the page in the meantime.
$meta = @{ id = $item.id; type = $Type; modified_gmt = $item.modified_gmt; file = $file } | ConvertTo-Json
Write-Utf8NoBom ($file + '.meta.json') $meta

"Pulled $Type $($item.id) '$($item.title.raw)' ($($item.status))"
"  -> $file"
"  link: $($item.link)"
