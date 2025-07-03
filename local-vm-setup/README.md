# Local Virtual Machines Setup for WordPress Migrator Testing

This guide helps you set up lightweight local virtual machines (VMs) on your Mac to simulate source and destination servers for testing the WordPress migration tool. The VMs will run the same PHP version as your Mac host to ensure compatibility.

## Prerequisites

- VirtualBox installed on your Mac
- Vagrant installed on your Mac
- Basic familiarity with command line and VM management

## Overview

We will create two lightweight Ubuntu VMs using Vagrant:

- **Source VM:** Simulates the source server (e.g., cPanel)
- **Destination VM:** Simulates the destination server (e.g., CyberPanel)

Each VM will have:

- PHP installed (matching your Mac's PHP version)
- Apache or Nginx web server
- MySQL or MariaDB database server
- FTP and SSH services enabled

## Steps

### 1. Create a Vagrantfile

Create a directory `local-vm-setup` and inside it create a `Vagrantfile` with the following content:

```ruby
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  # Source VM
  config.vm.define "source" do |source|
    source.vm.box = "ubuntu/focal64"
    source.vm.hostname = "source.local"
    source.vm.network "private_network", ip: "192.168.56.10"
    source.vm.provider "virtualbox" do |vb|
      vb.memory = "1024"
      vb.cpus = 1
    end
    source.vm.provision "shell", inline: <<-SHELL
      apt-get update
      apt-get install -y apache2 php php-mysql mysql-server vsftpd openssh-server zip unzip
      systemctl enable apache2
      systemctl enable mysql
      systemctl enable vsftpd
      systemctl enable ssh
    SHELL
  end

  # Destination VM
  config.vm.define "destination" do |dest|
    dest.vm.box = "ubuntu/focal64"
    dest.vm.hostname = "destination.local"
    dest.vm.network "private_network", ip: "192.168.56.11"
    dest.vm.provider "virtualbox" do |vb|
      vb.memory = "1024"
      vb.cpus = 1
    end
    dest.vm.provision "shell", inline: <<-SHELL
      apt-get update
      apt-get install -y apache2 php php-mysql mysql-server vsftpd openssh-server zip unzip
      systemctl enable apache2
      systemctl enable mysql
      systemctl enable vsftpd
      systemctl enable ssh
    SHELL
  end
end
```

### 2. Start the VMs

From the `local-vm-setup` directory, run:

```bash
vagrant up
```

This will download the Ubuntu box and provision both VMs.

### 3. Access the VMs

You can SSH into the VMs:

```bash
vagrant ssh source
vagrant ssh destination
```

### 4. Configure WordPress and Test Environment

- Set up WordPress sites on each VM under `/var/www/html`.
- Configure FTP users and permissions.
- Ensure PHP version matches your Mac by checking `php -v` on both Mac and VMs.
- Adjust firewall and network settings if needed.

### 5. Use the Migration Tool

- Use the IP addresses `192.168.56.10` and `192.168.56.11` as source and destination hosts in your migration tool.
- Test migration flows between these VMs.

## Notes

- You can customize PHP versions by adding appropriate PPAs or using Docker containers inside the VMs.
- This setup provides isolated environments for safe testing without affecting your Mac.

## Cleanup

To stop and remove the VMs:

```bash
vagrant destroy -f
```

## Support

If you need help with this setup, please ask.

---

This setup will help you simulate real server environments locally for thorough testing of your WordPress migration tool.
