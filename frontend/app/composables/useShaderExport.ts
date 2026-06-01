type CaptureFormat = "png" | "jpeg" | "webp";

interface ShaderCanvasExpose {
  captureImage: (options?: {
    format?: CaptureFormat;
    scale?: number;
    quality?: number;
  }) => Promise<Blob>;
  getCanvas: () => HTMLCanvasElement | null;
  // Re-renders the shader at `pixelRatio` × its CSS size (TRUE high-res, not a
  // bitmap upscale) and returns a stop() that restores the previous ratio.
  beginRecordingResolution?: (pixelRatio: number) => Promise<() => Promise<void>>;
}

interface DownloadImageOptions {
  format?: CaptureFormat;
  filename?: string;
  /** Target output width in px; the canvas is re-rendered to hit it (default 3840 ≈ 4K). */
  targetWidth?: number;
  quality?: number;
}

interface RecordVideoOptions {
  filename?: string;
  durationMs?: number;
  fps?: number;
  /** Target output width in px (default 1920 ≈ 1080p). */
  targetWidth?: number;
  videoBitsPerSecond?: number;
  onProgress?: (fraction: number) => void;
}

const clamp = (n: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, n));

/**
 * Export helpers for shader canvases. Everything runs client-side via the
 * package's WebGPU canvas — no server, no shaders.com dependency.
 *
 * High-resolution capture uses `beginRecordingResolution()` to re-render the
 * shader at a higher device-pixel-ratio (sharp, true resolution) before reading
 * the canvas, instead of upscaling the on-screen bitmap.
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
    const { format = "jpeg", filename = "shader", targetWidth = 3840, quality = 0.96 } = options;

    let stop: (() => Promise<void>) | undefined;
    try {
      const canvas = canvasRef.getCanvas?.();
      if (canvas?.clientWidth && canvasRef.beginRecordingResolution) {
        const ratio = clamp(targetWidth / canvas.clientWidth, 1, 8);
        stop = await canvasRef.beginRecordingResolution(ratio);
      }
      const blob = await canvasRef.captureImage({ format, quality });
      if (!blob) return false;
      const ext = format === "jpeg" ? "jpg" : format;
      downloadBlob(blob, `${filename}.${ext}`);
      return true;
    } finally {
      if (stop) await stop();
    }
  }

  function pickVideoMime(): string {
    // Prefer MP4/H.264 (broadly compatible — Chrome 130+, Safari). Fall back to
    // WebM only where MP4 recording isn't supported.
    const candidates = [
      "video/mp4;codecs=avc1.640033",
      "video/mp4;codecs=avc1.42E01E",
      "video/mp4;codecs=avc1",
      "video/mp4",
      "video/webm;codecs=vp9",
      "video/webm;codecs=vp8",
      "video/webm",
    ];
    const MR = (globalThis as { MediaRecorder?: typeof MediaRecorder }).MediaRecorder;
    for (const m of candidates) {
      if (MR?.isTypeSupported?.(m)) return m;
    }
    return "video/webm";
  }

  /** Record the animated canvas to a video file (WebM) entirely in-browser. */
  async function recordVideo(
    canvasRef: ShaderCanvasExpose | null | undefined,
    options: RecordVideoOptions = {},
  ): Promise<boolean> {
    const canvas = canvasRef?.getCanvas?.();
    if (!canvas || typeof MediaRecorder === "undefined") return false;
    const {
      filename = "shader",
      durationMs = 5000,
      fps = 60,
      targetWidth = 1920,
      videoBitsPerSecond = 24_000_000,
      onProgress,
    } = options;

    let stop: (() => Promise<void>) | undefined;
    let progressTimer: ReturnType<typeof setInterval> | undefined;
    try {
      if (canvas.clientWidth && canvasRef?.beginRecordingResolution) {
        const ratio = clamp(targetWidth / canvas.clientWidth, 1, 4);
        stop = await canvasRef.beginRecordingResolution(ratio);
      }

      const stream = canvas.captureStream(fps);
      const mimeType = pickVideoMime();
      const recorder = new MediaRecorder(stream, { mimeType, videoBitsPerSecond });
      const chunks: BlobPart[] = [];
      recorder.ondataavailable = (e) => {
        if (e.data && e.data.size) chunks.push(e.data);
      };

      const finished = new Promise<void>((resolve) => {
        recorder.onstop = () => resolve();
      });

      const startedAt = performance.now();
      if (onProgress) {
        progressTimer = setInterval(() => {
          onProgress(clamp((performance.now() - startedAt) / durationMs, 0, 1));
        }, 100);
      }

      recorder.start();
      await new Promise((r) => setTimeout(r, durationMs));
      recorder.stop();
      await finished;

      const ext = mimeType.includes("mp4") ? "mp4" : "webm";
      downloadBlob(new Blob(chunks, { type: mimeType }), `${filename}.${ext}`);
      onProgress?.(1);
      return true;
    } finally {
      if (progressTimer) clearInterval(progressTimer);
      if (stop) await stop();
    }
  }

  return { downloadImage, downloadBlob, recordVideo };
}
