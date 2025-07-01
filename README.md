# WordPress Migrator Beta Release

This is a beta release of the WordPress Migrator CLI tool for migrating WordPress sites between cPanel and CyberPanel servers.

## Important

- This tool only works when cloned from this repository.
- It supports CLI migration from cPanel to CyberPanel and vice versa.
- Web UI is not included in this beta release.
- Ensure you have PHP 7.4+, PHP ssh2 extension, SSH access, and required MySQL tools installed.

## Installation

1. Clone this repository:

```bash
git clone https://github.com/yourusername/wordpress-migrator.git
cd wordpress-migrator/website-migrator-beta
```

2. Install PHP ssh2 extension if not already installed.

## Usage

Run the CLI migration tool:

```bash
php migrate.php
```

Follow the prompts to select migration direction, enter source and destination server details, and start the migration.

## Features

- FTP/SFTP connection to source server for file transfer
- MySQL database export and import
- SSH connection to destination server
- Incremental file and database sync options
- Clean logging of migration steps

## Notes

- This is a beta release for testing purposes.
- Contributions and feedback are welcome.

## License

MIT License
