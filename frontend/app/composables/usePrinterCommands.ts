/**
 * Builder byte-array untuk command printer thermal label.
 * Mendukung dua protocol:
 * - TSPL (TSC Printer Language) - dialek umum di label printer (Clabel, TSC, Xprinter, dsb).
 * - ESC/POS - dialek umum di receipt printer, beberapa label printer support sebagai fallback.
 *
 * Pure functions tanpa dependency ke Bluetooth API. Output `Uint8Array` siap dikirim ke
 * BLE characteristic.
 */

const encoder = new TextEncoder();

function concatBytes(...chunks: Uint8Array[]): Uint8Array {
  const total = chunks.reduce((sum, c) => sum + c.length, 0);
  const out = new Uint8Array(total);
  let offset = 0;
  for (const chunk of chunks) {
    out.set(chunk, offset);
    offset += chunk.length;
  }
  return out;
}

function asciiBytes(str: string): Uint8Array {
  return encoder.encode(str);
}

/**
 * Render canvas (RGBA) → 1-bit monochrome packed bytes (MSB first), row by row.
 * Pixel hitam (lum < threshold) → bit 1, putih → bit 0.
 * Width dipadkan ke kelipatan 8.
 */
function canvasToMonoBytes(
  canvas: HTMLCanvasElement,
  threshold = 128,
  invertBits = false
): { data: Uint8Array; widthBytes: number; height: number; width: number } {
  const ctx = canvas.getContext("2d");
  if (!ctx) {
    throw new Error("Canvas context tidak tersedia");
  }
  const { width, height } = canvas;
  const widthBytes = Math.ceil(width / 8);
  const padded = widthBytes * 8;
  const imgData = ctx.getImageData(0, 0, width, height).data;
  const out = new Uint8Array(widthBytes * height);

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < padded; x++) {
      let bit = 0;
      if (x < width) {
        const idx = (y * width + x) * 4;
        const r = imgData[idx] ?? 255;
        const g = imgData[idx + 1] ?? 255;
        const b = imgData[idx + 2] ?? 255;
        const a = imgData[idx + 3] ?? 255;
        const lum = (r * 0.299 + g * 0.587 + b * 0.114) * (a / 255);
        if (lum < threshold) {
          bit = 1;
        }
      }
      const byteIndex = y * widthBytes + (x >> 3);
      const bitIndex = 7 - (x & 7);
      const setBit = invertBits ? bit === 0 : bit === 1;
      if (setBit) {
        out[byteIndex] = (out[byteIndex] ?? 0) | (1 << bitIndex);
      }
    }
  }

  return { data: out, widthBytes, height, width };
}

export interface LabelOptions {
  /** Lebar label dalam mm (default 50) */
  widthMm?: number;
  /** Tinggi label dalam mm (default 50) */
  heightMm?: number;
  /** Gap antar label dalam mm (default 2) */
  gapMm?: number;
  /** Density 0-15 (TSPL) */
  density?: number;
}

export interface TsplQrOptions extends LabelOptions {
  name: string;
  qrData: string;
}

// ==========================================================================
// Label layout. Unit: dots (1 mm = 8 dots @ 203 DPI). The name + QR are
// centred as a group - each centred horizontally, the whole block centred
// vertically - and the QR is sized as large as the label allows. Nothing here
// is printer-specific: positions are derived from the *exact* QR module count
// (computed per-token, see computeTsplLayout) plus the label size, so any
// 50x50mm (or other) label stays perfectly centred with no manual nudging.
// ==========================================================================

const DOTS_PER_MM = 8;
// TSPL internal font "3" = fixed 16x24 dots per character (multiplier 1).
const FONT_CHAR_WIDTH = 16;
const FONT_CHAR_HEIGHT = 24;
const FONT_MUL = 1;
// Vertical gap between the name line and the QR (dots).
const GAP_BETWEEN = 16;
// Quiet-zone margin kept around the QR/text block (dots). 24 dots = 3 mm.
const QR_MARGIN = 24;
// Largest TSPL QRCODE cell size (module size in dots) we'll use. The QR grows
// to the biggest cell that still fits the label minus margins, capped here so
// the printed code never spills past the quiet zone.
const CELL_MAX = 10;
// Physical print calibration (dots, +x = right, +y = down). The layout itself
// is mathematically centred; these compensate for the printer's printable
// origin sitting a little in from the label's top-left edge, which otherwise
// makes the whole block print slightly up-and-left. Applied ONLY to the native
// TSPL commands (buildTsplNativeQr) - the digital preview/bitmap stays pure.
// Tune per printer if labels still sit off-centre.
const PRINT_X_OFFSET = 30;
const PRINT_Y_OFFSET = 26;
// Negative letter spacing for the canvas/bitmap preview only (dots @ scale 1);
// TSPL native text can't letter-space, so this never affects real native prints.
const CANVAS_LETTER_SPACING = -4;

