# Server image (appliance)

The appliance is a ready-made Debian 13 server with Docker and the Material Moodle images already inside. Start it,
answer a few questions on its screen, and the site is running. Nothing needs to be typed into files.

## Downloads

Each release has these files once the image build finishes:

| File | Use it for |
| --- | --- |
| `material-moodle-appliance-x86_64.ova` | VirtualBox and VMware: File, Import Appliance. The `.vmdk` disk is inside it. |
| `material-moodle-appliance-x86_64.qcow2` | QEMU, Proxmox and other KVM hosts. |
| `material-moodle-appliance-x86_64.img.xz` | A spare PC: write it to a USB stick or disk. |
| `material-moodle-appliance-arm64.img.xz` | Raspberry Pi 4 and 5 (64-bit), written to a microSD card or USB drive. |
| `SHA256SUMS-appliance` | Checksums for every file above. |

PC images boot with BIOS and UEFI. Write `.img.xz` files with Raspberry Pi Imager, balenaEtcher or
`xzcat file.img.xz | sudo dd of=/dev/sdX bs=4M status=progress`.

## First start

1. Connect the machine to your network (virtual machines use a bridged adapter by default).
2. Start it. The disk grows to fill the drive and a swap file is made.
3. The first screen shows a menu: site name, administrator and how people reach the site (your network, a free public
   Cloudflare link, or your own domain). Passwords are generated for you.
4. When it finishes it shows the link, `http://material-moodle.local`, and the one-time administrator password.

## Afterwards

- The console shows a status screen with the link, address and health. Press R to run setup again, U to update, or
  L to sign in on the second console.
- Sign in over SSH as `material` with the one-time password shown on the status screen. You choose your own at the first
  sign-in, and the line disappears.
- `sudo material-update` pulls new images and restarts the site. Security updates for Debian install automatically.
- Only ports 22 (SSH), 80 (the site) and local discovery are open.

## Building it yourself

`material/appliance/build.sh x86_64` or `build.sh raspberry-pi` on a Debian or Ubuntu machine with root, then
`test/boot-test.sh` boots the result in QEMU. The CI workflow `material-appliance.yml` does both and attaches the files to
the matching release.
