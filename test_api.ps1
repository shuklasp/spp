$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginResponse = Invoke-RestMethod -Uri "http://localhost/school1/sppadmin/api.php?action=login" -Method Post -Body @{ username="admin"; password="password" } -WebSession $session
Write-Output "Login Response:"
$loginResponse | ConvertTo-Json -Depth 5

$permsResponse = Invoke-RestMethod -Uri "http://localhost/school1/sppadmin/api.php?action=get_admin_permissions" -Method Get -WebSession $session
Write-Output "Permissions Response:"
$permsResponse | ConvertTo-Json -Depth 5