interface TsplLayout {
  labelWidthDots: number;
  labelHeightDots: number;
  textX: number;
  textY: number;
  textWidth: number;
  fontHeight: number;
  fontMul: number;
  qrX: number;
  qrY: number;
  qrPixelSize: number;
  cellWidth: number;
}

/**
 * Single source of truth for where the name + QR sit on the label, in dots.
 * Shared by the native-QR command and the canvas/bitmap renderer so both stay
 * pixel-identical.
 *
 * `moduleCount` is the QR's real side length in modules (from QRCode.create on
 * the actual token), so the QR footprint is known exactly instead of guessed -
 * that is what lets the block be perfectly centred. The cell size is grown to
 * the largest that fits the label minus margins (capped at CELL_MAX) so the QR
 * prints as large as possible.
 */
function computeTsplLayout(
  name: string,
  moduleCount: number,
  widthMm: number,
  heightMm: number
): TsplLayout {
  const labelWidthDots = widthMm * DOTS_PER_MM;
  const labelHeightDots = heightMm * DOTS_PER_MM;
  const fontHeight = FONT_CHAR_HEIGHT * FONT_MUL;
  const textWidth = name.length * FONT_CHAR_WIDTH * FONT_MUL;

  // Biggest cell that fits the width AND the height left after the name + gap.
  const safeModules = Math.max(1, moduleCount);
  const maxCellByWidth = Math.floor((labelWidthDots - 2 * QR_MARGIN) / safeModules);
  const maxCellByHeight = Math.floor(
    (labelHeightDots - 2 * QR_MARGIN - fontHeight - GAP_BETWEEN) / safeModules
  );
  const cellWidth = Math.max(1, Math.min(CELL_MAX, maxCellByWidth, maxCellByHeight));
  const qrPixelSize = safeModules * cellWidth;

  // Centre the name + QR as one block: each centred horizontally, the whole
  // stack centred vertically.
  const totalContentHeight = fontHeight + GAP_BETWEEN + qrPixelSize;
  const topMargin = Math.max(0, Math.floor((labelHeightDots - totalContentHeight) / 2));

  return {
    labelWidthDots,
    labelHeightDots,
    textX: Math.max(0, Math.floor((labelWidthDots - textWidth) / 2)),
    textY: topMargin,
    textWidth,
    fontHeight,
    fontMul: FONT_MUL,
    qrX: Math.max(0, Math.floor((labelWidthDots - qrPixelSize) / 2)),
    qrY: topMargin + fontHeight + GAP_BETWEEN,
    qrPixelSize,
    cellWidth,
  };
}

/**
 * TSPL native QR command. Printer yang render QR.
 * Layout: nama di atas, QR di tengah-bawah.
 */
export async function buildTsplNativeQr(opts: TsplQrOptions): Promise<Uint8Array> {
  const widthMm = opts.widthMm ?? 50;
  const heightMm = opts.heightMm ?? 50;
  const gapMm = opts.gapMm ?? 2;
  const density = opts.density ?? 8;

  // Exact module count for THIS token so the QR can be centred and maximised
  // precisely. ECC "H" here must match the `H` in the QRCODE command below;
  // both the library and the printer firmware pick the smallest spec-compliant
  // version for the data, so the counts agree.
  const QRLib = (await import("qrcode")).default;
  const moduleCount = QRLib.create(opts.qrData, { errorCorrectionLevel: "H" }).modules.size;
  const layout = computeTsplLayout(opts.name, moduleCount, widthMm, heightMm);

  const cmd = [
    `SIZE ${widthMm} mm,${heightMm} mm`,
    `GAP ${gapMm} mm,0`,
    `DENSITY ${density}`,
    `SPEED 4`,
    `DIRECTION 0`,
    `REFERENCE 0,0`,
    `CLS`,
    `TEXT ${layout.textX + PRINT_X_OFFSET},${layout.textY + PRINT_Y_OFFSET},"3",0,${layout.fontMul},${layout.fontMul},"${escapeTspl(opts.name)}"`,
    `QRCODE ${layout.qrX + PRINT_X_OFFSET},${layout.qrY + PRINT_Y_OFFSET},H,${layout.cellWidth},A,0,M2,S7,"${escapeTspl(opts.qrData)}"`,
    `PRINT 1,1`,
    ``,
  ].join("\r\n");

  return asciiBytes(cmd);
}

