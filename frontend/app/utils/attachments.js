// Shared helpers for rendering uploaded files/attachments consistently.
// Media objects arrive in inconsistent shapes across the app (a bare URL
// string, `{ url }`, `{ original }`, name via `.name`/`.file_name`/`.alt`,
// with or without `.size`/`.mime_type`). `normalizeAttachment` collapses all
// of them into one shape that `<AttachmentLink>` can render.

const IMAGE_EXTENSIONS = ["png", "jpg", "jpeg", "webp", "gif", "svg", "avif"];

export function formatFileSize(bytes) {
  if (!bytes || bytes <= 0) return "";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function extensionOf(name = "", url = "") {
  const source = String(name || url || "").split("?")[0];
  if (!source.includes(".")) return "";
  const ext = source.split(".").pop().toLowerCase();
  return ext.length <= 5 ? ext : "";
}

// All names verified against @iconify-json/hugeicons so every branch renders a
// real icon (there is no `ai-01` in the set, for example).
function iconFor(extension = "", mimeType = "") {
  const ext = extension.toLowerCase();
  const mime = mimeType.toLowerCase();

  if (mime.startsWith("image/") || IMAGE_EXTENSIONS.includes(ext)) return "hugeicons:image-01";
  if (mime.startsWith("video/") || ["mp4", "mov", "avi", "mkv", "webm", "m4v"].includes(ext)) {
    return "hugeicons:file-video";
  }
  if (mime.startsWith("audio/") || ["mp3", "wav", "ogg", "m4a", "aac", "flac"].includes(ext)) {
    return "hugeicons:file-audio";
  }
  if (mime === "application/pdf" || ext === "pdf") return "hugeicons:pdf-01";
  if (ext === "csv" || mime === "text/csv") return "hugeicons:csv-01";
  if (["xls", "xlsx"].includes(ext) || mime.includes("spreadsheet") || mime.includes("excel")) {
    return "hugeicons:xls-01";
  }
  if (["ppt", "pptx"].includes(ext) || mime.includes("presentation") || mime.includes("powerpoint")) {
    return "hugeicons:ppt-01";
  }
  if (["doc", "docx"].includes(ext) || mime.includes("word")) return "hugeicons:doc-01";
  if (ext === "txt" || mime === "text/plain") return "hugeicons:txt-01";
  if (ext === "ai" || mime.includes("illustrator") || mime.includes("postscript")) {
    return "hugeicons:ai-file";
  }
  if (["zip", "rar", "7z", "tar", "gz"].includes(ext) || mime.includes("zip") || mime.includes("compressed")) {
    return "hugeicons:zip-01";
  }
  return "hugeicons:file-01";
}

/**
 * Normalize any media reference into a single shape.
 * @returns {{ url: string|null, name: string, size: number|null, mimeType: string, extension: string, isImage: boolean, icon: string }|null}
 */
export function normalizeAttachment(media, { fallbackName = "Attachment" } = {}) {
  if (!media) return null;

  if (typeof media === "string") {
    const extension = extensionOf("", media);
    return {
      url: media,
      name: fallbackName,
      size: null,
      mimeType: "",
      extension,
      isImage: false,
      icon: iconFor(extension),
    };
  }

  const url = media.url || media.original || media.href || null;
  const name = media.name || media.file_name || media.alt || media.title || fallbackName;
  const size = media.size ?? null;
  const mimeType = media.mime_type || media.mimeType || "";
  const extension = (media.extension || extensionOf(name, url)).toLowerCase();
  const isImage = (mimeType.startsWith("image/") || IMAGE_EXTENSIONS.includes(extension)) && !!url;

  return { url, name, size, mimeType, extension, isImage, icon: iconFor(extension, mimeType) };
}
