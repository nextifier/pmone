import { toast } from "vue-sonner";
import { useVibrate } from "@vueuse/core";

/**
 * The whole check-in scanner session for one event. Lifted out of the old
 * monolithic `pages/scan/[eventId].vue` so the shell can `provide()` it once and
 * the Scan / Find / Activity child views `inject()` it - the printer link,
 * manifest and outbox then persist across tab switches.
 *
 * All scan input methods (camera, hardware gun, manual search) funnel through a
 * single decoded-string entry point: `onScanValue()`.
 */
export function useScanSession(eventId: string) {
  const client = useSanctumClient();
  const { hasPermission } = usePermission();

  const canScan = computed(() => hasPermission("scan.check_in"));
  const scanBase = computed(() => `/api/events/${eventId}/scan`);

  /* ----------------------------- Event context ---------------------------- */
  const eventContext = ref<any>(null);
  const eventTitle = computed(() => eventContext.value?.title || "Event");

  const loadContext = async (): Promise<void> => {
    try {
      const res = await client(`${scanBase.value}/context`);
      eventContext.value = res.data || null;
    } catch {
      // Non-fatal: the header simply falls back to the generic title.
    }
  };

  /* ------------------------------- Manifest ------------------------------- */
  const manifest = ref<any[]>([]);
  const manifestByToken = computed(() => {
    const map = new Map<string, any>();
    for (const a of manifest.value) {
      if (a?.qr_token) map.set(a.qr_token, a);
    }
    return map;
  });
  const manifestKey = computed(() => `pmone:scan-manifest:${eventId}`);
  const outboxKey = computed(() => `pmone:scan-outbox:${eventId}`);
  const soundsKey = computed(() => `pmone:scan-sounds:${eventId}`);

  /* --------------------------- Offline persistence ------------------------ */
  const IDB_STORE = "kv";
  function idbOpen(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
      if (typeof indexedDB === "undefined") return reject(new Error("no-indexeddb"));
      const req = indexedDB.open("pmone-scan", 1);
      req.onupgradeneeded = () => req.result.createObjectStore(IDB_STORE);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }
  async function idbSet(key: string, value: any): Promise<void> {
    const plain = JSON.parse(JSON.stringify(value));
    try {
      const db = await idbOpen();
      await new Promise<void>((resolve, reject) => {
        const tx = db.transaction(IDB_STORE, "readwrite");
        tx.objectStore(IDB_STORE).put(plain, key);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
      });
    } catch {
      try {
        localStorage.setItem(key, JSON.stringify(plain));
      } catch {
        // storage best-effort
      }
    }
  }
  async function idbGet(key: string): Promise<any> {
    try {
      const db = await idbOpen();
      const value = await new Promise<any>((resolve, reject) => {
        const tx = db.transaction(IDB_STORE, "readonly");
        const req = tx.objectStore(IDB_STORE).get(key);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
      });
      if (value !== undefined) return value;
    } catch {
      // fall through to localStorage
    }
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : undefined;
    } catch {
      return undefined;
    }
  }

  /* ------------------------------- Network -------------------------------- */
  const isOnline = ref(true);

  /* ------------------------------- Camera --------------------------------- */
  // Inline live camera (no overlay). The page renders <QrcodeStream> directly
  // and decides when it is visible via a `cameraEnabled` prop; the session only
  // tracks support + the last error so the panel can show the right state.
  const cameraError = ref("");
  const cameraSupported = ref(false);

  /* ------------------------------- Search --------------------------------- */
  const searchQuery = ref("");
  const searchResults = ref<any[]>([]);
  const searchPending = ref(false);
  let searchTimer: ReturnType<typeof setTimeout> | null = null;
  let searchSeq = 0;

  /* ------------------------------- Results -------------------------------- */
  const lastResult = ref<any>(null);
  const recentScans = ref<any[]>([]);
  const manualBusy = ref<string | null>(null);

  /* ------------------------------- Outbox --------------------------------- */
  const outbox = ref<any[]>([]);
  const syncing = ref(false);
  // Mirrors the server's `logs => max:200` sync validation (ScanController::sync)
  // - the outbox is posted in slices this size so a large offline batch syncs
  // across several requests instead of 422ing as one oversized call.
  const SYNC_CHUNK = 200;

  /* ------------------------------- Printer -------------------------------- */
  const {
    status: printerStatus,
    isSupported: printerSupported,
    connect: connectPrinter,
    ensureConnected,
    writeChunked,
    tryRestoreDevice,
    disconnect: disconnectPrinter,
    device: printerDevice,
    savedDeviceName: printerName,
    errorMessage: printerError,
    startKeepAlive,
    stopKeepAlive,
    logs: printerLogs,
    copyLogs: copyPrinterLogs,
    clearLogs: clearPrinterLogs,
    addLog: addPrinterLog,
  } = useBluetoothPrinter();
  const printerConnected = computed(() => printerStatus.value === "connected");
  // "Ready" once a printer has been picked at least once this session - even if
  // the GATT link has since dropped, since printBadge() silently reconnects.
  const printerReady = computed(() => printerConnected.value || !!printerDevice.value);
  const printing = ref(false);
  // Throttle for the "printer asleep, tap to reconnect" auto-print hint so a run
  // of scans against an unreachable printer shows the guidance once, not per scan.
  let lastPrinterHintAt = 0;

  /* --------------------------- Debounce dedupe ---------------------------- */
  // Sliding window: every detection of a token refreshes its timestamp, so a
  // ticket held continuously in front of the camera submits once (the camera's
  // `track` callback fires ~25x/s). Take it away for longer than this window and
  // present it again and it scans afresh - this is what lets staff deliberately
  // re-scan the same ticket without reloading. Kept short because a repeat
  // check-in is idempotent server-side (returns "already_checked_in"), so the
  // only cost of an over-eager re-scan is a harmless redundant request.
  const DEDUPE_MS = 1500;
  const recentTokens = new Map<string, number>();

  const isDuplicateScan = (token: string): boolean => {
    const now = Date.now();
    const last = recentTokens.get(token);
    recentTokens.set(token, now);
    if (recentTokens.size > 200) {
      for (const [k, t] of recentTokens) {
        if (now - t > DEDUPE_MS * 4) recentTokens.delete(k);
      }
    }
    return last !== undefined && now - last < DEDUPE_MS;
  };

  /* ------------------------------ Helpers --------------------------------- */
  const nowTime = (): string =>
    new Intl.DateTimeFormat("en-GB", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    }).format(new Date());

  const beep = (ok = true): void => {
    try {
      const Ctx = window.AudioContext || (window as any).webkitAudioContext;
      if (!Ctx) return;
      const ctx = new Ctx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type = "sine";
      osc.frequency.value = ok ? 880 : 220;
      gain.gain.setValueAtTime(0.0001, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.18);
      osc.start();
      osc.stop(ctx.currentTime + 0.2);
      osc.onended = () => ctx.close();
    } catch {
      // audio is best-effort only
    }
  };

  const vibratePattern = ref<number[]>([]);
  const { vibrate: triggerVibrate, isSupported: vibrateSupported } = useVibrate({
    pattern: vibratePattern,
  });
  const buzz = (pattern: number[]): void => {
    if (!vibrateSupported.value) return;
    vibratePattern.value = pattern;
    try {
      triggerVibrate();
    } catch {
      // best-effort
    }
  };

  /* ------------------------- Scan notification sounds --------------------- */
  const scanSounds = ref({ success_url: "", failed_url: "", enabled: true });
  let successAudio: HTMLAudioElement | null = null;
  let failedAudio: HTMLAudioElement | null = null;

  const buildAudio = (url: string): HTMLAudioElement | null => {
    if (!url) return null;
    try {
      const a = new Audio(url);
      a.preload = "auto";
      a.load();
      return a;
    } catch {
      return null;
    }
  };

  const applyScanSounds = (cfg: any): void => {
    scanSounds.value = {
      success_url: cfg?.success_url || "",
      failed_url: cfg?.failed_url || "",
      enabled: cfg?.enabled !== false,
    };
    successAudio = buildAudio(scanSounds.value.success_url);
    failedAudio = buildAudio(scanSounds.value.failed_url);
  };

  const loadScanSounds = async (): Promise<void> => {
    try {
      const cached = await idbGet(soundsKey.value);
      if (cached) applyScanSounds(cached);
    } catch {
      // ignore malformed cache
    }
    if (!isOnline.value) return;
    try {
      const res = await client("/api/app-settings/scan_sounds");
      applyScanSounds(res?.value || {});
      idbSet(soundsKey.value, scanSounds.value);
    } catch {
      // keep cached/default config
    }
  };

  const playScanSound = (isSuccess: boolean): void => {
    if (!scanSounds.value.enabled) return;
    const audio = isSuccess ? successAudio : failedAudio;
    if (!audio) {
      beep(isSuccess);
      return;
    }
    try {
      audio.currentTime = 0;
      audio.play().catch(() => beep(isSuccess));
    } catch {
      beep(isSuccess);
    }
  };

  /* ----------------------------- Result UI -------------------------------- */
  const RESULT_META: Record<string, any> = {
    checked_in: {
      headline: "Checked in",
      icon: "hugeicons:checkmark-circle-02",
      banner: "bg-success/10 border-success/20 text-success-foreground",
      iconWrap: "bg-success/15 text-success-foreground",
    },
    reprinted: {
      headline: "Badge reprinted",
      icon: "hugeicons:printer",
      banner: "bg-info/10 border-info/20 text-info-foreground",
      iconWrap: "bg-info/15 text-info-foreground",
    },
    already_checked_in: {
      headline: "Already checked in",
      icon: "hugeicons:alert-circle",
      banner: "bg-warning/10 border-warning/20 text-warning-foreground",
      iconWrap: "bg-warning/15 text-warning-foreground",
    },
    invalid: {
      headline: "Invalid ticket",
      icon: "hugeicons:cancel-circle",
      banner: "bg-destructive/10 border-destructive/20 text-destructive",
      iconWrap: "bg-destructive/15 text-destructive",
    },
    queued: {
      headline: "Queued (offline)",
      icon: "hugeicons:clock-01",
      banner: "bg-muted border-border text-foreground",
      iconWrap: "bg-background text-muted-foreground",
    },
  };

  const metaFor = (result: string): any => RESULT_META[result] || RESULT_META.invalid;

  const resultHeadline = computed(() => metaFor(lastResult.value?.result).headline);
  const resultIcon = computed(() => metaFor(lastResult.value?.result).icon);
  const resultBannerClass = computed(() => metaFor(lastResult.value?.result).banner);
  const resultIconWrapClass = computed(() => metaFor(lastResult.value?.result).iconWrap);
  const resultHeadlineFor = (entry: any) => metaFor(entry.result).headline;

  const REASON_TEXT: Record<string, string> = {
    ticket_not_found: "This QR code is not a valid ticket for any attendee.",
    order_not_confirmed: "The order for this ticket is not confirmed yet.",
    wrong_event: "This ticket belongs to a different event and cross-scan is not allowed.",
  };

  const WARNING_TEXT: Record<string, string> = {
    cross_day: "Outside this ticket's valid day. Use your judgment before admitting.",
    unknown_ticket: "Ticket details could not be verified.",
  };

  const warningText = (w: string): string => WARNING_TEXT[w] || "Please double-check this ticket.";

  const formatTime = (iso: string): string => {
    try {
      return new Intl.DateTimeFormat("en-GB", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
      }).format(new Date(iso));
    } catch {
      return iso;
    }
  };

  const resultSubline = computed(() => {
    const r = lastResult.value;
    if (!r) return "";
    if (r.result === "invalid") {
      return REASON_TEXT[r.reason] || "This ticket could not be redeemed.";
    }
    const parts: string[] = [];
    if (r.attendee?.title) parts.push(r.attendee.title);
    if (r.attendee?.tier) parts.push(r.attendee.tier);
    if (r.result === "already_checked_in" && r.attendee?.checked_in_at) {
      parts.push(`Checked in at ${formatTime(r.attendee.checked_in_at)}`);
    }
    return parts.join(" · ");
  });

  // Readable "day" line for the scanned ticket, mapping valid_day_ids / the
  // chosen day-pass day against the event context.
  const dayLabel = (attendee: any): string => {
    if (!attendee) return "";
    if (attendee.selected_day) {
      return attendee.selected_day.label || `Day ${attendee.selected_day.day_number}`;
    }
    const ids: number[] = attendee.valid_day_ids || [];
    const days: any[] = eventContext.value?.days || [];
    if (!ids.length || (days.length && ids.length >= days.length)) {
      return days.length ? "All days" : "";
    }
    return ids
      .map((id) => days.find((d) => d.id === id))
      .filter(Boolean)
      .map((d) => d.label || `Day ${d.day_number}`)
      .join(", ");
  };

  const checkedInCount = computed(
    () => recentScans.value.filter((s) => s.result === "checked_in").length,
  );

  const feedIcon = (result: string): string => metaFor(result).icon;
  const feedIconColor = (result: string): string => {
    switch (result) {
      case "checked_in":
        return "text-success-foreground";
      case "reprinted":
        return "text-info-foreground";
      case "already_checked_in":
        return "text-warning-foreground";
      case "queued":
        return "text-muted-foreground";
      default:
        return "text-destructive";
    }
  };
  const feedFallbackLabel = (entry: any): string =>
    entry.result === "invalid" ? "Unknown ticket" : "Attendee";

  /* --------------------------- Recent scan feed --------------------------- */
  const pushRecent = (result: any): void => {
    recentScans.value.unshift({
      id: crypto.randomUUID(),
      result: result.result,
      attendee: result.attendee || null,
      time: nowTime(),
    });
    if (recentScans.value.length > 20) recentScans.value.length = 20;
  };

  /* ------------------------------ Check-in -------------------------------- */
  const persistOutbox = (): void => {
    idbSet(outboxKey.value, [...outbox.value]);
  };

  const enqueueOffline = (qrToken: string, idempotencyKey: string, action: string = "check_in"): void => {
    // Stamp the client's own clock at scan time - without this the server
    // would stamp scanned_at/checked_in_at at sync time instead, corrupting
    // attendance timing (and day-validity reports) for a batch that syncs
    // hours after the actual scans (Trigger E). The server bounds/clamps
    // this to +/-24h of its own clock before trusting it.
    outbox.value.push({
      qr_token: qrToken,
      idempotency_key: idempotencyKey,
      action,
      scanned_at: new Date().toISOString(),
    });
    persistOutbox();
  };

  const offlineResult = (qrToken: string, idempotencyKey: string, action: string = "check_in"): any => {
    const att = manifestByToken.value.get(qrToken);
    if (!att) {
      return { result: "invalid", reason: "ticket_not_found" };
    }

    const presented = {
      name: att.name,
      email: att.email,
      title: att.tier || att.kind,
      tier: att.tier,
      qr_token: att.qr_token,
      checked_in_at: att.checked_in_at,
    };

    // Reprint/reissue never collide with check-in state - always queue them
    // with their real action so the server applies the right thing on sync,
    // instead of every offline scan being coerced into a plain check_in
    // (Trigger D).
    if (action !== "check_in") {
      enqueueOffline(qrToken, idempotencyKey, action);
      return { result: "queued", attendee: presented };
    }

    // Consult the cached manifest state instead of blindly queuing a fresh
    // check-in: a ticket already checked in (by this device before the last
    // manifest refresh, or by another gate before this device went offline)
    // surfaces as a repeat instead of silently double-queuing (Trigger B).
    //
    // No wrong-event check here: the manifest endpoint already filters to
    // only the tokens scannable at this gate (own event + allow_cross_scan
    // partners - see ScanService::manifest/scannableEventIds) and doesn't
    // expose which partner event each cross-scan token belongs to, so an
    // attendee found in the manifest with a different event_id is a
    // legitimate cross-scan partner, not a wrong-event ticket - rejecting on
    // that mismatch would incorrectly turn away allowed attendees.
    if (att.checked_in_at) {
      return { result: "already_checked_in", attendee: presented };
    }

    enqueueOffline(qrToken, idempotencyKey, action);
    return { result: "queued", attendee: presented };
  };

  /* ------------------------------- Printer -------------------------------- */
  const shouldAutoPrint = (attendee: any): boolean => {
    if (!printerReady.value || !attendee?.qr_token) return false;
    return attendee.kind !== "add_on" || attendee.print_on_redeem === true;
  };

  const printBadge = async (attendee: any, { interactive = false } = {}): Promise<void> => {
    if (!printerSupported.value || !attendee?.qr_token) return;
    printing.value = true;
    addPrinterLog(
      "info",
      `printBadge mulai: ${attendee.name || "(no name)"}`,
      `interactive=${interactive}, status awal=${printerStatus.value}`,
    );
    try {
      // Silent reconnect first (no picker, no gesture needed). Only fall back to
      // the picker from an interactive user gesture (the buttons / printer chip).
      let ready = await ensureConnected();
      addPrinterLog(ready ? "success" : "warn", `Printer siap untuk print: ${ready}`, `status=${printerStatus.value}`);
      if (!ready && interactive) {
        await connectPrinter(!!printerDevice.value);
        ready = printerConnected.value;
      }
      if (!ready) {
        addPrinterLog("error", "Auto-print DIBATALKAN: printer tidak tersambung", `interactive=${interactive}`);
        if (interactive) {
          toast.error("Printer not connected", {
            description: printerError.value || "Connect a printer using the printer icon first.",
          });
        } else if (printerReady.value && Date.now() - lastPrinterHintAt > 8000) {
          // Auto-print wanted to fire but the remembered printer is asleep / out
          // of range and couldn't be reconnected silently (Web Bluetooth can't
          // wake a fully-asleep BLE device without a user gesture). Surface a
          // throttled, actionable hint instead of failing silently so staff know
          // to tap once - rather than thinking auto-print is broken.
          lastPrinterHintAt = Date.now();
          toast.warning("Printer needs reconnecting", {
            description: "Tap the printer icon above, then Reconnect, to resume auto-print.",
          });
        }
        return;
      }
      // Native TSPL QR: the printer firmware renders the QR, so we only send a
      // tiny (~400 byte) ASCII command instead of a ~20 KB raster bitmap. This
      // makes auto-print near-instant and creates almost no per-print garbage
      // (no canvas / getImageData / qrcode import).
      const { buildTsplNativeQr } = await import(
        "@/composables/usePrinterCommands"
      );
      const bytes = await buildTsplNativeQr({
        name: attendee.name || "Guest",
        // Encode the bare qr_token so the printed badge matches the e-ticket and
        // bulk-PDF QR exactly; the gate and exhibitor scanners read it verbatim.
        qrData: attendee.qr_token,
        widthMm: 50,
        heightMm: 50,
      });
      await writeChunked(bytes);
      addPrinterLog("success", `Auto-print SELESAI: ${attendee.name || "(no name)"}`, `${bytes.length} bytes terkirim`);
      if (interactive) toast.success("Badge sent to printer");
    } catch (err) {
      addPrinterLog("error", `Auto-print GAGAL: ${attendee.name || "(no name)"}`, err instanceof Error ? err.message : String(err));
      toast.error("Print failed", {
        description: err instanceof Error ? err.message : String(err),
      });
    } finally {
      printing.value = false;
    }
  };

  const connectPrinterInteractive = async (): Promise<void> => {
    const ok = await ensureConnected();
    if (ok) {
      toast.success("Printer connected");
      return;
    }
    await connectPrinter(!!printerDevice.value);
    if (printerConnected.value) toast.success("Printer connected");
  };

  // Reconnect to the remembered printer (Tier-1 instant, or name-filtered picker).
  const reconnectPrinter = async (): Promise<void> => {
    await connectPrinter(true);
    if (printerConnected.value) {
      toast.success("Printer connected");
    } else if (printerError.value) {
      toast.error("Reconnect failed", { description: printerError.value });
    }
  };

  // Open a fresh picker to pair a different printer.
  const chooseAnotherPrinter = async (): Promise<void> => {
    await connectPrinter(false);
    if (printerConnected.value) {
      toast.success("Printer connected");
    } else if (printerError.value) {
      toast.error("Couldn't connect", { description: printerError.value });
    }
  };

  // Disconnect and clear the remembered device from this browser.
  const forgetPrinter = async (): Promise<void> => {
    await disconnectPrinter(true);
    toast.success("Printer forgotten");
  };

  const reprintBadge = (attendee: any): void => {
    if (attendee?.qr_token) submitCheckIn(attendee.qr_token, { action: "reprint" });
  };
  const reissueBadge = (attendee: any): void => {
    if (attendee?.qr_token) submitCheckIn(attendee.qr_token, { action: "reissue" });
  };

  /* ----------------------------- Apply result ----------------------------- */
  const applyResult = (result: any): void => {
    lastResult.value = result;
    pushRecent(result);
    const ok = result.result === "checked_in" || result.result === "reprinted";
    const isSuccessSound = ok || result.result === "queued";
    playScanSound(isSuccessSound);
    const alreadyIn = result.result === "already_checked_in";
    buzz(isSuccessSound ? [35] : alreadyIn ? [30, 40, 30] : [50, 40, 50]);
    // Auto-print on a first check-in (or reprint/re-issue): printBadge silently
    // reconnects, so this keeps working on every scan, not just the first.
    const willAutoPrint = ok && shouldAutoPrint(result.attendee);
    addPrinterLog(
      "info",
      `Scan: ${result.attendee?.name || "(unknown)"} → ${result.result}`,
      willAutoPrint
        ? "auto-print akan dijalankan"
        : ok
          ? `tidak auto-print (printerReady=${printerReady.value}, kind=${result.attendee?.kind})`
          : "tidak auto-print (bukan check-in/reprint baru)",
    );
    if (willAutoPrint) {
      printBadge(result.attendee);
    }
  };

  const submitCheckIn = async (
    qrToken: string,
    { action = "check_in" }: { action?: string } = {},
  ): Promise<void> => {
    const idempotencyKey = crypto.randomUUID();

    if (!isOnline.value) {
      applyResult(offlineResult(qrToken, idempotencyKey, action));
      return;
    }

    try {
      const res = await client(`${scanBase.value}/check-in`, {
        method: "POST",
        body: { qr_token: qrToken, idempotency_key: idempotencyKey, action },
      });
      applyResult(res.data);
    } catch (err: any) {
      if (!navigator.onLine || err?.statusCode === 0 || !err?.statusCode) {
        isOnline.value = navigator.onLine;
        applyResult(offlineResult(qrToken, idempotencyKey, action));
      } else {
        toast.error("Check-in failed", {
          description: err?.data?.message || err?.message || "Please try again.",
        });
      }
    }
  };

  // Accept both the bare token and the legacy verify-URL badge form
  // (https://pmone.id/v/<token>) so offline manifest lookups still match. The
  // backend normalizes the same way for online scans.
  const normalizeQrToken = (raw: string): string => {
    let value = String(raw || "").trim();
    const i = value.toLowerCase().indexOf("/v/");
    if (i !== -1) value = value.slice(i + 3);
    return value.replace(/[/?#].*$/, "").trim();
  };

  const onScanValue = (raw: string): void => {
    const token = normalizeQrToken(raw);
    if (!token) return;
    if (isDuplicateScan(token)) return;
    submitCheckIn(token);
  };

  /* ------------------------------- Gun ------------------------------------ */
  // Always-on hardware scanner-gun capture; disabled while the camera overlay
  // is open so the camera owns detection during that window.
  // Camera and gun run side by side; isDuplicateScan() guards double check-ins.
  const gun = useScannerGun(onScanValue);

  /* ------------------------------- Manual --------------------------------- */
  const manualCheckIn = async (attendee: any): Promise<void> => {
    manualBusy.value = attendee.ulid;
    const idempotencyKey = crypto.randomUUID();
    try {
      const res = await client(`${scanBase.value}/manual-check-in`, {
        method: "POST",
        body: { attendee_ulid: attendee.ulid, idempotency_key: idempotencyKey },
      });
      applyResult(res.data);
      const idx = searchResults.value.findIndex((a) => a.ulid === attendee.ulid);
      if (idx !== -1 && res.data.attendee) {
        searchResults.value[idx] = { ...searchResults.value[idx], ...res.data.attendee };
      }
    } catch (err: any) {
      toast.error("Check-in failed", {
        description: err?.data?.message || err?.message || "Please try again.",
      });
    } finally {
      manualBusy.value = null;
    }
  };

  /* ------------------------------- Search --------------------------------- */
  watch(searchQuery, (q) => {
    if (searchTimer) clearTimeout(searchTimer);
    const term = q.trim();
    if (term.length < 2) {
      searchResults.value = [];
      searchPending.value = false;
      return;
    }
    searchPending.value = true;
    searchTimer = setTimeout(() => runSearch(term), 300);
  });

  const runSearch = async (term: string): Promise<void> => {
    const seq = ++searchSeq;
    try {
      const res = await client(`${scanBase.value}/search`, {
        method: "GET",
        params: { q: term },
      });
      if (seq !== searchSeq) return;
      searchResults.value = res.data || [];
    } catch (err: any) {
      if (seq !== searchSeq) return;
      searchResults.value = [];
      toast.error("Search failed", { description: err?.data?.message || err?.message });
    } finally {
      if (seq === searchSeq) searchPending.value = false;
    }
  };

  /* -------------------------------- Sync ---------------------------------- */
  const flushOutbox = async (): Promise<void> => {
    if (!outbox.value.length || syncing.value || !isOnline.value) return;
    syncing.value = true;
    let syncedCount = 0;
    let rejectedCount = 0;
    let errorMessage: string | null = null;

    try {
      // Post the outbox in <=SYNC_CHUNK slices instead of one request - a
      // large offline-outage batch (>200 scans) would otherwise 422 the whole
      // sync against the server's matching cap and retry the same oversized
      // payload forever (Trigger A).
      while (outbox.value.length && isOnline.value) {
        const chunk = outbox.value.slice(0, SYNC_CHUNK);
        let res: any;
        try {
          res = await client(`${scanBase.value}/sync`, {
            method: "POST",
            body: { logs: chunk },
          });
        } catch (err: any) {
          errorMessage = err?.data?.message || err?.message || "Please try again.";
          break;
        }

        const applied: any[] = res.applied || [];
        const appliedKeys = new Set(applied.map((a) => a.idempotency_key));
        outbox.value = outbox.value.filter((o) => !appliedKeys.has(o.idempotency_key));
        persistOutbox();
        syncedCount += applied.length;

        // Per-log sync results were previously discarded - surface anything
        // that isn't a plain check-in (already checked in, invalid, a future
        // result the server may add) into the recent feed instead of only
        // toasting a raw count (Trigger B). Unknown result strings pass
        // through unchanged so a later server addition (e.g. Plan 001's
        // `ticket_cancelled`) works here without a client change.
        for (const a of applied) {
          if (a.result && a.result !== "checked_in") {
            rejectedCount++;
            pushRecent({ result: a.result, attendee: null });
          }
        }

        // A chunk that applied nothing (e.g. every entry was already synced by
        // another tab/device) would otherwise spin forever - bail instead.
        if (!applied.length) break;
      }

      const remaining = outbox.value.length;
      if (syncedCount) {
        toast.success(
          `Synced ${syncedCount} scan${syncedCount === 1 ? "" : "s"}` +
            (remaining ? ` · ${remaining} remaining` : ""),
        );
      }
      if (rejectedCount) {
        toast.warning(`${rejectedCount} scan${rejectedCount === 1 ? "" : "s"} rejected on sync`, {
          description: "Check the recent scans list for details.",
        });
      }
      if (errorMessage) {
        toast.error("Sync failed", {
          description: remaining
            ? `${errorMessage} ${remaining} scan${remaining === 1 ? "" : "s"} still queued.`
            : errorMessage,
        });
      }

      if (syncedCount) await loadManifest();
    } finally {
      syncing.value = false;
    }
  };

  /* ------------------------------- Camera --------------------------------- */
  const onCameraDetect = (codes: any[]): void => {
    for (const c of codes) {
      if (c.rawValue) onScanValue(c.rawValue);
    }
  };
  const onCameraOn = (): void => {
    cameraError.value = "";
  };
  const onCameraError = (err: any): void => {
    cameraError.value =
      err?.name === "NotAllowedError"
        ? "Camera permission denied. Allow camera access to scan."
        : err?.name === "NotFoundError"
          ? "No camera was found on this device."
          : "Could not start the camera on this device.";
  };
  const retryCamera = (): void => {
    cameraError.value = "";
  };

  /* ----------------------------- Data loading ----------------------------- */
  const loadManifest = async (): Promise<void> => {
    if (!isOnline.value) return;
    try {
      const res = await client(`${scanBase.value}/manifest`);
      manifest.value = res.data || [];
      idbSet(manifestKey.value, { data: manifest.value, generated_at: res.generated_at });
    } catch {
      // keep whatever was restored from the offline cache
    }
  };

  const restoreFromStorage = async (): Promise<void> => {
    try {
      const cachedManifest = await idbGet(manifestKey.value);
      if (Array.isArray(cachedManifest?.data)) manifest.value = cachedManifest.data;

      const cachedOutbox = await idbGet(outboxKey.value);
      if (Array.isArray(cachedOutbox)) outbox.value = cachedOutbox;
    } catch {
      // ignore malformed cache
    }
  };

  /* ------------------------------ Lifecycle ------------------------------- */
  const handleOnline = (): void => {
    isOnline.value = true;
    flushOutbox();
  };
  const handleOffline = (): void => {
    isOnline.value = false;
  };

  onMounted(() => {
    if (!canScan.value) return;
    isOnline.value = navigator.onLine;
    cameraSupported.value =
      typeof window !== "undefined" && !!navigator.mediaDevices?.getUserMedia;

    restoreFromStorage().then(loadManifest);
    loadScanSounds();
    loadContext();
    if (printerSupported.value) {
      // Arm auto-print on load: restore the remembered printer, then silently
      // (re)connect to it so the very first scan prints without a manual connect
      // and the status chip shows the live link straight away. Reconnecting to
      // an already-granted device needs no user gesture; a failure is silent
      // (the printer may be off) and every printBadge() still reconnects on its
      // own, so this is best-effort arming, not a hard requirement.
      tryRestoreDevice().then(() => {
        if (printerDevice.value) ensureConnected();
      });
      // Heartbeat keeps the printer link warm for the whole session so auto-print
      // stays instant and the printer never idles long enough to need a manual
      // reconnect.
      startKeepAlive();
    }

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    if (outbox.value.length && isOnline.value) flushOutbox();
  });

  onBeforeUnmount(() => {
    if (searchTimer) clearTimeout(searchTimer);
    stopKeepAlive();
    window.removeEventListener("online", handleOnline);
    window.removeEventListener("offline", handleOffline);
  });

  return {
    // context + perms
    canScan,
    eventContext,
    eventTitle,
    loadContext,
    dayLabel,
    // network
    isOnline,
    // results
    lastResult,
    recentScans,
    resultHeadline,
    resultIcon,
    resultBannerClass,
    resultIconWrapClass,
    resultSubline,
    resultHeadlineFor,
    warningText,
    checkedInCount,
    feedIcon,
    feedIconColor,
    feedFallbackLabel,
    formatTime,
    // search
    searchQuery,
    searchResults,
    searchPending,
    manualBusy,
    manualCheckIn,
    // outbox / sync
    outbox,
    syncing,
    flushOutbox,
    // scan input
    onScanValue,
    gun,
    // camera
    cameraSupported,
    cameraError,
    retryCamera,
    onCameraDetect,
    onCameraOn,
    onCameraError,
    // printer
    printerStatus,
    printerSupported,
    printerConnected,
    printerReady,
    printerError,
    printerName,
    printing,
    printBadge,
    reprintBadge,
    reissueBadge,
    connectPrinterInteractive,
    reconnectPrinter,
    chooseAnotherPrinter,
    forgetPrinter,
    // diagnostics
    printerLogs,
    copyPrinterLogs,
    clearPrinterLogs,
  };
}
