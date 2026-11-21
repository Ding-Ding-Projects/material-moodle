#!/usr/bin/env bash
# Builds the Material Moodle appliance: a Debian disk image that boots straight into a Material Moodle server.
#
#   sudo material/appliance/build.sh --platform x86_64 --out out [--images images.tar.zst] [--version 1.0.0]
#   sudo material/appliance/build.sh --platform raspberry-pi --out out [--images images.tar.zst]
#
# x86_64 writes material-moodle-appliance-x86_64.img.xz, .qcow2, .vmdk and .ova.
# raspberry-pi writes material-moodle-appliance-arm64.img.xz (run it on an arm64 machine).
# Both write SHA256SUMS. Runs on Debian or Ubuntu as root; GitHub's ubuntu-24.04 runners are the reference.
set -euo pipefail

PLATFORM=""
OUT="out"
IMAGES=""
VERSION="dev"
SIZE="8G"
SUITE="trixie"
MIRROR="http://deb.debian.org/debian"
while [ $# -gt 0 ]; do
  case "$1" in
    --platform) PLATFORM="$2"; shift 2 ;;
    --out) OUT="$2"; shift 2 ;;
    --images) IMAGES="$2"; shift 2 ;;
    --version) VERSION="$2"; shift 2 ;;
    --size) SIZE="$2"; shift 2 ;;
    --mirror) MIRROR="$2"; shift 2 ;;
    *) echo "Unknown option $1" >&2; exit 2 ;;
  esac
done
case "$PLATFORM" in
  x86_64) ARCH=amd64; NAME=material-moodle-appliance-x86_64; KERNEL=linux-image-amd64 ;;
  raspberry-pi) ARCH=arm64; NAME=material-moodle-appliance-arm64; KERNEL=linux-image-arm64 ;;
  *) echo "Use --platform x86_64 or --platform raspberry-pi" >&2; exit 2 ;;
esac
[ "$(id -u)" -eq 0 ] || { echo "Run as root." >&2; exit 1; }
for tool in debootstrap sgdisk parted mkfs.vfat mkfs.ext4 losetup xz qemu-img; do
  command -v "$tool" >/dev/null || { echo "Missing $tool" >&2; exit 1; }
done

HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"
WORK="$(mktemp -d /tmp/material-appliance.XXXXXX)"
ROOT="$WORK/root"
DISK="$WORK/$NAME.img"
LOOP=""

cleanup() {
  set +e
  for m in "$ROOT/boot/efi" "$ROOT/boot/firmware" "$ROOT/dev/pts" "$ROOT/dev" "$ROOT/proc" "$ROOT/sys" "$ROOT/run" "$ROOT"; do
    mountpoint -q "$m" && umount -lf "$m"
  done
  [ -n "$LOOP" ] && losetup -d "$LOOP"
  rm -rf "$WORK"
}
trap cleanup EXIT

echo "==> Disk ($SIZE, $PLATFORM)"
truncate -s "$SIZE" "$DISK"
if [ "$PLATFORM" = x86_64 ]; then
  sgdisk -Z "$DISK" >/dev/null
  sgdisk -n 1:2048:+1M -t 1:ef02 -c 1:bios \
         -n 2:0:+256M -t 2:ef00 -c 2:esp \
         -n 3:0:0 -t 3:8300 -c 3:root "$DISK" >/dev/null
  BOOT_PART=2; ROOT_PART=3; BOOT_DIR=boot/efi
else
  parted -s "$DISK" mklabel msdos mkpart primary fat32 4MiB 516MiB mkpart primary ext4 516MiB 100% set 1 boot on
  BOOT_PART=1; ROOT_PART=2; BOOT_DIR=boot/firmware
fi
LOOP="$(losetup -Pf --show "$DISK")"
partprobe "$LOOP" 2>/dev/null || true
sleep 1
mkfs.vfat -F 32 -n MMBOOT "${LOOP}p$BOOT_PART" >/dev/null
mkfs.ext4 -q -L mm-root "${LOOP}p$ROOT_PART"

mkdir -p "$ROOT"
mount "${LOOP}p$ROOT_PART" "$ROOT"
mkdir -p "$ROOT/$BOOT_DIR"
mount "${LOOP}p$BOOT_PART" "$ROOT/$BOOT_DIR"

echo "==> Debian $SUITE ($ARCH)"
debootstrap --arch="$ARCH" --variant=minbase \
  --include=systemd,systemd-sysv,dbus,ca-certificates,curl,gnupg,locales,keyboard-configuration,console-setup \
  "$SUITE" "$ROOT" "$MIRROR"

for d in dev dev/pts proc sys run; do mount --bind "/$d" "$ROOT/$d"; done
cat >"$ROOT/etc/apt/sources.list" <<EOF
deb $MIRROR $SUITE main contrib non-free-firmware
deb $MIRROR $SUITE-updates main contrib non-free-firmware
deb http://security.debian.org/debian-security $SUITE-security main contrib non-free-firmware
EOF
echo material-moodle >"$ROOT/etc/hostname"
printf '127.0.0.1 localhost\n127.0.1.1 material-moodle material-moodle.local\n' >"$ROOT/etc/hosts"
cat >"$ROOT/etc/fstab" <<EOF
LABEL=mm-root / ext4 defaults,noatime,errors=remount-ro 0 1
LABEL=MMBOOT /$BOOT_DIR vfat defaults,umask=0077 0 2
/swapfile none swap sw,nofail 0 0
EOF

