# Shared settings for pull-page.ps1 / push-page.ps1. Dot-sourced, not run directly.
# Talks ONLY to Hayley's Multidev. The credential is an Editor-role Application Password
# stored encrypted (Windows DPAPI, readable only by this Windows user) in the file below.

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$Script:WpBase   = 'https://hayley-canine.pantheonsite.io'
$Script:CredFile = Join-Path $env:USERPROFILE '.wp-hayley-credential.xml'
$Script:PagesDir = Join-Path (Split-Path $PSScriptRoot -Parent) 'pages'

# Safety net: refuse to run against anything but the Multidev.
if ($Script:WpBase -notmatch '^https://hayley-[a-z0-9-]+\.pantheonsite\.io$') {
    throw "WpBase '$($Script:WpBase)' is not the hayley Multidev. Refusing to continue."
}

function Get-WpAuthHeader {
    if (-not (Test-Path $Script:CredFile)) {
        throw "No saved credential. Run .claude/scripts/save-wp-credential.ps1 yourself first (not via Claude)."
    }
    $cred  = Import-Clixml $Script:CredFile
    $plain = $cred.GetNetworkCredential().Password
    $pair  = '{0}:{1}' -f $cred.UserName, $plain
    $token = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($pair))
    return @{ Authorization = "Basic $token" }
}

function Invoke-Wp {
    param(
        [Parameter(Mandatory)] [string] $Path,
        [string] $Method = 'GET',
        $Body = $null
    )
    $uri    = $Script:WpBase.TrimEnd('/') + '/wp-json/wp/v2/' + $Path.TrimStart('/')
    $params = @{ Uri = $uri; Method = $Method; Headers = (Get-WpAuthHeader); UseBasicParsing = $true }
    if ($null -ne $Body) {
        $json = $Body | ConvertTo-Json -Depth 10
        $params.Body        = [Text.Encoding]::UTF8.GetBytes($json)
        $params.ContentType = 'application/json; charset=utf-8'
    }
    try {
        $resp = Invoke-WebRequest @params
    } catch {
        $msg = $_.Exception.Message
        if ($_.ErrorDetails -and $_.ErrorDetails.Message) { $msg += "`n" + $_.ErrorDetails.Message }
        throw "WordPress request failed ($Method $uri): $msg"
    }
    # Decode as UTF-8 explicitly (Windows PowerShell 5.1 sometimes guesses wrong).
    $text = [Text.Encoding]::UTF8.GetString($resp.RawContentStream.ToArray())
    return $text | ConvertFrom-Json
}

function Write-Utf8NoBom([string] $Path, [string] $Text) {
    [IO.File]::WriteAllText($Path, $Text, (New-Object Text.UTF8Encoding($false)))
}
