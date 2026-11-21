'use strict';
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const OUT = path.join(__dirname, '..', 'src', 'assets', 'icons');
fs.mkdirSync(OUT, { recursive: true });

/* CRC32 for PNG chunks */
const TABLE = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c >>> 0;
  }
  return t;
})();
function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const td = Buffer.concat([Buffer.from(type), data]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td));
  return Buffer.concat([len, td, crc]);
}
function png(w, h, pixel) {
  const raw = Buffer.alloc((w * 4 + 1) * h);
  for (let y = 0; y < h; y++) {
    raw[y * (w * 4 + 1)] = 0; /* filter: none */
    for (let x = 0; x < w; x++) {
      const [r, g, b] = pixel(x, y, w, h);
      const o = y * (w * 4 + 1) + 1 + x * 4;
      raw[o] = r; raw[o + 1] = g; raw[o + 2] = b; raw[o + 3] = 255;
    }
  }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4);
  ihdr[8] = 8; ihdr[9] = 6; /* 8-bit RGBA */
  return Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0))
  ]);
}

const NAVY = [11, 42, 74], WHITE = [255, 255, 255], AMBER = [245, 158, 11];
const inside = (v, a, b) => v >= a && v <= b;

/* a white "report" page with three amber lines, centred on navy */
function draw(x, y, w, h) {
  const s = Math.min(w, h);
  const u = (x - (w - s) / 2) / s;
  const v = (y - (h - s) / 2) / s;
  if (inside(u, 0.30, 0.70) && inside(v, 0.22, 0.78)) {
    if (inside(u, 0.37, 0.63) && (inside(v, 0.34, 0.39) || inside(v, 0.46, 0.51) || inside(v, 0.58, 0.63))) return AMBER;
    return WHITE;
  }
  return NAVY;
}

const files = [
  ['icon-192.png', 192, 192],
  ['icon-512.png', 512, 512],
  ['icon-maskable-512.png', 512, 512],
  ['og-image.png', 1200, 630]
];
for (const [name, w, h] of files) {
  fs.writeFileSync(path.join(OUT, name), png(w, h, draw));
  console.log('created', name);
}