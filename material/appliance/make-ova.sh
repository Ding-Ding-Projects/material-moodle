#!/usr/bin/env bash
# Wraps a streamOptimized VMDK into an OVA (OVF 1.0) that VirtualBox and VMware can import.
#   make-ova.sh disk.vmdk out.ova 8G version
set -euo pipefail
VMDK="$1"; OVA="$2"; SIZE="$3"; VERSION="${4:-dev}"
BYTES=$(numfmt --from=iec "$SIZE")
FILE_SIZE=$(stat -c %s "$VMDK")
DIR="$(mktemp -d)"
BASE="$(basename "$OVA" .ova)"
DISKFILE="$BASE-disk1.vmdk"
cp "$VMDK" "$DIR/$DISKFILE"
cat >"$DIR/$BASE.ovf" <<OVF
<?xml version="1.0" encoding="UTF-8"?>
<Envelope ovf:version="1.0" xml:lang="en-US"
  xmlns="http://schemas.dmtf.org/ovf/envelope/1"
  xmlns:ovf="http://schemas.dmtf.org/ovf/envelope/1"
  xmlns:rasd="http://schemas.dmtf.org/wbem/wscim/1/cim-schema/2/CIM_ResourceAllocationSettingData"
  xmlns:vssd="http://schemas.dmtf.org/wbem/wscim/1/cim-schema/2/CIM_VirtualSystemSettingData"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <References>
    <File ovf:id="file1" ovf:href="$DISKFILE" ovf:size="$FILE_SIZE"/>
  </References>
  <DiskSection>
    <Info>Virtual disk</Info>
    <Disk ovf:capacity="$BYTES" ovf:diskId="vmdisk1" ovf:fileRef="file1"
      ovf:format="http://www.vmware.com/interfaces/specifications/vmdk.html#streamOptimized"/>
  </DiskSection>
  <NetworkSection>
    <Info>Network</Info>
    <Network ovf:name="Bridged"><Description>Bridged network so the server gets an address on your network</Description></Network>
  </NetworkSection>
  <VirtualSystem ovf:id="Material Moodle">
    <Info>Material Moodle appliance $VERSION</Info>
    <Name>Material Moodle</Name>
    <OperatingSystemSection ovf:id="96">
      <Info>Debian (64-bit)</Info>
    </OperatingSystemSection>
    <VirtualHardwareSection>
      <Info>2 CPUs, 2 GB memory, one disk, one network adapter</Info>
      <System>
        <vssd:ElementName>Virtual Hardware Family</vssd:ElementName>
        <vssd:InstanceID>0</vssd:InstanceID>
        <vssd:VirtualSystemIdentifier>Material Moodle</vssd:VirtualSystemIdentifier>
        <vssd:VirtualSystemType>vmx-14 virtualbox-2.2</vssd:VirtualSystemType>
      </System>
      <Item>
        <rasd:Caption>2 virtual CPUs</rasd:Caption>
        <rasd:ElementName>2 virtual CPUs</rasd:ElementName>
        <rasd:InstanceID>1</rasd:InstanceID>
        <rasd:ResourceType>3</rasd:ResourceType>
        <rasd:VirtualQuantity>2</rasd:VirtualQuantity>
      </Item>
      <Item>
        <rasd:AllocationUnits>byte * 2^20</rasd:AllocationUnits>
        <rasd:Caption>2048 MB of memory</rasd:Caption>
        <rasd:ElementName>2048 MB of memory</rasd:ElementName>
        <rasd:InstanceID>2</rasd:InstanceID>
        <rasd:ResourceType>4</rasd:ResourceType>
        <rasd:VirtualQuantity>2048</rasd:VirtualQuantity>
      </Item>
      <Item>
        <rasd:Caption>SATA controller</rasd:Caption>
        <rasd:ElementName>SATA controller</rasd:ElementName>
        <rasd:InstanceID>3</rasd:InstanceID>
        <rasd:ResourceSubType>AHCI</rasd:ResourceSubType>
        <rasd:ResourceType>20</rasd:ResourceType>
      </Item>
      <Item>
        <rasd:AddressOnParent>0</rasd:AddressOnParent>
        <rasd:Caption>Disk 1</rasd:Caption>
        <rasd:ElementName>Disk 1</rasd:ElementName>
        <rasd:HostResource>ovf:/disk/vmdisk1</rasd:HostResource>
        <rasd:InstanceID>4</rasd:InstanceID>
        <rasd:Parent>3</rasd:Parent>
        <rasd:ResourceType>17</rasd:ResourceType>
      </Item>
      <Item>
        <rasd:AutomaticAllocation>true</rasd:AutomaticAllocation>
        <rasd:Caption>Ethernet adapter on Bridged</rasd:Caption>
        <rasd:Connection>Bridged</rasd:Connection>
        <rasd:ElementName>Ethernet adapter</rasd:ElementName>
        <rasd:InstanceID>5</rasd:InstanceID>
        <rasd:ResourceSubType>E1000</rasd:ResourceSubType>
        <rasd:ResourceType>10</rasd:ResourceType>
      </Item>
    </VirtualHardwareSection>
  </VirtualSystem>
</Envelope>
OVF
( cd "$DIR" && printf 'SHA256(%s)= %s\nSHA256(%s)= %s\n' "$BASE.ovf" "$(sha256sum "$BASE.ovf" | cut -d' ' -f1)" \
    "$DISKFILE" "$(sha256sum "$DISKFILE" | cut -d' ' -f1)" >"$BASE.mf" )
tar -C "$DIR" -cf "$OVA" "$BASE.ovf" "$BASE.mf" "$DISKFILE"
rm -rf "$DIR"
echo "Wrote $OVA"
