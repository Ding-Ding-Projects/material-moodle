'use strict';
// Draws the app icon (512x512 PNG) without any image libraries: a rounded tile in the seed
// colour with a graduation cap and an upward deploy chevron. Writes build/icon.png and
// renderer/assets/icon.png.
const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');

const SIZE = 512;
const SS = 4; // supersamples per axis

const hex = (h) => [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)];
const TOP = hex('#A86A1C');
const BOTTOM = hex('#7A4400');
const CAP = hex('#FFF1E4');
const ACCENT = hex('#FFC98F');

function roundedRect(x, y, x0, y0, x1, y1, r) {
  const cx = Math.max(x0 + r, Math.min(x, x1 - r));
  const cy = Math.max(y0 + r, Math.min(y, y1 - r));
  return (x - cx) ** 2 + (y - cy) ** 2 <= r * r && x >= x0 && x <= x1 && y >= y0 && y <= y1;
}
function rhombus(x, y, cx, cy, w, h) {
  return Math.abs(x - cx) / (w / 2) + Math.abs(y - cy) / (h / 2) <= 1;
}
function segment(x, y, ax, ay, bx, by, width) {
  const dx = bx - ax, dy = by - ay;
  const t = Math.max(0, Math.min(1, ((x - ax) * dx + (y - ay) * dy) / (dx * dx + dy * dy)));
  return (x - ax - t * dx) ** 2 + (y - ay - t * dy) ** 2 <= (width / 2) ** 2;
}

function shade(x, y) {
  if (!roundedRect(x, y, 16, 16, 496, 496, 118)) return null;
  // Graduation cap: board, crown and tassel.
  if (rhombus(x, y, 256, 196, 330, 150)) return CAP;
  if (y >= 214 && y <= 300 && roundedRect(x, y, 166, 200, 346, 300, 40) && !(y < 236 && Math.abs(x - 256) / 165 + Math.abs(y - 196) / 75 > 1)) return CAP;
  if (segment(x, y, 256, 196, 382, 232, 12) || segment(x, y, 382, 232, 382, 300, 12)) return ACCENT;
  if ((x - 382) ** 2 + (y - 312) ** 2 <= 17 ** 2) return ACCENT;
  // Deploy chevron.
  if (segment(x, y, 186, 410, 256, 350, 34) || segment(x, y, 256, 350, 326, 410, 34)) return ACCENT;
  const t = (y - 16) / 480;
  return TOP.map((c, i) => Math.round(c + (BOTTOM[i] - c) * t));
}

const raw = Buffer.alloc((SIZE * 4 + 1) * SIZE);
for (let y = 0; y < SIZE; y++) {
  raw[y * (SIZE * 4 + 1)] = 0;
  for (let x = 0; x < SIZE; x++) {
    let r = 0, g = 0, b = 0, a = 0;
    for (let sy = 0; sy < SS; sy++) {
      for (let sx = 0; sx < SS; sx++) {
        const c = shade(x + (sx + 0.5) / SS, y + (sy + 0.5) / SS);
        if (c) { r += c[0]; g += c[1]; b += c[2]; a++; }
      }
    }
    const o = y * (SIZE * 4 + 1) + 1 + x * 4;
    raw[o] = a ? Math.round(r / a) : 0;
    raw[o + 1] = a ? Math.round(g / a) : 0;
    raw[o + 2] = a ? Math.round(b / a) : 0;
    raw[o + 3] = Math.round((a / (SS * SS)) * 255);
  }
}

const CRC = new Uint32Array(256).map((_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1;
  return c >>> 0;
});
function crc32(buf) {
  let c = 0xFFFFFFFF;
  for (const b of buf) c = CRC[(c ^ b) & 0xFF] ^ (c >>> 8);
  return (c ^ 0xFFFFFFFF) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length);
  const td = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(td));
  return Buffer.concat([len, td, crc]);
}
const ihdr = Buffer.alloc(13);
ihdr.writeUInt32BE(SIZE, 0);
ihdr.writeUInt32BE(SIZE, 4);
ihdr[8] = 8; ihdr[9] = 6; ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0;
const png = Buffer.concat([
  Buffer.from([0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A]),
  chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0)),
]);
for (const out of [path.join(__dirname, '..', 'build', 'icon.png'), path.join(__dirname, '..', 'renderer', 'assets', 'icon.png')]) {
  fs.mkdirSync(path.dirname(out), { recursive: true });
  fs.writeFileSync(out, png);
  console.log('Wrote ' + path.relative(process.cwd(), out) + ' (' + png.length + ' bytes)');
}
