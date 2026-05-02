$files = Get-ChildItem spp/admin/js/views/*.js
foreach ($file in $files) {
    $content = Get-Content $file.FullName
    $content = $content -replace 'this\.admin', 'this'
    Set-Content $file.FullName $content
}
