$data = Get-Content -Raw "C:\projects\apache\school1\src\ptable\data\master_elements.json" | ConvertFrom-Json
$ce = $data.Ce
$result = @{
    extract_html = $ce.extract_html
    sections = $ce.sections
}
$result | ConvertTo-Json -Depth 10 | Out-File -Encoding UTF8 "C:\projects\apache\school1\src\ptable\data\drafts\Cerium_extract.json"
