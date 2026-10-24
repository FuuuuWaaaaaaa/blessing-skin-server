$ErrorActionPreference = 'Stop'

# Keep the Windows entry point aligned with the GitHub release workflow.
bash (Join-Path $PSScriptRoot 'release.sh')