/**
 * TSPL bitmap mode. Render canvas → BITMAP command.
 * BITMAP x,y,width_in_bytes,height,mode,data
 * mode 0 = OVERWRITE
 */
export function buildTsplBitmap(canvas: HTMLCanvasElement, opts: LabelOptions = {}): Uint8Array {
  const widthMm = opts.widthMm ?? 50;
  const heightMm = opts.heightMm ?? 50;
  const gapMm = opts.gapMm ?? 2;
  const density = opts.density ?? 8;

  const { data, widthBytes, height } = canvasToMonoBytes(canvas, 128, true);

  const header = asciiBytes(
    [
      `SIZE ${widthMm} mm,${heightMm} mm`,
      `GAP ${gapMm} mm,0`,
      `DENSITY ${density}`,
      `SPEED 4`,
      `DIRECTION 0`,
      `REFERENCE 0,0`,
      `CLS`,
      `BITMAP 0,0,${widthBytes},${height},0,`,
    ].join("\r\n") + "\r\n"
  );

  const footer = asciiBytes(`\r\nPRINT 1,1\r\n`);

  return concatBytes(header, data, footer);
}

function escapeTspl(text: string): string {
  return text.replace(/"/g, '\\"');
}

export interface EscPosQrOptions {
  name: string;
  qrData: string;
  /** QR module size 1-16, default 8 */
  qrSize?: number;
  /** Error correction: 48=L, 49=M, 50=Q, 51=H. Default 51 (H) */
  qrErrorCorrection?: number;
}

/**
 * ESC/POS native QR command (Model 2).
 * Sequence: init → align center → print name → QR setup+store+print → feed.
 */
export function buildEscPosNativeQr(opts: EscPosQrOptions): Uint8Array {
  const qrSize = Math.min(16, Math.max(1, opts.qrSize ?? 8));
  const qrEc = opts.qrErrorCorrection ?? 51;

  const init = new Uint8Array([0x1b, 0x40]);
  const alignCenter = new Uint8Array([0x1b, 0x61, 0x01]);
  const nameBytes = asciiBytes(opts.name + "\n");

  const qrModel = new Uint8Array([0x1d, 0x28, 0x6b, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00]);
  const qrModuleSize = new Uint8Array([0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x43, qrSize]);
  const qrErrorLevel = new Uint8Array([0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x45, qrEc]);

  const dataBytes = asciiBytes(opts.qrData);
  const storeLen = dataBytes.length + 3;
  const pL = storeLen & 0xff;
  const pH = (storeLen >> 8) & 0xff;
  const qrStoreHeader = new Uint8Array([0x1d, 0x28, 0x6b, pL, pH, 0x31, 0x50, 0x30]);

  const qrPrint = new Uint8Array([0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x51, 0x30]);

  const feed = new Uint8Array([0x0a, 0x0a, 0x0a]);

  return concatBytes(
    init,
    alignCenter,
    nameBytes,
    qrModel,
    qrModuleSize,
    qrErrorLevel,
    qrStoreHeader,
    dataBytes,
    qrPrint,
    feed
  );
}

/**
 * ESC/POS raster bitmap (GS v 0). Lebar harus kelipatan 8.
 */
export function buildEscPosBitmap(canvas: HTMLCanvasElement): Uint8Array {
  const { data, widthBytes, height } = canvasToMonoBytes(canvas);

  const init = new Uint8Array([0x1b, 0x40]);
  const alignCenter = new Uint8Array([0x1b, 0x61, 0x01]);

  const xL = widthBytes & 0xff;
  const xH = (widthBytes >> 8) & 0xff;
  const yL = height & 0xff;
  const yH = (height >> 8) & 0xff;
  const rasterHeader = new Uint8Array([0x1d, 0x76, 0x30, 0x00, xL, xH, yL, yH]);

  const feed = new Uint8Array([0x0a, 0x0a, 0x0a]);

  return concatBytes(init, alignCenter, rasterHeader, data, feed);
}

/**
 * Render preview "what will be printed" ke canvas, pakai layout TSPL yang
 * sama dengan buildTsplNativeQr. Hasil bitmap → posisi & ukuran identik
 * dengan native QR mode (font berbeda karena TSPL pakai font printer
 * built-in, kita pakai monospace browser yang mendekati).
 *
 * `scale` controls canvas resolution:
 * - 1 untuk print (canvas = label dots, mis. 400×400) → match printer pixel
 * - 4-8 untuk preview UI (canvas lebih besar, browser anti-alias smooth)
 */
export async function renderPrintCanvas(opts: {
  name: string;
  qrData: string;
  widthMm?: number;
  heightMm?: number;
  scale?: number;
}): Promise<HTMLCanvasElement> {
  const widthMm = opts.widthMm ?? 50;
  const heightMm = opts.heightMm ?? 50;
  const scale = opts.scale ?? 1;

  const QRLib = (await import("qrcode")).default;
  const moduleCount = QRLib.create(opts.qrData, { errorCorrectionLevel: "H" }).modules.size;
  const layout = computeTsplLayout(opts.name, moduleCount, widthMm, heightMm);

  const widthPx = layout.labelWidthDots * scale;
  const heightPx = layout.labelHeightDots * scale;

  const canvas = document.createElement("canvas");
  canvas.width = widthPx;
  canvas.height = heightPx;
  const ctx = canvas.getContext("2d");
  if (!ctx) {
    throw new Error("Canvas context tidak tersedia");
  }

  ctx.fillStyle = "#FFFFFF";
  ctx.fillRect(0, 0, widthPx, heightPx);

  const fontPx = layout.fontHeight * scale;
  const targetTextWidthPx = layout.textWidth * scale;
  ctx.fillStyle = "#000000";
  ctx.font = `${fontPx}px ui-monospace, "SF Mono", Menlo, Consolas, monospace`;
  ctx.textBaseline = "top";
  ctx.textAlign = "left";
  const ctxAny = ctx as CanvasRenderingContext2D & { letterSpacing?: string };
  if ("letterSpacing" in ctx) {
    ctxAny.letterSpacing = `${CANVAS_LETTER_SPACING * scale}px`;
  }
  const measuredWidth = ctx.measureText(opts.name).width;
  if (measuredWidth > 0 && Math.abs(measuredWidth - targetTextWidthPx) > 1) {
    ctx.save();
    const xScale = targetTextWidthPx / measuredWidth;
    ctx.translate(layout.textX * scale, layout.textY * scale);
    ctx.scale(xScale, 1);
    ctx.fillText(opts.name, 0, 0);
    ctx.restore();
  } else {
    ctx.fillText(opts.name, layout.textX * scale, layout.textY * scale);
  }
  if ("letterSpacing" in ctx) {
    ctxAny.letterSpacing = "0px";
  }

  const qrSizePx = layout.qrPixelSize * scale;
  const qrCanvas = document.createElement("canvas");
  await QRLib.toCanvas(qrCanvas, opts.qrData, {
    width: qrSizePx,
    margin: 0,
    errorCorrectionLevel: "H",
    color: { dark: "#000000", light: "#FFFFFF" },
  });
  ctx.drawImage(qrCanvas, layout.qrX * scale, layout.qrY * scale, qrSizePx, qrSizePx);

  return canvas;
}

/** Hex preview untuk diagnostic log */
export function bytesToHexPreview(bytes: Uint8Array, max = 32): string {
  const slice = bytes.slice(0, max);
  const hex = Array.from(slice)
    .map((b) => b.toString(16).padStart(2, "0"))
    .join(" ");
  return bytes.length > max ? `${hex} … (+${bytes.length - max} bytes)` : hex;
}
