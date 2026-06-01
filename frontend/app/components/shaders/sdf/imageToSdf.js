/**
 * Offline SVG / PNG -> Signed Distance Field generator for the shader editor.
 *
 * The `shaders` library only *loads* SDFs (see its `loadSdfFromUrl`), it does not
 * generate them - the shapes you see in presets were converted on shaders.com. To
 * stay independent of that subscription (the whole point of pmone.id/shaders) we
 * generate the SDF ourselves, in the browser, from any uploaded SVG or PNG logo.
 *
 * Output format (reverse-engineered from a real preset `.bin` and matching the
 * library's loader exactly):
 *  - 512x512 grid, row-major, origin top-left (same layout the loader expects).
 *  - Signed distance in UV units: pixelDistance / 512. Negative = inside the shape,
 *    positive = outside, ~0 at the boundary.
 *  - Compact Uint16 encoding: u = round((v + 1) * 32767.5); the loader decodes it
 *    back with v = u / 32767.5 - 1, so a full 512*512*2-byte file is detected as the
 *    compact format.
 *
 * Quality: the SDF texture is fixed at 512x512 by the library loader, so we cannot
 * store a higher-resolution field. A binary mask rasterized straight at 512 produces
 * a stair-stepped (aliased) boundary because the zero-crossing snaps to pixel edges.
 * To get smooth, sub-pixel edges we supersample: rasterize and run the EDT at
 * 512*supersample, then box-average the distance field back down to 512. Averaging a
 * high-res signed distance field places the zero-crossing with sub-pixel accuracy, so
 * the bilinear-filtered texture renders smooth edges (matching shaders.com's own SVG
 * shapes - which are also 512 and thus inherently softer than the in-shader analytic
 * primitives).
 */

export const SDF_SIZE = 512;

const INF = 1e20;

/**
 * 1D squared Euclidean distance transform (Felzenszwalb & Huttenlocher).
 * `f` holds the seed costs (0 at feature samples, INF elsewhere); `d` receives the
 * squared distance to the nearest feature. `v`/`z` are reusable scratch buffers.
 */
function edt1d(f, d, v, z, n) {
  let k = 0;
  v[0] = 0;
  z[0] = -INF;
  z[1] = INF;
  for (let q = 1; q < n; q++) {
    let s = (f[q] + q * q - (f[v[k]] + v[k] * v[k])) / (2 * q - 2 * v[k]);
    while (s <= z[k]) {
      k--;
      s = (f[q] + q * q - (f[v[k]] + v[k] * v[k])) / (2 * q - 2 * v[k]);
    }
    k++;
    v[k] = q;
    z[k] = s;
    z[k + 1] = INF;
  }
  k = 0;
  for (let q = 0; q < n; q++) {
    while (z[k + 1] < q) {
      k++;
    }
    const dx = q - v[k];
    d[q] = dx * dx + f[v[k]];
  }
}

/**
 * In-place separable 2D squared EDT over a row-major grid that already holds 0 at
 * feature pixels and INF everywhere else. Leaves squared distances behind.
 */
function edt2d(grid, w, h) {
  const m = Math.max(w, h);
  const f = new Float32Array(m);
  const d = new Float32Array(m);
  const v = new Int32Array(m);
  const z = new Float32Array(m + 1);

  for (let x = 0; x < w; x++) {
    for (let y = 0; y < h; y++) {
      f[y] = grid[y * w + x];
    }
    edt1d(f, d, v, z, h);
    for (let y = 0; y < h; y++) {
      grid[y * w + x] = d[y];
    }
  }

  for (let y = 0; y < h; y++) {
    const off = y * w;
    for (let x = 0; x < w; x++) {
      f[x] = grid[off + x];
    }
    edt1d(f, d, v, z, w);
    for (let x = 0; x < w; x++) {
      grid[off + x] = d[x];
    }
  }
}

/**
 * Turn an inside/outside mask (1 = inside the shape) into a signed distance field
 * in UV units. Runs the EDT twice - once seeded from outside pixels (depth inside
 * the shape) and once from inside pixels (distance outside) - then signs and scales.
 *
 * @param {Uint8Array} mask
 * @param {number} w
 * @param {number} h
 * @returns {Float32Array}
 */
export function maskToSignedSdf(mask, w, h) {
  const n = w * h;

  const inside = new Float32Array(n);
  const outside = new Float32Array(n);
  for (let i = 0; i < n; i++) {
    inside[i] = mask[i] ? INF : 0;
    outside[i] = mask[i] ? 0 : INF;
  }
  edt2d(inside, w, h);
  edt2d(outside, w, h);

  const sdf = new Float32Array(n);
  for (let i = 0; i < n; i++) {
    const dist = mask[i] ? -Math.sqrt(inside[i]) : Math.sqrt(outside[i]);
    sdf[i] = dist / w;
  }
  return sdf;
}

/**
 * Encode a signed UV-space SDF into the library's compact Uint16 representation.
 *
 * @param {Float32Array} sdf
 * @returns {Uint16Array}
 */
