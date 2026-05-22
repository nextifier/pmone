/**
 * Payment method channel → logo file mapping.
 * Mirror of `App\Services\Reservation\DocumentService::channelLogoFile()` so the
 * web UI renders the same SVG used in invoice/receipt PDFs.
 */
const CHANNEL_LOGO_MAP: Record<string, { file: string; label: string }> = {
  BCA: { file: "bca.svg", label: "BCA" },
  BNI: { file: "bni.svg", label: "BNI" },
  BRI: { file: "bri.svg", label: "BRI" },
  MANDIRI: { file: "mandiri.svg", label: "Mandiri" },
  PERMATA: { file: "permata-bank.svg", label: "Permata Bank" },
  BSI: { file: "bsi.svg", label: "BSI" },
  BSS: { file: "bss.svg", label: "Bank Sahabat Sampoerna" },
  CIMB: { file: "cimb-niaga.svg", label: "CIMB Niaga" },
  CIMB_NIAGA: { file: "cimb-niaga.svg", label: "CIMB Niaga" },
  BJB: { file: "bjb.svg", label: "BJB" },
  BNC: { file: "neobank.svg", label: "Neobank" },
  NEOBANK: { file: "neobank.svg", label: "Neobank" },
  MUAMALAT: { file: "bank-muamalat.svg", label: "Bank Muamalat" },
  GOPAY: { file: "gopay.svg", label: "GoPay" },
  OVO: { file: "ovo.svg", label: "OVO" },
  DANA: { file: "dana.svg", label: "DANA" },
  SHOPEEPAY: { file: "shopeepay.svg", label: "ShopeePay" },
  LINKAJA: { file: "link-aja.svg", label: "LinkAja" },
  JENIUSPAY: { file: "jeniuspay.svg", label: "JeniusPay" },
  NEXCASH: { file: "nexcash.svg", label: "NexCash" },
  ASTRAPAY: { file: "astrapay.svg", label: "AstraPay" },
  QRIS: { file: "qris.svg", label: "QRIS" },
  VISA: { file: "visa.svg", label: "Visa" },
  MASTERCARD: { file: "mastercard.svg", label: "Mastercard" },
  AMEX: { file: "amex.svg", label: "AMEX" },
  JCB: { file: "jcb.svg", label: "JCB" },
  DD_BRI: { file: "dd-bri.svg", label: "BRI Direct Debit" },
  BRI_DIRECT_DEBIT: { file: "dd-bri.svg", label: "BRI Direct Debit" },
};

// `xendit` is the gateway, not a channel the customer actually paid with, so it
// has no displayable label of its own and is deliberately absent here. A Xendit
// reservation with no channel yet (typically still unpaid) resolves to an empty
// state in the Payment column — the Status column already conveys whether
// payment has happened. Only methods meaningful without a channel (manual
// transfer, complimentary) carry a label.
const METHOD_LABEL_MAP: Record<string, string> = {
  manual_bank_transfer: "Manual Bank Transfer",
  complimentary: "Complimentary",
};

function normalize(value?: string | null): string | null {
  if (!value) return null;
  return value.toString().toUpperCase().replace(/[\s-]+/g, "_");
}

export function getPaymentLogoUrl(channel?: string | null): string | null {
  const key = normalize(channel);
  if (!key) return null;
  const entry = CHANNEL_LOGO_MAP[key];
  return entry ? `/images/payment-methods/${entry.file}` : null;
}

export function getPaymentChannelLabel(channel?: string | null): string | null {
  const key = normalize(channel);
  if (!key) return null;
  return CHANNEL_LOGO_MAP[key]?.label ?? channel ?? null;
}

export function getPaymentMethodLabel(method?: string | null): string | null {
  if (!method) return null;
  return METHOD_LABEL_MAP[method.toLowerCase()] ?? null;
}
