# WordPress Migration Script (PowerShell) with dependency checks and installation prompts

param(
    [string]$SourceDir,
    [string]$SourceSSH,
    [string]$DestFTP,
    [string]$DestFTPPass
)

function Check-Command {
    param([string]$command)
    $null -ne (Get-Command $command -ErrorAction SilentlyContinue)
}

function Install-Command {
    param([string]$command)
    Write-Host "Please install $command manually as automatic installation is not supported in this script."
    exit 1
}

$requiredCommands = @("ssh", "scp", "zip", "curl")

foreach ($cmd in $requiredCommands) {
    if (-not (Check-Command $cmd)) {
        Write-Host "$cmd is not installed."
        $choice = Read-Host "Do you want to continue without $cmd? (y/n)"
        if ($choice -ne "y") {
            Install-Command $cmd
        }
    }
}

if (-not $SourceDir) {
    $SourceDir = Read-Host "Source WordPress directory (e.g. C:\inetpub\wwwroot)"
}
if (-not $SourceSSH) {
    $SourceSSH = Read-Host "Source SSH user and host (e.g. user@host)"
}
if (-not $DestFTP) {
    $DestFTP = Read-Host "Destination FTP user and host (e.g. user@host)"
}
if (-not $DestFTPPass) {
    $DestFTPPass = Read-Host -AsSecureString "Destination FTP password"
    $BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($DestFTPPass)
    $DestFTPPass = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)
}

$ZipName = "wordpress_migration_$(Get-Date -UFormat %s).zip"
$RemoteZip = "/tmp/$ZipName"

Write-Host "Zipping source directory on source server..."
ssh $SourceSSH "zip -r $RemoteZip $SourceDir"

Write-Host "Downloading zip file from source server..."
scp "$SourceSSH:$RemoteZip" .

Write-Host "Cleaning up zip file on source server..."
ssh $SourceSSH "rm $RemoteZip"

Write-Host "Uploading zip file to destination FTP server..."
$ftpUri = "ftp://$($DestFTP.Split('@')[1])$RemoteZip"
$ftpRequest = [System.Net.FtpWebRequest]::Create($ftpUri)
$ftpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
$ftpRequest.Credentials = New-Object System.Net.NetworkCredential(($DestFTP.Split('@')[0]), $DestFTPPass)
$ftpRequest.UseBinary = $true
$ftpRequest.UsePassive = $true

$fileContent = [System.IO.File]::ReadAllBytes($ZipName)
$ftpStream = $ftpRequest.GetRequestStream()
$ftpStream.Write($fileContent, 0, $fileContent.Length)
$ftpStream.Close()

Write-Host "Migration zip uploaded to destination FTP at $RemoteZip"
Write-Host "Please unzip the file manually on the destination server."

Remove-Item $ZipName

Write-Host "Migration completed."