export function encodeSdfUint16(sdf) {
  const out = new Uint16Array(sdf.length);
  for (let i = 0; i < sdf.length; i++) {
    const u = Math.round((sdf[i] + 1) * 32767.5);
    out[i] = u < 0 ? 0 : u > 65535 ? 65535 : u;
  }
  return out;
}

/**
 * Load a File into an HTMLImageElement via an object URL, decoding before resolve.
 *
 * @param {File} file
 * @returns {Promise<{ image: HTMLImageElement, revoke: () => void }>}
 */
function loadImageFromFile(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => resolve({ image, revoke: () => URL.revokeObjectURL(url) });
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("File logo tidak bisa dibaca. Pastikan SVG atau PNG yang valid."));
    };
    image.src = url;
  });
}

/**
 * Rasterize an SVG/PNG file onto a square canvas (contain-fit with margin) and
 * derive an inside/outside mask. Prefers the alpha channel; for fully opaque images
 * (a logo on a solid background) it falls back to a luminance difference from the
 * detected background colour, so both dark-on-light and light-on-dark logos work.
 *
 * @param {File} file
 * @param {number} size
 * @param {number} margin fraction of the canvas left empty on each side (0-0.5)
 * @returns {Promise<{ mask: Uint8Array, insideCount: number }>}
 */
async function rasterizeToMask(file, size, margin) {
  const { image, revoke } = await loadImageFromFile(file);
  try {
    let w0 = image.naturalWidth || image.width || 0;
    let h0 = image.naturalHeight || image.height || 0;
    if (!w0 || !h0) {
      w0 = size;
      h0 = size;
    }

    const avail = size * (1 - 2 * margin);
    const scale = Math.min(avail / w0, avail / h0);
    const dw = w0 * scale;
    const dh = h0 * scale;
    const dx = (size - dw) / 2;
    const dy = (size - dh) / 2;

    const canvas = document.createElement("canvas");
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext("2d", { willReadFrequently: true });
    ctx.clearRect(0, 0, size, size);
    ctx.drawImage(image, dx, dy, dw, dh);

    const { data } = ctx.getImageData(0, 0, size, size);
    const n = size * size;
    const mask = new Uint8Array(n);

    let hasAlpha = false;
    for (let i = 0; i < n; i++) {
      if (data[i * 4 + 3] < 250) {
        hasAlpha = true;
        break;
      }
    }

    let insideCount = 0;
    if (hasAlpha) {
      for (let i = 0; i < n; i++) {
        const v = data[i * 4 + 3] >= 128 ? 1 : 0;
        mask[i] = v;
        insideCount += v;
      }
    } else {
      const lum = (o) => 0.299 * data[o] + 0.587 * data[o + 1] + 0.114 * data[o + 2];
      const bg = lum(0);
      for (let i = 0; i < n; i++) {
        const v = Math.abs(lum(i * 4) - bg) > 40 ? 1 : 0;
        mask[i] = v;
        insideCount += v;
      }
    }

    return { mask, insideCount };
  } finally {
    revoke();
  }
}

/**
 * Reconstruct the 512 field from the supersampled one by sampling each destination
 * texel's CENTER (the central 2x2 of its block), not a full box average. A box
 * average is a low-pass filter that rounds off corners and softens thin strokes;
 * centre-sampling keeps the sub-pixel-accurate boundary the hi-res EDT computed while
 * preserving sharp corners, so the result is as crisp as a 512 texture allows.
 *
 * @param {Float32Array} src
 * @param {number} srcSize
 * @param {number} dstSize
 * @param {number} factor srcSize / dstSize (even)
 * @returns {Float32Array}
 */
function downsampleSdf(src, srcSize, dstSize, factor) {
  const dst = new Float32Array(dstSize * dstSize);
  const o = (factor >> 1) - 1; // top-left of the central 2x2 within each block
  for (let y = 0; y < dstSize; y++) {
    for (let x = 0; x < dstSize; x++) {
      const r0 = (y * factor + o) * srcSize + (x * factor + o);
      const r1 = r0 + srcSize;
      dst[y * dstSize + x] = (src[r0] + src[r0 + 1] + src[r1] + src[r1 + 1]) * 0.25;
    }
  }
  return dst;
}

/**
 * Full pipeline: uploaded SVG/PNG -> supersampled signed SDF -> 512 compact Uint16 -> Blob.
 *
 * @param {File} file
 * @param {{ size?: number, margin?: number, supersample?: number }} [options]
 * @returns {Promise<{ sdf: Float32Array, uint16: Uint16Array, blob: Blob, size: number }>}
 */
export async function imageFileToSdf(file, { size = SDF_SIZE, margin = 0.06, supersample = 4 } = {}) {
  const hi = size * supersample;
  const { mask, insideCount } = await rasterizeToMask(file, hi, margin);
  if (insideCount === 0) {
    throw new Error("Tidak ada bentuk yang terdeteksi. Gunakan logo dengan area solid atau latar transparan.");
  }
  const sdfHi = maskToSignedSdf(mask, hi, hi);
  const sdf = supersample === 1 ? sdfHi : downsampleSdf(sdfHi, hi, size, supersample);
  const uint16 = encodeSdfUint16(sdf);
  const blob = new Blob([uint16.buffer], { type: "application/octet-stream" });
  return { sdf, uint16, blob, size };
}
