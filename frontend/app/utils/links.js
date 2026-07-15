/**
 * Seed link-editor rows so every predefined label has an open row up-front,
 * pre-filled from any saved link with a matching label (case-insensitive).
 * Saved links whose label is not predefined (custom) are appended after.
 *
 * The save path filters out rows with an empty url, so seeded-but-unfilled
 * predefined rows are never persisted.
 *
 * @param {Array<{label?: string, url?: string}>} savedLinks
 * @param {string[]} predefinedLabels
 * @returns {Array<{label: string, url: string, isCustomLabel: boolean}>}
 */
export function seedPredefinedLinks(savedLinks, predefinedLabels) {
  const saved = savedLinks || [];
  const byLabel = new Map(
    saved
      .filter((link) => link?.label)
      .map((link) => [link.label.trim().toLowerCase(), link])
  );

  const rows = predefinedLabels.map((label) => {
    const match = byLabel.get(label.trim().toLowerCase());
    return { label, url: match?.url || "", isCustomLabel: false };
  });

  const predefinedLower = new Set(predefinedLabels.map((l) => l.trim().toLowerCase()));
  const customRows = saved
    .filter((link) => link?.label && !predefinedLower.has(link.label.trim().toLowerCase()))
    .map((link) => ({ label: link.label || "", url: link.url || "", isCustomLabel: true }));

  return [...rows, ...customRows];
}
