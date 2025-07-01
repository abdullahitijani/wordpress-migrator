# WordPress Migrator

## Version 1.0.0 - CLI Local Release

This release provides a command-line interface (CLI) tool for migrating WordPress sites between cPanel and CyberPanel servers. It does not include any web UI components and is intended for local use only.

---

## Features

- Direct streaming of WordPress site zip files between servers without local intermediate storage.
- Database export and import with credential checks.
- User prompts for flexible migration configuration.

---

## Requirements

- PHP 7.4 or higher
- PHP ssh2 extension installed and enabled
- SSH access with username/password to both source and destination servers
- rsync installed on both servers
- MySQL client tools (`mysqldump`, `mysql`) installed on both servers

---

## Installation

1. Clone or download this repository.

2. Install the PHP ssh2 extension:

   ```bash
   # macOS with Homebrew
   brew install libssh2
   pecl install ssh2

   # Enable extension in php.ini
   echo "extension=ssh2.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")
   ```

3. Configure your credentials in `config/credentials.json` or use the CLI prompts to input them.

---

## Configuration

This version uses a .env configuration file for credentials located at `config/.env`. You can either edit this file directly with your server and database credentials or leave it empty and provide credentials interactively via CLI prompts during migration.

Example `config/.env.example`:

# Copy this file to .env and fill in your credentials

# Source cPanel FTP
CPANEL_FTP_HOST=
CPANEL_FTP_PORT=21
CPANEL_FTP_USER=
CPANEL_FTP_PASS=

# Destination CyberPanel SSH
CYBERPANEL_SSH_HOST=
CYBERPANEL_SSH_PORT=22
CYBERPANEL_SSH_USER=
CYBERPANEL_SSH_PASS=

# Source CyberPanel SSH
SOURCE_CYBERPANEL_SSH_HOST=
SOURCE_CYBERPANEL_SSH_PORT=22
SOURCE_CYBERPANEL_SSH_USER=
SOURCE_CYBERPANEL_SSH_PASS=

# Destination cPanel FTP
DEST_C_PANEL_FTP_HOST=
DEST_C_PANEL_FTP_PORT=21
DEST_C_PANEL_FTP_USER=
DEST_C_PANEL_FTP_PASS=

---

## Usage

Run the migration script from the command line:

```bash
php migrate.php
```

Follow the prompts to enter source and destination server details and migration options.

---

## Notes

- This version excludes any web UI components.
- Future versions may include web UI and additional features, such as:
  - Web-based user interface for easier configuration and monitoring
  - Support for SSH key authentication
  - Incremental file and database synchronization improvements
  - Enhanced error handling and logging
  - Support for additional hosting control panels

---

## License

MIT License

---

## Contributing

Contributions and improvements are welcome! Please open issues or pull requests.
