type CaptureFormat = "png" | "jpeg" | "webp";

interface ShaderCanvasExpose {
  captureImage: (options?: {
    format?: CaptureFormat;
    scale?: number;
    quality?: number;
    maxWidth?: number;
  }) => Promise<Blob>;
}

interface DownloadImageOptions {
  format?: CaptureFormat;
  filename?: string;
  quality?: number;
  maxWidth?: number;
  scale?: number;
}

/**
 * Export helpers for shader canvases. `<ShaderCanvas>` forwards the underlying
 * `<Shader>` `captureImage()` method, which renders the live WebGPU output to a
 * Blob entirely client-side - no server, no shaders.com dependency.
 */
export function useShaderExport() {
  function downloadBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.download = filename;
    a.href = url;
    a.click();
    URL.revokeObjectURL(url);
  }

  async function downloadImage(
    canvasRef: ShaderCanvasExpose | null | undefined,
    options: DownloadImageOptions = {},
  ): Promise<boolean> {
    if (!canvasRef?.captureImage) return false;
    const { format = "jpeg", filename = "shader", quality = 0.92, maxWidth = 2048, scale } = options;
    const blob = await canvasRef.captureImage({ format, quality, maxWidth, scale });
    if (!blob) return false;
    const ext = format === "jpeg" ? "jpg" : format;
    downloadBlob(blob, `${filename}.${ext}`);
    return true;
  }

  return { downloadImage, downloadBlob };
}
