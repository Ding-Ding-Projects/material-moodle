# Material Moodle appliance

A ready-made server image: write it to a USB stick, import it into VirtualBox or VMware, or flash it to a Raspberry
Pi, switch it on, answer a few questions on the screen, and your Material Moodle site is running.

Every release has these files (built and boot-tested by `.github/workflows/material-appliance.yml`):

| File | For |
| --- | --- |
| `material-moodle-appliance-x86_64.img.xz` | A spare PC: write it to a USB stick or disk |
| `material-moodle-appliance-x86_64.ova` | VirtualBox or VMware: File › Import |
| `material-moodle-appliance-x86_64.qcow2` | QEMU, Proxmox, libvirt |
| `material-moodle-appliance-arm64.img.xz` | Raspberry Pi 4 or 5 (64-bit), on an SD card or USB drive |
| `SHA256SUMS-appliance` | Checksums of all of the above |

The desktop deployer can do the download, check and flashing for you.

## Write it to a USB stick, SD card or disk

- **balenaEtcher** or **Raspberry Pi Imager** ("Use custom"): pick the `.img.xz` file and the drive. Both check the
  write for you.
- **Linux or macOS terminal**: `xz -dc material-moodle-appliance-x86_64.img.xz | sudo dd of=/dev/sdX bs=4M
  conv=fsync status=progress`, with `/dev/sdX` the whole stick, not a partition. Everything on it is erased.

The image is 8 GB and grows to fill the drive on first boot; a 16 GB or larger drive is a good choice. On a PC,
choose the USB stick in the boot menu. Secure Boot must be off.

## Import into VirtualBox or VMware

Import the `.ova`. Keep the network adapter on **Bridged** so the server gets an address on your network, give it
at least 2 GB of memory, and start it.

## First boot

The screen shows a welcome, waits for the network, and then asks:

1. the site name,
2. the administrator's username and email,
3. a password (generated for you, or your own),
4. how people reach the site: only your network, a free public https link from Cloudflare, or your own domain,
5. the colour and whether to show passkey sign-in.

It then starts Moodle and shows the link in large letters. From another computer on the same network the site is
also at `http://material-moodle.local`.

Afterwards the first screen shows a status page: the link, whether the site is running, and keys to run the setup
again (`R`), update (`U`) or log in (`L`).

## Signing in to the server itself

The status screen shows a one-time password for the user `material`. Sign in on the console (`L`) or over SSH
(`ssh material@material-moodle.local`) and choose your own password when asked. Then:

```sh
sudo material-setup --reconfigure   # change the answers
sudo material-update                # update the system and Moodle
material-status                     # the link and health
```

## What is inside

Debian 13 with Docker, the Material Moodle stack in `/opt/material-moodle` with its images already loaded, automatic
security updates (restarts at 04:30 when needed), a firewall that allows only SSH, HTTP (80 and 8080) and network
discovery, journal and container log limits, a swap file, and mDNS so the name `material-moodle.local` works.

## Building it yourself

On Debian or Ubuntu as root (an arm64 machine for the Raspberry Pi image):

```sh
for i in ghcr.io/ding-ding-projects/material-moodle:latest postgres:17 cloudflare/cloudflared:latest; do docker pull "$i"; done
docker save ghcr.io/ding-ding-projects/material-moodle:latest postgres:17 cloudflare/cloudflared:latest | zstd -o images.tar.zst
sudo material/appliance/build.sh --platform x86_64 --out out --images images.tar.zst
material/appliance/test/boot-test.sh x86_64 out/material-moodle-appliance-x86_64.qcow2 uefi
```
