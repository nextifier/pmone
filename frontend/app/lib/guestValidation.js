// Single source of truth for hotel-booking guest validation. Shared by
// BookingStep3Guest.vue (per-field blur messages) and stores/booking.js
// (canProceedStep3 gate) so the "shows an error but still advances" class of
// bug cannot happen from the two drifting apart.

export const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
export const NIK_RE = /^\d{16}$/;
export const PASSPORT_RE = /^[A-Z0-9]{6,15}$/i;
export const PHONE_RE = /^\+?\d[\d\s-]{5,20}$/;

export function isIdentityValid(guest) {
  const value = (guest.identity_number || "").trim();
  if (!value) return false;
  return guest.identity_type === "passport"
    ? PASSPORT_RE.test(value)
    : NIK_RE.test(value);
}

// True only when every required guest field is present AND correctly formatted.
export function isGuestValid(guest) {
  return (
    !!guest.name?.trim() &&
    EMAIL_RE.test((guest.email || "").trim()) &&
    PHONE_RE.test(String(guest.phone || "").trim()) &&
    isIdentityValid(guest)
  );
}
