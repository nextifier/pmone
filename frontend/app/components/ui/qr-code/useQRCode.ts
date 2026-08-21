import { useLocalStorage } from "@vueuse/core";
import type QRCodeLib from "qrcode";

export type QRStyleVariant = "rounded" | "square";

export interface QRSvgOptions {
  size?: number;
  margin?: number;
  fgColor?: string;
  bgColor?: string;
  errorCorrectionLevel?: "L" | "M" | "Q" | "H";
  styleVariant?: QRStyleVariant;
}

function isInFinderPattern(row: number, col: number, size: number): boolean {
  return (
    (row < 7 && col < 7) ||
    (row < 7 && col >= size - 7) ||
    (row >= size - 7 && col < 7)
  );
}

/**
 * Tilt and size of a `rounded` data module, keyed by which of its four
 * orthogonal neighbours are dark: "UP LEFT RIGHT DOWN". A module with both
 * horizontal or both vertical neighbours set sits inside a run and takes the
 * gentle shared tilt, so runs still read as runs; isolated modules and run ends
 * take the stronger angles. `scale` is a fraction of one module.
 */
const TILT_BY_NEIGHBOURS: Record<string, { angle: number; scale: number }> = {
  "0000": { angle: -10.01, scale: 0.8632 },
  "0001": { angle: -3.014, scale: 0.9513 },
  "0010": { angle: -15.945, scale: 0.728 },
  "0011": { angle: -7.764, scale: 0.8882 },
  "0100": { angle: 7.125, scale: 0.8063 },
  "0101": { angle: 15.751, scale: 0.8105 },
  "0110": { angle: 2.602, scale: 0.8809 },
  "0111": { angle: 2.602, scale: 0.8809 },
  "1000": { angle: -6.339, scale: 0.9056 },
  "1001": { angle: 2.602, scale: 0.8809 },
  "1010": { angle: 8.499, scale: 0.8797 },
  "1011": { angle: 2.602, scale: 0.8809 },
  "1100": { angle: -11.576, scale: 0.8473 },
  "1101": { angle: 2.602, scale: 0.8809 },
  "1110": { angle: 2.602, scale: 0.8809 },
  "1111": { angle: 2.602, scale: 0.8809 },
};

/** Dark *and* drawn: finder-pattern modules render as rings, not as squares. */
function isDrawnModule(
  qrData: QRCodeLib.QRCode,
  row: number,
  col: number,
  moduleCount: number
): boolean {
  if (row < 0 || col < 0 || row >= moduleCount || col >= moduleCount) {
    return false;
  }
  if (isInFinderPattern(row, col, moduleCount)) return false;
  return Boolean(qrData.modules.get(row, col));
}

let qrcodeLib: typeof QRCodeLib | null = null;

/**
 * Lazy client-only import: the encoder is ~20KB and is never needed during SSR.
 */
export async function loadQRCodeLib(): Promise<typeof QRCodeLib> {
  if (!qrcodeLib) {
    const mod = await import("qrcode");
    qrcodeLib = mod.default;
  }
  return qrcodeLib;
}

/**
 * Resolve a CSS color value (including CSS variables) to a hex string.
 * Falls back to the original value if resolution fails.
 */
function resolveColor(color: string): string {
  if (!color.startsWith("var(")) return color;

  try {
    const style = getComputedStyle(document.documentElement);
    const varName = color.replace(/^var\(/, "").replace(/\)$/, "").trim();
    const resolved = style.getPropertyValue(varName).trim();
    return resolved || color;
  } catch {
    return color;
  }
}

