#!/bin/bash

# WordPress Migration Script (Bash) with dependency checks and installation prompts

# Check for required commands and install if missing (Mac/Linux)
check_command() {
  command -v "$1" >/dev/null 2>&1
}

install_command() {
  if check_command brew; then
    echo "Installing $1 using Homebrew..."
    brew install "$1"
  elif check_command apt-get; then
    echo "Installing $1 using apt-get..."
    sudo apt-get update && sudo apt-get install -y "$1"
  elif check_command yum; then
    echo "Installing $1 using yum..."
    sudo yum install -y "$1"
  else
    echo "Please install $1 manually."
    exit 1
  fi
}

required_commands=("ssh" "scp" "zip" "curl")

for cmd in "${required_commands[@]}"; do
  if ! check_command "$cmd"; then
    echo "$cmd is not installed."
    read -p "Do you want to install $cmd now? (y/n): " choice
    if [[ "$choice" == "y" || "$choice" == "Y" ]]; then
      install_command "$cmd"
    else
      echo "Cannot proceed without $cmd. Exiting."
      exit 1
    fi
  fi
done

# Prompt for inputs if not provided as arguments
if [ "$#" -ne 4 ]; then
  echo "Please enter the following details:"
  read -p "Source WordPress directory (e.g. /var/www/html): " SOURCE_DIR
  read -p "Source SSH user and host (e.g. user@host): " SOURCE_SSH
  read -p "Destination FTP user and host (e.g. user@host): " DEST_FTP
  read -s -p "Destination FTP password: " DEST_FTP_PASS
  echo
else
  SOURCE_DIR=$1
  SOURCE_SSH=$2
  DEST_FTP=$3
  DEST_FTP_PASS=$4
fi

ZIP_NAME="wordpress_migration_$(date +%s).zip"
REMOTE_ZIP="/tmp/$ZIP_NAME"

echo "Zipping source directory on source server..."
ssh "$SOURCE_SSH" "zip -r $REMOTE_ZIP $SOURCE_DIR"

echo "Downloading zip file from source server..."
scp "$SOURCE_SSH:$REMOTE_ZIP" .

echo "Cleaning up zip file on source server..."
ssh "$SOURCE_SSH" "rm $REMOTE_ZIP"

echo "Uploading zip file to destination FTP server..."
curl -T "$ZIP_NAME" --ftp-create-dirs -u "$DEST_FTP:$DEST_FTP_PASS" "ftp://$(echo $DEST_FTP | cut -d'@' -f2)/$REMOTE_ZIP"

if [ $? -ne 0 ]; then
  echo "Failed to upload zip file to destination FTP."
  exit 1
fi

echo "Migration zip uploaded to destination FTP at $REMOTE_ZIP"
echo "Please unzip the file manually on the destination server."

rm "$ZIP_NAME"

echo "Migration completed."
