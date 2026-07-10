/**
 * Wrap a single image URL (object URL for an unsaved upload, or a saved media
 * URL) into the shape the Avatar component reads: every conversion key points
 * at the same URL.
 */
export function toImageModel(url) {
  if (!url) return null;
  return { url, sm: url, md: url, lg: url, xl: url };
}