export function buildQRSvgString(
  qrData: QRCodeLib.QRCode,
  options: QRSvgOptions = {}
): string {
  const {
    size = 268,
    margin = 0,
    fgColor = "#000000",
    bgColor = "#FFFFFF",
    styleVariant = "square",
  } = options;

  const moduleCount = qrData.modules.size;
  const totalModules = moduleCount + margin * 2;
  const moduleSize = size / totalModules;
  const offset = margin * moduleSize;

  const finderPositions: [number, number][] = [
    [0, 0],
    [0, moduleCount - 7],
    [moduleCount - 7, 0],
  ];

  const parts: string[] = [];

  if (styleVariant === "square") {
    // Background (no corner radius for classic look)
    parts.push(
      `<rect width="${size}" height="${size}" fill="${bgColor}"/>`
    );

    // Finder patterns: eye frame as a stroke square (transparent centre) + pupil,
    // so the QR background can be transparent and still read as a proper frame.
    for (const [r, c] of finderPositions) {
      const x = offset + c * moduleSize;
      const y = offset + r * moduleSize;
      parts.push(
        `<rect x="${x + moduleSize / 2}" y="${y + moduleSize / 2}" width="${6 * moduleSize}" height="${6 * moduleSize}" fill="none" stroke="${fgColor}" stroke-width="${moduleSize}"/>`
      );
      parts.push(
        `<rect x="${x + 2 * moduleSize}" y="${y + 2 * moduleSize}" width="${3 * moduleSize}" height="${3 * moduleSize}" fill="${fgColor}"/>`
      );
    }

    // Data modules (squares)
    for (let row = 0; row < moduleCount; row++) {
      for (let col = 0; col < moduleCount; col++) {
        if (
          qrData.modules.get(row, col) &&
          !isInFinderPattern(row, col, moduleCount)
        ) {
          const x = offset + col * moduleSize;
          const y = offset + row * moduleSize;
          parts.push(
            `<rect x="${x}" y="${y}" width="${moduleSize}" height="${moduleSize}" fill="${fgColor}"/>`
          );
        }
      }
    }
  } else {
    // Background (rounded container)
    parts.push(
      `<rect width="${size}" height="${size}" fill="${bgColor}" rx="12" ry="12"/>`
    );

    // Finder patterns: eye frame as a stroke ring (transparent centre) + pupil,
    // so the QR background can be transparent and still read as a proper ring.
    for (const [r, c] of finderPositions) {
      const cx = offset + (c + 3.5) * moduleSize;
      const cy = offset + (r + 3.5) * moduleSize;

      parts.push(
        `<circle cx="${cx}" cy="${cy}" r="${3 * moduleSize}" fill="none" stroke="${fgColor}" stroke-width="${moduleSize}"/>`
      );
      parts.push(
        `<circle cx="${cx}" cy="${cy}" r="${1.5 * moduleSize}" fill="${fgColor}"/>`
      );
    }

    // Data modules: squares tilted by their neighbourhood (TILT_BY_NEIGHBOURS).
    for (let row = 0; row < moduleCount; row++) {
      for (let col = 0; col < moduleCount; col++) {
        if (!isDrawnModule(qrData, row, col, moduleCount)) continue;

        const key =
          `${+isDrawnModule(qrData, row - 1, col, moduleCount)}` +
          `${+isDrawnModule(qrData, row, col - 1, moduleCount)}` +
          `${+isDrawnModule(qrData, row, col + 1, moduleCount)}` +
          `${+isDrawnModule(qrData, row + 1, col, moduleCount)}`;
        const { angle, scale } = TILT_BY_NEIGHBOURS[key]!;

        const side = moduleSize * scale;
        const cx = offset + (col + 0.5) * moduleSize;
        const cy = offset + (row + 0.5) * moduleSize;

        parts.push(
          `<rect x="${cx - side / 2}" y="${cy - side / 2}" width="${side}" height="${side}" fill="${fgColor}" transform="rotate(${angle} ${cx} ${cy})"/>`
        );
      }
    }
  }

  return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">${parts.join("")}</svg>`;
}

/**
 * Shared module style (square / rounded), persisted so a deliberate choice
 * survives navigation and follows every QR the app renders. The key is
 * app-agnostic on purpose: this component ships identically to every project.
 */
export function useQRCodeStyle() {
  const qrStyle = useLocalStorage<QRStyleVariant>("ui:qr-code-style", "square");

  const toggleQrStyle = () => {
    qrStyle.value = qrStyle.value === "rounded" ? "square" : "rounded";
  };

  return { qrStyle, toggleQrStyle };
}

export function useQRCode() {
  const { qrStyle } = useQRCodeStyle();

  async function createQRData(
    value: string,
    errorCorrectionLevel: "L" | "M" | "Q" | "H" = "M"
  ): Promise<QRCodeLib.QRCode | null> {
    try {
      const lib = await loadQRCodeLib();
      return lib.create(value, { errorCorrectionLevel });
    } catch {
      return null;
    }
  }

  async function generateSvgString(
    value: string,
    options: QRSvgOptions = {}
  ): Promise<string> {
    const qrData = await createQRData(
      value,
      options.errorCorrectionLevel || "M"
    );
    if (!qrData) return "";
    return buildQRSvgString(qrData, options);
  }

  async function downloadSVG(
    value: string,
    filename: string,
    options: QRSvgOptions = {}
  ): Promise<void> {
    const svgString = await generateSvgString(value, {
      ...options,
      size: options.size || 512,
      margin: options.margin ?? 2,
      fgColor: resolveColor(options.fgColor || "var(--foreground)"),
      bgColor: resolveColor(options.bgColor || "var(--background)"),
      styleVariant: options.styleVariant || qrStyle.value,
    });
    if (!svgString) return;

    const blob = new Blob([svgString], { type: "image/svg+xml" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.download = filename;
    a.href = url;
    a.click();
    URL.revokeObjectURL(url);
  }

  async function downloadJPG(
    value: string,
    filename: string,
    options: QRSvgOptions = {}
  ): Promise<void> {
    const downloadSize = options.size || 1080;
    const svgString = await generateSvgString(value, {
      ...options,
      size: downloadSize,
      margin: options.margin ?? 2,
      fgColor: resolveColor(options.fgColor || "var(--foreground)"),
      bgColor: resolveColor(options.bgColor || "var(--background)"),
      styleVariant: options.styleVariant || qrStyle.value,
    });
    if (!svgString) return;

    const blob = new Blob([svgString], { type: "image/svg+xml" });
    const url = URL.createObjectURL(blob);

    return new Promise<void>((resolve) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement("canvas");
        canvas.width = downloadSize;
        canvas.height = downloadSize;
        const ctx = canvas.getContext("2d")!;
        ctx.drawImage(img, 0, 0, downloadSize, downloadSize);
        URL.revokeObjectURL(url);

        const dataUrl = canvas.toDataURL("image/png");
        const a = document.createElement("a");
        a.download = filename;
        a.href = dataUrl;
        a.click();
        resolve();
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        resolve();
      };
      img.src = url;
    });
  }

  return {
    createQRData,
    generateSvgString,
    downloadSVG,
    downloadJPG,
  };
}
