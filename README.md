# WordPress Migrator

A PHP CLI and Web UI tool to automate migrating a WordPress website between cPanel and CyberPanel servers.

---

## Features

- Direct server-to-server file transfer using SSH and rsync
- MySQL database export, transfer, and import via SSH
- Supports username/password authentication with configurable SSH ports
- Bidirectional migration: cPanel to CyberPanel and vice versa
- Clean logging of migration steps and errors
- CLI and Web UI interfaces for flexibility
- Incremental file sync to download only changed files: This feature compares file modification times on the source server and local backup, downloading only new or updated files to speed up migration and reduce downtime.
- Incremental database sync to export/import only changed data: This feature exports only database rows changed since the last sync based on timestamps, minimizing the size of database dumps and accelerating the import process.
- Backup integration to create backups before migration
- Email and Slack notifications for migration status and errors
- Support for WordPress multisite migration
- Rollback and retry options for failed migrations
- Detailed migration reports with success/failure logs and metrics

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

3. Configure your credentials in `config/credentials.json` or use the Web UI to input them.

---

## Usage

### CLI

Run the CLI script:

```bash
php src/index.php
```

Follow the prompts to select migration direction, source/destination directories, and credentials.

**Incremental Sync:** When using the CLI, you can choose to perform incremental file or database syncs. This means only files or database entries changed since the last migration will be transferred, reducing migration time and downtime.

This feature works by tracking the last migration time and comparing file modification timestamps or database row update timestamps. Only new or updated files and database entries are transferred, making subsequent migrations faster and minimizing downtime.



### Web UI

1. Serve the `src/web.php` file via a PHP-enabled web server (e.g., Apache, Nginx, or PHP built-in server):

```bash
php -S localhost:8000 -t src
```

2. Open your browser and navigate to `http://localhost:8000/web.php`.

3. Use the new buttons to trigger **Incremental File Sync** and **Incremental DB Sync** for efficient migration. These buttons allow you to sync only changed files or database entries since the last migration, reducing downtime and speeding up the process.

**Incremental Sync:** The Web UI provides dedicated buttons for incremental file and database syncs. Clicking these buttons will transfer only the changed files or database records since the last migration, making the process faster and minimizing downtime.

In the Web UI, you can trigger incremental syncs using the provided buttons labeled "Incremental File Sync" and "Incremental DB Sync". These buttons initiate the transfer of only changed files or database records since the last migration, allowing you to efficiently update your migrated site with minimal downtime.


4. Fill in the migration form with server details, directories, and credentials.

5. Click **Start Migration** to begin.

---

## Configuration

Example `config/credentials.example.json`:

```json
{
  "cpanel": {
    "host": "cpanel.example.com",
    "username": "cpaneluser",
    "password": "cpanelpass",
    "port": 22,
    "database": {
      "name": "cpanel_db",
      "user": "cpanel_db_user",
      "password": "cpanel_db_pass"
    }
  },
  "cyberpanel": {
    "host": "cyberpanel.example.com",
    "username": "cyberuser",
    "password": "cyberpass",
    "port": 22,
    "database": {
      "name": "cyberpanel_db",
      "user": "cyberpanel_db_user",
      "password": "cyberpanel_db_pass"
    }
  }
}
```

---

## Logging

Migration logs are saved to `logs/migration.log`. Check this file for detailed progress and error messages.

---

## Notes

- Ensure SSH access and permissions are correctly configured on both servers.
- Test migration on a staging environment before production.
- The tool currently supports username/password SSH authentication; SSH key support may be added in the future.
- The tool requires `rsync` and MySQL client tools installed on both servers.
- Use the Web UI buttons for incremental syncs to minimize downtime during migration.

---

## License

MIT License

---

## Contributing

Contributions and improvements are welcome! Please open issues or pull requests.
