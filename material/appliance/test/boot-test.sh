#!/usr/bin/env bash
# Boots an appliance image in QEMU and waits for the first-boot service to report on the serial console.
#   boot-test.sh x86_64 out/material-moodle-appliance-x86_64.qcow2 [bios|uefi]
#   boot-test.sh arm64  out/material-moodle-appliance-arm64.img.xz out/boot-test
# Passes when the console prints "MATERIAL-APPLIANCE: test boot complete" and Docker reported ready.
set -euo pipefail
ARCH="$1"; IMAGE="$2"; EXTRA="${3:-bios}"
WORK="$(mktemp -d)"
LOG="${BOOT_LOG:-$WORK/serial.log}"
ACCEL="tcg"; TIMEOUT=3600
if [ -w /dev/kvm ] && [ "$ARCH" = x86_64 ]; then ACCEL="kvm"; TIMEOUT=1800; fi
echo "Booting $IMAGE ($ARCH, $EXTRA, accel $ACCEL), log $LOG"

if [ "$ARCH" = x86_64 ]; then
  qemu-img create -q -f qcow2 -F qcow2 -b "$(realpath "$IMAGE")" "$WORK/disk.qcow2" 16G
  FW=()
  if [ "$EXTRA" = uefi ]; then
    cp /usr/share/OVMF/OVMF_VARS_4M.fd "$WORK/vars.fd"
    # shellcheck disable=SC2054 # QEMU options use commas inside one argument.
    FW=(-drive if=pflash,format=raw,readonly=on,file=/usr/share/OVMF/OVMF_CODE_4M.fd -drive if=pflash,format=raw,file="$WORK/vars.fd")
  fi
  timeout "$TIMEOUT" qemu-system-x86_64 -machine q35,accel="$ACCEL" -cpu max -smp 2 -m 3072 \
    "${FW[@]}" -drive file="$WORK/disk.qcow2",if=virtio,format=qcow2 \
    -netdev user,id=n0 -device virtio-net-pci,netdev=n0 \
    -fw_cfg name=opt/material/testboot,string=1 \
    -display none -serial file:"$LOG" -no-reboot || true
else
  KERNELS="$EXTRA"
  xz -dc "$IMAGE" >"$WORK/disk.img"
  qemu-img resize -q -f raw "$WORK/disk.img" 16G
  timeout "$TIMEOUT" qemu-system-aarch64 -machine virt -cpu max -smp 2 -m 3072 \
    -kernel "$KERNELS/vmlinuz" -initrd "$KERNELS/initrd.img" \
    -append "root=LABEL=mm-root rw console=ttyAMA0 material.testboot=1" \
    -drive file="$WORK/disk.img",if=virtio,format=raw \
    -netdev user,id=n0 -device virtio-net-pci,netdev=n0 \
    -fw_cfg name=opt/material/testboot,string=1 \
    -display none -serial file:"$LOG" -no-reboot || true
fi

echo "---- serial console (last 60 lines) ----"
tail -n 60 "$LOG" || true
grep -q "MATERIAL-APPLIANCE: first-boot setup screen ready" "$LOG" || { echo "FAIL: setup screen never started"; exit 1; }
grep -q "MATERIAL-APPLIANCE: docker ready" "$LOG" || { echo "FAIL: Docker was not ready"; exit 1; }
grep -q "MATERIAL-APPLIANCE: image .*material-moodle" "$LOG" || { echo "FAIL: the Material Moodle image was not loaded"; exit 1; }
grep -q "MATERIAL-APPLIANCE: test boot complete" "$LOG" || { echo "FAIL: test boot did not complete"; exit 1; }
echo "PASS: $ARCH $EXTRA boot test"
