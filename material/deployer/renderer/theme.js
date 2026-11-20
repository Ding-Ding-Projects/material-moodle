'use strict';
// Tonal light and dark palettes generated from one seed colour.
// The base tables are tuned for the default seed #8B5000; other seeds rotate their hue.
(function (root) {
  const DEFAULT_SEED = '#8B5000';
  const LIGHT = { bg: '#FFF8F5', low: '#FCF2EB', c: '#F6ECE5', high: '#F0E6DF', top: '#EBE0D9', on: '#201A17', onv: '#51443A', outline: '#84746A', ov: '#D6C3B6', sc: '#F8DEC6', onsc: '#291806', tc: '#DCE8B4', ontc: '#161E01', err: '#BA1A1A', errc: '#FFDAD6', onerrc: '#410002', ok: '#1F6B2C', okc: '#B7F1B4', onokc: '#002106', inv: '#362F2B', oninv: '#FBEEE7', card: '#FFFFFF' };
  const DARK = { bg: '#18120D', low: '#201A16', c: '#251E1A', high: '#302824', top: '#3B332E', on: '#EDE0D9', onv: '#D6C3B6', outline: '#9E8E83', ov: '#51443A', sc: '#5A432C', onsc: '#FFDCBE', tc: '#414B24', ontc: '#DCE8B4', err: '#FFB4AB', errc: '#93000A', onerrc: '#FFDAD6', ok: '#9CD69A', okc: '#00531B', onokc: '#B7F1B4', inv: '#EDE0D9', oninv: '#362F2B', card: '#251E1A' };
  const FIXED = ['err', 'errc', 'onerrc', 'ok', 'okc', 'onokc', 'tc', 'ontc'];

  function mix(hex, w, toWhite) {
    const n = parseInt(hex.slice(1), 16);
    const c = [n >> 16 & 255, n >> 8 & 255, n & 255];
    return '#' + c.map((v) => Math.round(v + ((toWhite ? 255 : 0) - v) * w).toString(16).padStart(2, '0')).join('');
  }

  function toHsl(hex) {
    const n = parseInt(hex.slice(1), 16);
    const r = (n >> 16 & 255) / 255, g = (n >> 8 & 255) / 255, b = (n & 255) / 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h = 0, s = 0;
    const l = (max + min) / 2;
    if (max !== min) {
      const d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
      h *= 60;
    }
    return [h, s, l];
  }

  function fromHsl(h, s, l) {
    const f = (n) => {
      const k = (n + h / 30) % 12;
      const a = s * Math.min(l, 1 - l);
      return Math.round((l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1))) * 255).toString(16).padStart(2, '0');
    };
    return '#' + f(0) + f(8) + f(4);
  }

  function rotate(hex, delta) {
    const [h, s, l] = toHsl(hex);
    return fromHsl((h + delta + 360) % 360, s, l);
  }

  function palette(seed, dark) {
    const a = /^#[0-9a-fA-F]{6}$/.test(seed || '') ? seed.toUpperCase() : DEFAULT_SEED;
    const base = dark ? DARK : LIGHT;
    const delta = toHsl(a)[0] - toHsl(DEFAULT_SEED)[0];
    const t = {};
    for (const [k, v] of Object.entries(base)) t[k] = a === DEFAULT_SEED || FIXED.includes(k) ? v : rotate(v, delta);
    t.p = dark ? mix(a, 0.55, true) : a;
    t.onp = dark ? (a === DEFAULT_SEED ? '#21160A' : mix(a, 0.85, false)) : '#FFFFFF';
    t.pc = dark ? mix(a, 0.4, false) : mix(a, 0.8, true);
    t.onpc = dark ? mix(a, 0.82, true) : mix(a, 0.6, false);
    return t;
  }

  function apply(seed, dark) {
    const t = palette(seed, dark);
    const style = document.documentElement.style;
    for (const [k, v] of Object.entries(t)) style.setProperty('--' + k, v);
    document.documentElement.dataset.scheme = dark ? 'dark' : 'light';
    return t;
  }

  root.MMDTheme = { palette, apply, mix, DEFAULT_SEED, SWATCHES: ['#8B5000', '#006A6A', '#4F5B92', '#9C4146', '#5B6300', '#7A4F9A'] };
})(globalThis);
