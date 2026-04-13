Add-Type -AssemblyName System.IO.Compression.FileSystem
$file = 'c:\laragon\www\gestion-locative\cahier_des_charges_locatif.docx'
$zip = [System.IO.Compression.ZipFile]::OpenRead($file)
$entry = $zip.Entries | Where-Object { $_.FullName -eq 'word/document.xml' }
$stream = $entry.Open()
$reader = New-Object System.IO.StreamReader($stream)
$xml = $reader.ReadToEnd()
$reader.Close()
$zip.Dispose()
$text = $xml -replace '<w:p[^>]*>', "`n" -replace '<[^>]+>', '' -replace '&amp;', '&' -replace '&lt;', '<' -replace '&gt;', '>'
Write-Output $text
