<#
.SYNOPSIS
  Run this YOURSELF (not through Claude) once, to store the Multidev Application Password.
  It is encrypted with Windows DPAPI, so only your Windows account on this computer can use it.
#>
$ErrorActionPreference = 'Stop'
$path = Join-Path $env:USERPROFILE '.wp-hayley-credential.xml'

Write-Host 'Enter the WordPress USERNAME of the Editor account on the hayley Multidev,'
Write-Host 'and the APPLICATION PASSWORD (not the normal login password) as the password.'
$cred = Get-Credential -Message 'hayley Multidev: username + Application Password'
$cred | Export-Clixml -Path $path
Write-Host "Saved to $path"
