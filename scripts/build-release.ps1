[CmdletBinding()]
param(
	[string]$Version = '1.6.5'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$dist = Join-Path $root 'dist'
$stage = Join-Path ([System.IO.Path]::GetTempPath()) ('seoistic-release-' + [guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $stage 'seoistic'

try {
	New-Item -ItemType Directory -Path $packageRoot, $dist -Force | Out-Null
	$excluded = @('.git', '.github', 'tests', 'node_modules', 'dist', '.env')
	Get-ChildItem -LiteralPath $root -Force |
		Where-Object { $excluded -notcontains $_.Name -and $_.Name -notlike '.env.*' } |
		ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination $packageRoot -Recurse -Force }

	$zip = Join-Path $dist ("seoistic-$Version.zip")
	if (Test-Path -LiteralPath $zip) {
		Remove-Item -LiteralPath $zip -Force
	}
	Compress-Archive -LiteralPath $packageRoot -DestinationPath $zip -CompressionLevel Optimal
	$hash = (Get-FileHash -LiteralPath $zip -Algorithm SHA256).Hash.ToLowerInvariant()
	Set-Content -LiteralPath (Join-Path $dist ("seoistic-$Version.zip.sha256")) -Value ("$hash  seoistic-$Version.zip") -Encoding ascii
	Get-ChildItem -LiteralPath $dist -Filter "seoistic-$Version*" | Select-Object Name, Length
}
finally {
	if (Test-Path -LiteralPath $stage) {
		Remove-Item -LiteralPath $stage -Recurse -Force
	}
}
