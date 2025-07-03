#!/usr/bin/env node

const { execSync, spawnSync } = require('child_process');
const readline = require('readline');

function checkCommand(cmd) {
  try {
    execSync(`command -v ${cmd}`, { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}

function prompt(question, hideInput = false) {
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });

  if (hideInput) {
    process.stdout.write(question);
    return new Promise((resolve) => {
      const stdin = process.openStdin();
      let password = '';
      process.stdin.on('data', (char) => {
        char = char + '';
        switch (char) {
          case '\n':
          case '\r':
          case '\u0004':
            stdin.pause();
            break;
          default:
            process.stdout.write('*');
            password += char;
            break;
        }
      });
      stdin.on('end', () => {
        resolve(password.trim());
      });
    });
  } else {
    return new Promise((resolve) => rl.question(question, (ans) => {
      rl.close();
      resolve(ans.trim());
    }));
  }
}

async function main() {
  const requiredCmds = ['ssh', 'scp', 'zip', 'curl'];
  for (const cmd of requiredCmds) {
    if (!checkCommand(cmd)) {
      console.error(`Required command '${cmd}' not found. Please install it before proceeding.`);
      process.exit(1);
    }
  }

  const args = process.argv.slice(2);
  let sourceDir = args[0];
  let sourceSSH = args[1];
  let destFTP = args[2];
  let destFTPPass = args[3];

  if (!sourceDir) {
    sourceDir = await prompt('Source WordPress directory (e.g. /var/www/html): ');
  }
  if (!sourceSSH) {
    sourceSSH = await prompt('Source SSH user and host (e.g. user@host): ');
  }
  if (!destFTP) {
    destFTP = await prompt('Destination FTP user and host (e.g. user@host): ');
  }
  if (!destFTPPass) {
    destFTPPass = await prompt('Destination FTP password: ', true);
    console.log('');
  }

  const zipName = `wordpress_migration_${Math.floor(Date.now() / 1000)}.zip`;
  const remoteZip = `/tmp/${zipName}`;

  console.log('Zipping source directory on source server...');
  execSync(`ssh ${sourceSSH} "zip -r ${remoteZip} ${sourceDir}"`, { stdio: 'inherit' });

  console.log('Downloading zip file from source server...');
  execSync(`scp ${sourceSSH}:${remoteZip} .`, { stdio: 'inherit' });

  console.log('Cleaning up zip file on source server...');
  execSync(`ssh ${sourceSSH} "rm ${remoteZip}"`, { stdio: 'inherit' });

  console.log('Uploading zip file to destination FTP server...');
  const ftpHost = destFTP.split('@')[1];
  const ftpUser = destFTP.split('@')[0];
  const uploadCmd = `curl -T ${zipName} --ftp-create-dirs -u ${ftpUser}:${destFTPPass} ftp://${ftpHost}/${remoteZip}`;
  execSync(uploadCmd, { stdio: 'inherit' });

  console.log(`Migration zip uploaded to destination FTP at ${remoteZip}`);
  console.log('Please unzip the file manually on the destination server.');

  execSync(`rm ${zipName}`);

  console.log('Migration completed.');
}

main();
