/**
 * Helpers for the custom Xendit Components channel picker.
 *
 * Groups channels returned by `sdk.getActiveChannels()` into Cards / E-Wallet
 * / QR Code (plus Direct Debit + Other as fallbacks). Pure functions, no SDK /
 * Vue / Nuxt imports - typed against a structural shape so they can be
 * unit-tested in isolation.
 *
 * Virtual Account support: `xendit-components-web` v0.0.22 does not yet
 * render VA action UI, so VA channels are not returned by getActiveChannels()
 * regardless of account configuration (Xendit's own docs at
 * https://docs.xendit.co/docs/components-overview confirm "Currently Xendit
 * Components only available for CARDS"; e-wallet was added in SDK 0.0.19,
 * QR in 0.0.18, VA still unreleased). The bank_transfer group definition
 * below stays in place purely as a forward-compatibility hook: if a future
 * SDK release starts returning VA channels with `uiGroup.groupId ===
 * "bank_transfer"`, the picker will render them automatically.
 */

/**
 * Subset of the Xendit Payment Channel shape we rely on. Everything else on
 * the channel object (cardBrands, minAmount, brandColor, ...) is opaque to
 * grouping/lookup.
 *
 * The SDK only formally exposes `uiGroup` (with groupId of "cards" |
 * "bank_transfer" | "online_banking" | "other"). To split "other" into
 * E-Wallet vs QR Code (the way customers expect to see options listed), we
 * also match against channel-code patterns.
 */
export interface XenditChannelLike {
  channelCode: string | string[];
  brandName?: string;
  brandLogoUrl?: string;
  uiGroup?: { groupId?: string; label?: string } | null;
}

export interface ChannelGroup {
  /** Stable id used as Accordion v-model value. */
  id: string;
  /** Display label rendered in the AccordionTrigger. */
  label: string;
  /** Channels belonging to this group, in SDK order. */
  channels: XenditChannelLike[];
}

/**
 * Normalize a channel code for logo / label lookup.
 *
 *  - Pick the first element if `channelCode` is an array (Xendit returns
 *    `[payCode, payAndSaveCode]` for tokenizable channels like GOPAY).
 *  - Uppercase.
 *  - Replace `-` and whitespace with `_`.
 *  - Strip the trailing `_VIRTUAL_ACCOUNT` suffix so v3 codes like
 *    `BCA_VIRTUAL_ACCOUNT` resolve to `BCA` in the logo map.
 *
 * Mirrors the webhook resolver logic in
 * `XenditWebhookController::resolveSessionChannel` (PHP) so the picker
 * normalizes consistently with what we eventually persist in the database.
 *
 * IMPORTANT: this output is for logo / label lookup ONLY. Pass the raw
 * channel object (with its original `channelCode`) to SDK methods like
 * `setCurrentChannel()` / `createChannelComponent()`.
 */
export function normalizeChannelCode(raw: string | string[] | null | undefined): string {
  if (!raw) return "";
  const first = Array.isArray(raw) ? raw[0] : raw;
  if (!first) return "";
  const upper = String(first).toUpperCase().replace(/[\s-]+/g, "_");
  return upper.endsWith("_VIRTUAL_ACCOUNT") ? upper.slice(0, -"_VIRTUAL_ACCOUNT".length) : upper;
}

const EWALLET_CODES = new Set([
  "OVO",
  "DANA",
  "GOPAY",
  "SHOPEEPAY",
  "LINKAJA",
  "JENIUSPAY",
  "ASTRAPAY",
  "NEXCASH",
  "JENIUS_PAY",
]);

const QR_CODES = new Set(["QRIS", "DUITNOW_QR", "PROMPTPAY", "PAYNOW"]);

const DIRECT_DEBIT_CODES = new Set([
  "DD_BRI",
  "BRI_DIRECT_DEBIT",
  "DD_BCA",
  "DD_MANDIRI",
]);

const CARDS_CODES = new Set(["CARDS", "CREDIT_CARD"]);

interface GroupDefinition {
  id: string;
  label: string;
  matches: (channel: XenditChannelLike, normalizedCode: string) => boolean;
}

const GROUP_DEFS: GroupDefinition[] = [
  {
    id: "cards",
    label: "Kartu Kredit / Debit",
    matches: (c, code) =>
      CARDS_CODES.has(code) || c.uiGroup?.groupId === "cards",
  },
  {
    id: "ewallet",
    label: "E-Wallet",
    matches: (_c, code) => EWALLET_CODES.has(code),
  },
  {
    id: "qr_code",
    label: "QR Code",
    matches: (_c, code) => QR_CODES.has(code),
  },
  {
    // Forward-compat only - SDK v0.0.22 does not return VA channels via
    // getActiveChannels(). Group falls through to OTHER if no SDK channel
    // declares uiGroup.groupId === "bank_transfer", which is the current
    // behavior. Will start rendering automatically when SDK adds VA support.
    id: "bank_transfer",
    label: "Transfer Bank (Virtual Account)",
    matches: (c) => c.uiGroup?.groupId === "bank_transfer",
  },
  {
    id: "direct_debit",
    label: "Direct Debit & Internet Banking",
    matches: (c, code) =>
      DIRECT_DEBIT_CODES.has(code) || c.uiGroup?.groupId === "online_banking",
  },
];

const OTHER_GROUP_ID = "other";
const OTHER_GROUP_LABEL = "Lainnya";

/**
 * Group SDK channels by visual category. Empty groups are omitted so the
 * accordion only renders sections that have at least one channel.
 */
export function groupChannels(channels: XenditChannelLike[] | null | undefined): ChannelGroup[] {
  if (!channels?.length) return [];

  const buckets = new Map<string, XenditChannelLike[]>();
  for (const def of GROUP_DEFS) {
    buckets.set(def.id, []);
  }
  buckets.set(OTHER_GROUP_ID, []);

  for (const channel of channels) {
    const code = normalizeChannelCode(channel.channelCode);
    const def = GROUP_DEFS.find((g) => g.matches(channel, code));
    const bucket = def ? buckets.get(def.id)! : buckets.get(OTHER_GROUP_ID)!;
    bucket.push(channel);
  }

  const result: ChannelGroup[] = [];
  for (const def of GROUP_DEFS) {
    const list = buckets.get(def.id)!;
    if (list.length > 0) {
      result.push({ id: def.id, label: def.label, channels: list });
    }
  }
  const other = buckets.get(OTHER_GROUP_ID)!;
  if (other.length > 0) {
    result.push({ id: OTHER_GROUP_ID, label: OTHER_GROUP_LABEL, channels: other });
  }

  return result;
}

/**
 * First group becomes the default-expanded accordion item, so the customer
 * sees options immediately rather than a wall of collapsed headers.
 */
export function getDefaultOpenGroupId(groups: ChannelGroup[]): string | null {
  return groups[0]?.id ?? null;
}

/**
 * Stable per-channel key for `v-for :key`. Mirrors normalizeChannelCode so
 * Vue's diffing stays consistent when the SDK emits the same channel
 * across re-renders (e.g. after Accordion expand/collapse).
 */
export function getChannelKey(channel: XenditChannelLike): string {
  return normalizeChannelCode(channel.channelCode);
}
