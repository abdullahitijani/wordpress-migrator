# WordPress Migrator CLI Tool

## Overview

WordPress Migrator is a command-line interface (CLI) tool designed to facilitate the migration of WordPress sites between cPanel and CyberPanel hosting environments. It automates the process of transferring files and databases securely and efficiently.

## Features

- Migrate WordPress files and database from cPanel to CyberPanel and vice versa.
- Supports FTP and SSH connections for source and destination servers.
- Automates zipping and transferring WordPress directories.
- Handles database export, transfer, and import.
- Logs migration progress for troubleshooting.

## Requirements

- PHP 8.x with SSH2 and FTP extensions enabled.
- Access credentials for source and destination servers (FTP and SSH).
- ZipArchive PHP extension.
- Network access between source and destination servers.

## Installation

1. Clone or download the repository.
2. Ensure PHP CLI is installed and configured.
3. Configure credentials in `.env` file based on `.env.example` or provide them interactively during migration.

## Usage

Run the migration script from the command line:

```bash
php migrate.php
```

The tool will prompt for:

- Migration direction:
  - `1` for cPanel to CyberPanel
  - `2` for CyberPanel to cPanel
- Source server credentials (FTP or SSH depending on direction).
- Destination server credentials.
- WordPress directory path on the source server.
- Database credentials for source and destination.

### Example Workflow

1. Select migration direction.
2. Enter source server FTP or SSH details.
3. Enter destination server SSH or FTP details.
4. Specify the WordPress directory to migrate.
5. The tool will zip the WordPress files, transfer them, and prompt for manual unzip on the destination.
6. Database export, transfer, and import will be performed automatically.
7. Monitor the console output for progress and errors.

## Web UI (IN NEXT VERSION)

A web-based UI is planned for the next update, available at `src/web.php`, which will provide a form to enter migration details, show migration progress, and display logs dynamically.

## Notes

- Ensure SSH and FTP credentials are correct and accessible.
- The migration scripts zip the source WordPress directory on the source server, transfer the zip file, and upload it to the destination FTP server.
- Manual unzipping on the destination server may be required.

## Testing

- Test the PHP CLI and shell scripts according to your environment.

## Support

For issues or feature requests, please open an issue in the repository.


## Notes

- Ensure SSH and FTP credentials are correct and accessible.
- The migration scripts zip the source WordPress directory on the source server, transfer the zip file, and upload it to the destination FTP server.
- Manual unzipping on the destination server may be required.


## Logs

Migration logs are saved in the `logs/migration.log` file. Review this file for detailed information about the migration process and troubleshooting.

## Troubleshooting

- Ensure all credentials are correct and have necessary permissions.
- Verify network connectivity between source and destination servers.
- Check PHP extensions for SSH2, FTP, and ZipArchive are installed.
- Review `logs/migration.log` for error details.

## Contributing

Contributions are welcome. Please fork the repository and submit pull requests.

## License

MIT License

## Contact

For support or questions, please open an issue on the repository.
