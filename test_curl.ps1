
$body = @{ action = "login"; username = "admin"; password = "admin123" } | ConvertTo-Json
$response = Invoke-WebRequest -Uri "http://localhost/school1/sppadmin/api.php" -Method Post -Body $body -ContentType "application/json" -SessionVariable session
$response.Headers
Write-Output "---"
$session.Cookies.GetCookies("http://localhost")

