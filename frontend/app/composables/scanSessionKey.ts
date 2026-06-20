import type { InjectionKey } from "vue";
import type { useScanSession } from "./useScanSession";

/**
 * Provided once by the scanner shell (`pages/scan/[eventId].vue`) and injected
 * by every child view (Scan / Find / Activity). Keeping the session in the
 * parent means the Bluetooth printer connection, attendee manifest and outbox
 * survive tab switches instead of re-initialising on every navigation.
 */
export const SCAN_SESSION: InjectionKey<ReturnType<typeof useScanSession>> =
  Symbol("scan-session");
