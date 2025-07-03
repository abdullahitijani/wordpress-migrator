#!/usr/bin/env python3
import os
import sys
import subprocess
import getpass
import argparse

def check_command(cmd):
    from shutil import which
    return which(cmd) is not None

def install_instructions(cmd):
    print(f"Please install '{cmd}' manually. Automatic installation is not supported.")

def run_command(cmd, capture_output=False):
    try:
        if capture_output:
            result = subprocess.run(cmd, shell=True, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            return result.stdout.decode(), result.stderr.decode()
        else:
            subprocess.run(cmd, shell=True, check=True)
            return None, None
    except subprocess.CalledProcessError as e:
        print(f"Command failed: {cmd}")
        print(e)
        sys.exit(1)

def prompt_input(prompt_text, is_password=False):
    if is_password:
        return getpass.getpass(prompt_text)
    else:
        return input(prompt_text)

def main():
    parser = argparse.ArgumentParser(description="WordPress Migration Script (Python)")
    parser.add_argument('--source-dir', help='Source WordPress directory')
    parser.add_argument('--source-ssh', help='Source SSH user@host')
    parser.add_argument('--dest-ftp', help='Destination FTP user@host')
    parser.add_argument('--dest-ftp-pass', help='Destination FTP password')
    args = parser.parse_args()

    required_cmds = ['ssh', 'scp', 'zip', 'curl']
    for cmd in required_cmds:
        if not check_command(cmd):
            print(f"Required command '{cmd}' not found.")
            install_instructions(cmd)
            sys.exit(1)

    source_dir = args.source_dir or prompt_input("Source WordPress directory (e.g. /var/www/html): ")
    source_ssh = args.source_ssh or prompt_input("Source SSH user and host (e.g. user@host): ")
    dest_ftp = args.dest_ftp or prompt_input("Destination FTP user and host (e.g. user@host): ")
    dest_ftp_pass = args.dest_ftp_pass or prompt_input("Destination FTP password: ", is_password=True)

    zip_name = f"wordpress_migration_{int(os.time())}.zip"
    remote_zip = f"/tmp/{zip_name}"

    print("Zipping source directory on source server...")
    run_command(f'ssh {source_ssh} "zip -r {remote_zip} {source_dir}"')

    print("Downloading zip file from source server...")
    run_command(f'scp {source_ssh}:{remote_zip} .')

    print("Cleaning up zip file on source server...")
    run_command(f'ssh {source_ssh} "rm {remote_zip}"')

    print("Uploading zip file to destination FTP server...")
    ftp_host = dest_ftp.split('@')[1]
    ftp_user = dest_ftp.split('@')[0]
    upload_cmd = f'curl -T {zip_name} --ftp-create-dirs -u {ftp_user}:{dest_ftp_pass} ftp://{ftp_host}/{remote_zip}'
    run_command(upload_cmd)

    print(f"Migration zip uploaded to destination FTP at {remote_zip}")
    print("Please unzip the file manually on the destination server.")

    os.remove(zip_name)
    print("Migration completed.")

if __name__ == "__main__":
    main()
