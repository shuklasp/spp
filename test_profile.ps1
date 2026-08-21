
$body = @{ action = "login"; username = "admin"; password = "admin123"; appname = "lekhak" } | ConvertTo-Json
$response = Invoke-WebRequest -Uri "http://localhost/school1/sppadmin/api.php" -Method Post -Body $body -ContentType "application/json" -SessionVariable session
$response.Content

$response2 = Invoke-WebRequest -Uri "http://localhost/school1/sppadmin/api.php?action=get_profile&appname=lekhak" -Method Get -WebSession $session
$response2.Content

