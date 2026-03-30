import type QRCodeLib from "qrcode";

interface QRSvgOptions {
  size?: number;
  margin?: number;
  fgColor?: string;
  bgColor?: string;
  errorCorrectionLevel?: "L" | "M" | "Q" | "H";
}

function isInFinderPattern(row: number, col: number, size: number): boolean {
  return (
    (row < 7 && col < 7) ||
    (row < 7 && col >= size - 7) ||
    (row >= size - 7 && col < 7)
  );
}

let qrcodeLib: typeof QRCodeLib | null = null;

async function loadLib(): Promise<typeof QRCodeLib> {
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
  } = options;

  const moduleCount = qrData.modules.size;
  const totalModules = moduleCount + margin * 2;
  const moduleSize = size / totalModules;
  const offset = margin * moduleSize;
  const circleRadius = moduleSize * (1 / 3);

  const finderPositions: [number, number][] = [
    [0, 0],
    [0, moduleCount - 7],
    [moduleCount - 7, 0],
  ];

  const parts: string[] = [];

  // Background
  parts.push(
    `<rect width="${size}" height="${size}" fill="${bgColor}" rx="12" ry="12"/>`
  );

  // Finder patterns (circular eye frame + eye ball)
  for (const [r, c] of finderPositions) {
    const cx = offset + (c + 3.5) * moduleSize;
    const cy = offset + (r + 3.5) * moduleSize;
    const outerR = 3.5 * moduleSize;
    const middleR = 2.5 * moduleSize;
    const innerR = 1.5 * moduleSize;

    // Eye frame: outer circle (fg)
    parts.push(
      `<circle cx="${cx}" cy="${cy}" r="${outerR}" fill="${fgColor}"/>`
    );
    // Eye frame: gap circle (bg)
    parts.push(
      `<circle cx="${cx}" cy="${cy}" r="${middleR}" fill="${bgColor}"/>`
    );
    // Eye ball: inner circle (fg)
    parts.push(
      `<circle cx="${cx}" cy="${cy}" r="${innerR}" fill="${fgColor}"/>`
    );
  }

  // Data modules (circles)
  for (let row = 0; row < moduleCount; row++) {
    for (let col = 0; col < moduleCount; col++) {
      if (
        qrData.modules.get(row, col) &&
        !isInFinderPattern(row, col, moduleCount)
      ) {
        const cx = offset + (col + 0.5) * moduleSize;
        const cy = offset + (row + 0.5) * moduleSize;
        parts.push(
          `<circle cx="${cx}" cy="${cy}" r="${circleRadius}" fill="${fgColor}"/>`
        );
      }
    }
  }

  return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">${parts.join("")}</svg>`;
}

export function useQRCode() {
  async function createQRData(
    value: string,
    errorCorrectionLevel: "L" | "M" | "Q" | "H" = "M"
  ): Promise<QRCodeLib.QRCode | null> {
    try {
      const lib = await loadLib();
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