echo "==> Overlay and stack"
cp -a "$HERE/rootfs/." "$ROOT/"
install -d "$ROOT/usr/local/lib/material-moodle/docker" "$ROOT/opt/material-moodle" "$ROOT/var/lib/material"
install -m 0755 "$REPO/material/install.sh" "$ROOT/usr/local/lib/material-moodle/install.sh"
install -m 0644 "$REPO/material/docker/docker-compose.yml" "$ROOT/usr/local/lib/material-moodle/docker/docker-compose.yml"
echo "$VERSION" >"$ROOT/etc/material-appliance-version"
if [ -n "$IMAGES" ]; then
  install -m 0644 "$IMAGES" "$ROOT/opt/material-moodle/images.tar.zst"
fi
chmod 0755 "$ROOT"/usr/local/sbin/*

echo "==> Packages"
chroot "$ROOT" /bin/bash -euo pipefail <<EOF
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends $KERNEL initramfs-tools \
  openssh-server sudo avahi-daemon libnss-mdns nftables unattended-upgrades apt-listchanges \
  whiptail figlet cloud-guest-utils e2fsprogs dosfstools zstd xz-utils iproute2 iputils-ping \
  systemd-resolved systemd-timesyncd less nano procps kmod
install -d -m 0755 /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$ARCH signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $SUITE stable" \
  >/etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y --no-install-recommends docker-ce docker-ce-cli containerd.io docker-compose-plugin
sed -i 's/^# *en_US.UTF-8/en_US.UTF-8/' /etc/locale.gen && locale-gen
echo 'LANG=en_US.UTF-8' >/etc/default/locale
useradd -m -s /bin/bash -G sudo,docker material
passwd -l material
passwd -l root
systemctl enable systemd-networkd systemd-resolved systemd-timesyncd ssh avahi-daemon nftables docker \
  material-grow-root material-swap material-default-user material-ssh-keys material-load-images \
  material-firstboot material-console material-moodle unattended-upgrades
systemctl mask getty@tty1.service systemd-firstboot.service
systemctl set-default multi-user.target
EOF

if [ "$PLATFORM" = x86_64 ]; then
  echo "==> GRUB (BIOS and UEFI)"
  chroot "$ROOT" /bin/bash -euo pipefail <<EOF
export DEBIAN_FRONTEND=noninteractive
apt-get install -y --no-install-recommends grub-pc-bin grub-efi-amd64-bin grub2-common grub-common
cat >/etc/default/grub <<'G'
GRUB_DEFAULT=0
GRUB_TIMEOUT=2
GRUB_DISTRIBUTOR="Material Moodle"
GRUB_CMDLINE_LINUX_DEFAULT="quiet"
GRUB_CMDLINE_LINUX="console=tty1 console=ttyS0,115200n8"
GRUB_TERMINAL="console serial"
GRUB_SERIAL_COMMAND="serial --speed=115200"
GRUB_DISABLE_OS_PROBER=true
G
grub-install --target=i386-pc --boot-directory=/boot $LOOP
grub-install --target=x86_64-efi --efi-directory=/boot/efi --boot-directory=/boot --removable --no-nvram
update-initramfs -u
EOF
  ROOTUUID="$(blkid -s UUID -o value "${LOOP}p$ROOT_PART")"
  chroot "$ROOT" grub-mkconfig -o /boot/grub/grub.cfg
  # grub-mkconfig sees the loop device; point every entry at the filesystem label instead.
  sed -i "s#root=[^ ]*#root=LABEL=mm-root#g" "$ROOT/boot/grub/grub.cfg"
  grep -q "root=LABEL=mm-root" "$ROOT/boot/grub/grub.cfg" || { echo "grub.cfg has no root entry" >&2; exit 1; }
  echo "root UUID $ROOTUUID"
else
  echo "==> Raspberry Pi firmware"
  cat >"$ROOT/etc/default/raspi-firmware" <<'EOF'
ROOTPART=LABEL=mm-root
CONSOLES="serial0,115200 tty1"
KERNEL_ARCH=arm64
EOF
  chroot "$ROOT" /bin/bash -euo pipefail <<'EOF'
export DEBIAN_FRONTEND=noninteractive
# Our settings file was written first, so keep it at the package prompt.
apt-get install -y --no-install-recommends -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold raspi-firmware firmware-brcm80211
update-initramfs -u
EOF
  ls "$ROOT/boot/firmware" | head
  mkdir -p "$OUT/boot-test"
  cp "$ROOT"/boot/vmlinuz-* "$OUT/boot-test/vmlinuz"
  cp "$ROOT"/boot/initrd.img-* "$OUT/boot-test/initrd.img"
fi

echo "==> Clean up the image"
chroot "$ROOT" /bin/bash -c 'apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*'
rm -f "$ROOT"/etc/ssh/ssh_host_*
: >"$ROOT/etc/machine-id"
rm -f "$ROOT/var/lib/dbus/machine-id"
sync
for m in "$ROOT/$BOOT_DIR" "$ROOT/dev/pts" "$ROOT/dev" "$ROOT/proc" "$ROOT/sys" "$ROOT/run"; do umount -lf "$m"; done
fstrim "$ROOT" 2>/dev/null || true
umount "$ROOT"
e2fsck -fy "${LOOP}p$ROOT_PART" >/dev/null || true
losetup -d "$LOOP"; LOOP=""

echo "==> Outputs"
cd "$OUT"
if [ "$PLATFORM" = x86_64 ]; then
  qemu-img convert -O qcow2 -c "$DISK" "$NAME.qcow2"
  qemu-img convert -O vmdk -o subformat=streamOptimized "$DISK" "$NAME.vmdk"
  bash "$HERE/make-ova.sh" "$NAME.vmdk" "$NAME.ova" "$SIZE" "$VERSION"
fi
xz -T0 -6 -c "$DISK" >"$NAME.img.xz"
sha256sum "$NAME".* >>SHA256SUMS
ls -la "$OUT"
echo "Done."
