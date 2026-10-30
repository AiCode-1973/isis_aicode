<#
  Cria ou atualiza um acesso ao painel da plataforma (https://isis.aicode.dev.br/plataforma)
  direto no banco de produção, a partir deste computador. A senha é digitada sem aparecer na tela.

  Uso:  powershell -ExecutionPolicy Bypass -File bin\plataforma-admin-producao.ps1 email@dominio "Nome Completo"
#>
param(
    [Parameter(Mandatory = $true)][string]$Email,
    [Parameter(Mandatory = $true)][string]$Nome
)
$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $PSScriptRoot

$php = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Recurse -Filter php.exe -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -like '*PHP.PHP*8.3*' } | Select-Object -First 1 -ExpandProperty FullName
if (-not $php) { throw 'PHP 8.3 do winget não encontrado (winget install PHP.PHP.NTS.8.3).' }

$s1 = Read-Host 'Senha (mínimo 12 caracteres)' -AsSecureString
$s2 = Read-Host 'Repita a senha' -AsSecureString
$senha = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s1))
if ($senha -ne [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s2))) {
    throw 'As senhas não conferem. Nada foi alterado.'
}
if ($senha.Length -lt 12) { throw 'A senha precisa ter pelo menos 12 caracteres.' }

# O .env usa DB_HOST=localhost (vale dentro do servidor); daqui o banco é acessado pelo IP.
$env:DB_HOST = '186.209.113.107'
Push-Location $raiz
try {
    $senha | & $php bin/isis plataforma:admin $Email $Nome
} finally {
    Pop-Location
    Remove-Item Env:DB_HOST
    $senha = $null
}
