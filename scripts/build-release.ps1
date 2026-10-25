[CmdletBinding()]
param(
	[string]$Version = '1.7.3'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$dist = Join-Path $root 'dist'
$stage = Join-Path ([System.IO.Path]::GetTempPath()) ('seoistic-release-' + [guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $stage 'seoistic'

# Runtime allowlist — identical to the CI "runtime-only distribution" step in
# .github/workflows/php.yml. Build tooling (scripts/, tests/, docs/) must never
# ship inside the plugin zip: WordPress Plugin Check flags application files.
$runtimeFiles = @(
	'assets', 'lib', 'src', 'knowledge', 'languages',
	'seoistic.php', 'uninstall.php', 'LICENSE', 'readme.txt',
	'CHANGELOG.md', 'README.md', 'composer.json'
)

try {
	New-Item -ItemType Directory -Path $packageRoot, $dist -Force | Out-Null
	foreach ( $name in $runtimeFiles ) {
		$source = Join-Path $root $name
		if ( Test-Path -LiteralPath $source ) {
			Copy-Item -LiteralPath $source -Destination $packageRoot -Recurse -Force
		}
	}

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
