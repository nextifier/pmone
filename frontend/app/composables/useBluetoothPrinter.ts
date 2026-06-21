import { bytesToHexPreview } from "@/composables/usePrinterCommands";

export type PrinterStatus =
  | "unsupported"
  | "disconnected"
  | "connecting"
  | "connected"
  | "error";

export type LogLevel = "info" | "success" | "warn" | "error" | "data";

export interface LogEntry {
  id: number;
  timestamp: string;
  level: LogLevel;
  message: string;
  detail?: string;
}

interface DiscoveredCharacteristic {
  serviceUuid: string;
  characteristicUuid: string;
  properties: string[];
  characteristic: BluetoothRemoteGATTCharacteristic;
}

/**
 * Service UUIDs umum untuk thermal/label printer BLE.
 * Web Bluetooth requires these to be declared di optionalServices saat requestDevice.
 * Daftar konservatif - mencakup chip-vendor BLE-serial yang umum dipakai vendor printer murah.
 */
const KNOWN_PRINTER_SERVICES = [
  "000018f0-0000-1000-8000-00805f9b34fb", // Generic Thermal Printer
  "0000ff00-0000-1000-8000-00805f9b34fb", // FF00 family
  "0000ff10-0000-1000-8000-00805f9b34fb",
  "0000ff20-0000-1000-8000-00805f9b34fb",
  "0000ff30-0000-1000-8000-00805f9b34fb",
  "0000ffe0-0000-1000-8000-00805f9b34fb", // HM-10 / TI BLE serial
  "0000ffe5-0000-1000-8000-00805f9b34fb",
  "0000fff0-0000-1000-8000-00805f9b34fb",
  "0000fee7-0000-1000-8000-00805f9b34fb", // Tencent OTA (sering dipakai chip nordic clone)
  "0000fee8-0000-1000-8000-00805f9b34fb",
  "0000fee9-0000-1000-8000-00805f9b34fb",
  "49535343-fe7d-4ae5-8fa9-9fafd205e455", // Microchip ISSC transparent uart
  "6e400001-b5a3-f393-e0a9-e50e24dcca9e", // Nordic UART Service
  "0000180a-0000-1000-8000-00805f9b34fb", // Device Information (read-only, infor diagnostic)
  "00001800-0000-1000-8000-00805f9b34fb", // Generic Access
];

/**
 * Service standar BLE yang TIDAK boleh dipakai sebagai write target.
 * Generic Access (1800) — characteristic "Device Name" (2a00) bersifat writable
 * untuk rename device, bukan untuk data printer. Chrome desktop mem-blocklist
 * service ini, tapi Chrome Android meng-expose-nya sehingga auto-pick salah pilih.
 * Device Information (180a) hanya read-only metadata.
 */
const EXCLUDED_WRITE_SERVICES = new Set([
  "00001800-0000-1000-8000-00805f9b34fb",
  "0000180a-0000-1000-8000-00805f9b34fb",
]);

/**
 * Service "UART-like" yang umum dipakai BLE printer untuk command stream.
 * Characteristic dari service ini di-prioritize saat auto-pick.
 */
const PREFERRED_PRINTER_SERVICES = new Set([
  "49535343-fe7d-4ae5-8fa9-9fafd205e455", // Microchip ISSC (CT221B)
  "6e400001-b5a3-f393-e0a9-e50e24dcca9e", // Nordic UART
  "000018f0-0000-1000-8000-00805f9b34fb", // Generic Thermal Printer
  "0000ff00-0000-1000-8000-00805f9b34fb",
  "0000ff10-0000-1000-8000-00805f9b34fb",
  "0000ff20-0000-1000-8000-00805f9b34fb",
  "0000ff30-0000-1000-8000-00805f9b34fb",
  "0000ffe0-0000-1000-8000-00805f9b34fb", // HM-10 BLE serial
  "0000ffe5-0000-1000-8000-00805f9b34fb",
  "0000fff0-0000-1000-8000-00805f9b34fb",
]);

function scoreCandidate(serviceUuid: string, properties: string[]): number {
  let score = 0;
  if (PREFERRED_PRINTER_SERVICES.has(serviceUuid.toLowerCase())) score += 100;
  if (properties.includes("writeWithoutResponse")) score += 10;
  if (properties.includes("write")) score += 1;
  return score;
}

const STORAGE_KEY = "pmone:bluetooth-printer-id";
const STORAGE_NAME_KEY = "pmone:bluetooth-printer-name";

const TIME_FORMATTER = new Intl.DateTimeFormat("en-GB", {
  timeZone: "Asia/Jakarta",
  hour: "2-digit",
  minute: "2-digit",
  second: "2-digit",
  hour12: false,
  fractionalSecondDigits: 3,
});

let logIdCounter = 0;

/**
 * Cap the diagnostic log so a long check-in session (hundreds/thousands of
 * scans, each pushing several entries via discoverWriteChar + writeChunked)
 * cannot grow `logs` unbounded and leak memory. Only the most recent entries
 * are kept.
 */
const MAX_LOGS = 300;

export function useBluetoothPrinter() {
  const device = shallowRef<BluetoothDevice | null>(null);
  const writeChar = shallowRef<BluetoothRemoteGATTCharacteristic | null>(null);
  // A readable characteristic used purely as a keep-alive heartbeat target (see
  // startKeepAlive). Reads can never make the printer print, so this is safe.
  const keepAliveChar = shallowRef<BluetoothRemoteGATTCharacteristic | null>(null);
  const status = ref<PrinterStatus>("disconnected");
  const errorMessage = ref<string | null>(null);
  const savedDeviceName = ref<string | null>(null);
  const discoveredChars = ref<
    Array<{ serviceUuid: string; characteristicUuid: string; properties: string[] }>
  >([]);
  const logs = ref<LogEntry[]>([]);

  const isSupported = computed(() => {
    if (!import.meta.client) return false;
    return typeof navigator !== "undefined" && "bluetooth" in navigator;
  });

  if (import.meta.client && !isSupported.value) {
    status.value = "unsupported";
  }

  function addLog(level: LogLevel, message: string, detail?: string): void {
    logs.value.push({
      id: ++logIdCounter,
      timestamp: TIME_FORMATTER.format(new Date()),
      level,
      message,
      detail,
    });
    if (logs.value.length > MAX_LOGS) {
      logs.value.splice(0, logs.value.length - MAX_LOGS);
    }
  }

  function clearLogs(): void {
    logs.value = [];
  }

  async function copyLogs(): Promise<void> {
    const text = logs.value
      .map((log) => {
        const base = `[${log.timestamp}] [${log.level.toUpperCase()}] ${log.message}`;
        return log.detail ? `${base}\n  ${log.detail}` : base;
      })
      .join("\n");
    try {
      await navigator.clipboard.writeText(text);
      addLog("success", "Log disalin ke clipboard");
    } catch (err) {
      addLog("error", "Gagal menyalin log", String(err));
    }
  }

  async function connect(useStoredFilter = false): Promise<void> {
    if (!isSupported.value) {
      addLog(
        "error",
        "Web Bluetooth tidak didukung browser ini",
        "Gunakan Chrome / Edge / Opera. Safari belum support."
      );
      status.value = "unsupported";
      return;
    }

    try {
      status.value = "connecting";
      errorMessage.value = null;

      let requestedDevice: BluetoothDevice;
      let isNewlyPicked = false;
      let usedTier1Direct = false;

      if (useStoredFilter && device.value) {
        // Tier 1: device sudah loaded via getDevices() di tryRestoreDevice.
        // Langsung pakai, no picker.
        requestedDevice = device.value;
        usedTier1Direct = true;
        addLog(
          "success",
          `Reconnect instan (tanpa picker): ${requestedDevice.name ?? "(unnamed)"}`,
          "Device sudah ter-load via getDevices()"
        );
      } else if (useStoredFilter && savedDeviceName.value) {
        // Tier 2 (fallback): filter picker by name.
        addLog(
          "info",
          `Membuka picker dengan filter nama: ${savedDeviceName.value}`,
          "Picker akan tampilkan hanya device dengan nama itu"
        );
        requestedDevice = await navigator.bluetooth.requestDevice({
          filters: [{ name: savedDeviceName.value }],
          optionalServices: KNOWN_PRINTER_SERVICES,
        });
        isNewlyPicked = true;
      } else {
        addLog("info", "Membuka picker bluetooth device...");
        requestedDevice = await navigator.bluetooth.requestDevice({
          acceptAllDevices: true,
          optionalServices: KNOWN_PRINTER_SERVICES,
        });
        isNewlyPicked = true;
      }

      if (isNewlyPicked) {
        addLog(
          "success",
          `Device dipilih: ${requestedDevice.name ?? "(no name)"}`,
          `id=${requestedDevice.id}`
        );
        attachDeviceListener(requestedDevice);
      }

      device.value = requestedDevice;
      persistDevice(requestedDevice);

      addLog("info", "Connecting ke GATT server...");
      let server: BluetoothRemoteGATTServer | undefined;
      try {
        server = await requestedDevice.gatt?.connect();
      } catch (gattErr) {
        // Auto-fallback: kalau Tier 1 (cached device dari getDevices) gagal
        // connect, kemungkinan device cache stale / tidak advertising. Trigger
        // filter picker dalam same user gesture untuk force BLE scan ulang.
        if (usedTier1Direct && savedDeviceName.value) {
          addLog(
            "warn",
            "Reconnect cepat gagal, auto-fallback ke filter picker",
            gattErr instanceof Error ? gattErr.message : String(gattErr)
          );
          addLog(
            "info",
            `Membuka picker untuk re-scan: ${savedDeviceName.value}`,
            "Picker trigger BLE scan baru → device akan terdeteksi kalau menyala"
          );
          const fallbackDevice = await navigator.bluetooth.requestDevice({
            filters: [{ name: savedDeviceName.value }],
            optionalServices: KNOWN_PRINTER_SERVICES,
          });
          addLog(
            "success",
            `Device dipilih ulang: ${fallbackDevice.name ?? "(no name)"}`,
            `id=${fallbackDevice.id}`
          );
          attachDeviceListener(fallbackDevice);
          requestedDevice = fallbackDevice;
          device.value = fallbackDevice;
          persistDevice(fallbackDevice);
          server = await requestedDevice.gatt?.connect();
        } else {
          throw gattErr;
        }
      }
      if (!server) {
        throw new Error("GATT server tidak tersedia");
      }
      addLog("success", "GATT server connected");

      await discoverWriteChar(server);

      status.value = "connected";
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      const isOutOfRange = /no longer in range|GATT operation failed|not found|GATT Server is disconnected/i.test(msg);
      errorMessage.value = isOutOfRange
        ? "Printer tidak terdeteksi. Pastikan printer menyala dan dekat dengan komputer, lalu klik Reconnect lagi."
        : msg;
      addLog("error", "Connect gagal", msg);
      if (isOutOfRange) {
        addLog(
          "info",
          "Tip: nyalakan printer + tekan tombol pairing → klik Reconnect ulang",
          "Kalau tetap gagal beberapa kali, coba 'Pilih Device Lain' untuk picker fresh"
        );
      }
      status.value = device.value?.gatt?.connected ? "connected" : "error";
      cleanup();
    }
  }

  /**
   * Discover printer services on an already-connected GATT server, rank the
   * writable characteristics, and set `writeChar` to the best candidate.
   * Shared by the picker `connect()` path and the silent `ensureConnected()`
   * reconnect path so both end up with a valid write target.
   */
  async function discoverWriteChar(server: BluetoothRemoteGATTServer): Promise<void> {
    addLog("info", "Discovering primary services...");
    const services = await server.getPrimaryServices();
    addLog("success", `${services.length} service ditemukan`);

    const candidates: DiscoveredCharacteristic[] = [];
    for (const service of services) {
      if (EXCLUDED_WRITE_SERVICES.has(service.uuid.toLowerCase())) {
        addLog(
          "info",
          `Skip service ${service.uuid}`,
          "Bukan UART printer (Generic Access / Device Info), di-exclude dari write target"
        );
        continue;
      }
      try {
        const chars = await service.getCharacteristics();
        const charDescs = chars.map((c) => {
          const props: string[] = [];
          if (c.properties.write) props.push("write");
          if (c.properties.writeWithoutResponse) props.push("writeWithoutResponse");
          if (c.properties.read) props.push("read");
          if (c.properties.notify) props.push("notify");
          if (c.properties.indicate) props.push("indicate");
          return { uuid: c.uuid, properties: props, characteristic: c };
        });

        addLog(
          "info",
          `Service ${service.uuid}`,
          charDescs.map((d) => `  └ ${d.uuid} [${d.properties.join(", ") || "no props"}]`).join("\n")
        );

        for (const cd of charDescs) {
          if (cd.properties.includes("write") || cd.properties.includes("writeWithoutResponse")) {
            candidates.push({
              serviceUuid: service.uuid,
              characteristicUuid: cd.uuid,
              properties: cd.properties,
              characteristic: cd.characteristic,
            });
          }
        }
      } catch (err) {
        addLog("warn", `Gagal ambil characteristics dari ${service.uuid}`, String(err));
      }
    }

    discoveredChars.value = candidates.map((c) => ({
      serviceUuid: c.serviceUuid,
      characteristicUuid: c.characteristicUuid,
      properties: c.properties,
    }));

    if (candidates.length === 0) {
      throw new Error("Tidak ada characteristic writable. Printer mungkin tidak expose service yang dikenali.");
    }

    const ranked = [...candidates].sort(
      (a, b) =>
        scoreCandidate(b.serviceUuid, b.properties) -
        scoreCandidate(a.serviceUuid, a.properties)
    );
    const preferred = ranked[0];

    if (!preferred) {
      throw new Error("Tidak ada characteristic terpilih");
    }

    writeChar.value = preferred.characteristic;
    const preferredScore = scoreCandidate(preferred.serviceUuid, preferred.properties);
    const isPreferredService = PREFERRED_PRINTER_SERVICES.has(
      preferred.serviceUuid.toLowerCase()
    );
    addLog(
      "success",
      `Write characteristic terpilih`,
      `service=${preferred.serviceUuid}\ncharacteristic=${preferred.characteristicUuid}\nproperties=[${preferred.properties.join(", ")}]\nscore=${preferredScore} (preferred service: ${isPreferredService})`
    );

    // Pick any readable characteristic (e.g. Device Information) as the
    // keep-alive heartbeat target. A periodic read is side-effect-free - it
    // cannot trigger a print - yet the BLE traffic keeps the link, and on most
    // cheap printers the firmware, from idling into sleep.
    keepAliveChar.value = null;
    for (const service of services) {
      try {
        const chars = await service.getCharacteristics();
        const readable = chars.find((c) => c.properties.read);
        if (readable) {
          keepAliveChar.value = readable;
          addLog("info", "Keep-alive characteristic dipilih", `${service.uuid} / ${readable.uuid}`);
          break;
        }
      } catch {
        // ignore services whose characteristics can't be enumerated
      }
    }
  }

  /**
   * Silent reconnect for auto-print. Many cheap BLE label printers drop the
   * GATT link after each job / idle, which would otherwise silently disable
   * auto-print on the 2nd+ scan. Reconnecting to an ALREADY-granted device via
   * `gatt.connect()` does NOT require a user gesture (only `requestDevice` /
   * the picker does), so this can run on every scan with no UI prompt.
   *
   * Returns false when there is no known device yet (the caller must open the
   * picker via `connect()` from a user gesture first).
   */
  // Coalesce concurrent auto-print reconnects. Rapid back-to-back scans right
  // after the link drops would otherwise each call `gatt.connect()` in parallel
  // and throw "GATT operation already in progress"; instead they all await the
  // same in-flight reconnect.
  let reconnectInFlight: Promise<boolean> | null = null;

  async function ensureConnected({ attempts = 3, quiet = false } = {}): Promise<boolean> {
    if (status.value === "connected" && writeChar.value && device.value?.gatt?.connected) {
      return true;
    }
    if (!device.value?.gatt) {
      return false;
    }
    if (reconnectInFlight) {
      return reconnectInFlight;
    }
    const gatt = device.value.gatt;
    reconnectInFlight = (async (): Promise<boolean> => {
      status.value = "connecting";
      // Cheap BLE label printers routinely drop the GATT link when idle and can
      // need a moment (or a second attempt) to answer a reconnect. Retrying a
      // few times with a short backoff - all silent, no picker - lets auto-print
      // survive the gaps between scans instead of giving up on the first miss.
      // `quiet` suppresses the per-attempt warnings for the keep-alive heartbeat
      // (which has its own 10s retry cadence) so an idle, asleep printer doesn't
      // flood the diagnostic log.
      for (let attempt = 1; attempt <= attempts; attempt++) {
        try {
          const server = await gatt.connect();
          await discoverWriteChar(server);
          // Let the printer settle after (re)connect + service discovery before
          // the caller's first write - cheap BLE printers drop a write that
          // arrives too soon after the link comes up, which would otherwise make
          // the first auto-print after a reconnect silently fail.
          await new Promise((r) => setTimeout(r, 250));
          status.value = "connected";
          addLog(
            "success",
            `Auto-reconnect berhasil (tanpa picker)${attempt > 1 ? ` pada percobaan ${attempt}` : ""}`
          );
          return true;
        } catch (err) {
          if (!quiet) {
            addLog(
              "warn",
              `Auto-reconnect percobaan ${attempt}/${attempts} gagal`,
              err instanceof Error ? err.message : String(err)
            );
          }
          if (attempt < attempts) {
            await new Promise((r) => setTimeout(r, 400));
          }
        }
      }
      cleanup();
      status.value = "disconnected";
      return false;
    })().finally(() => {
      reconnectInFlight = null;
    });
    return reconnectInFlight;
  }

  function handleDisconnected(): void {
    addLog("warn", "Device disconnected");
    cleanup();
    status.value = "disconnected";
  }

  // Track which device currently has our disconnect listener so switching
  // printers ("Choose another device") doesn't leave stale listeners on old
  // device objects across many re-pairs.
  let listenedDevice: BluetoothDevice | null = null;

  function attachDeviceListener(d: BluetoothDevice): void {
    if (listenedDevice && listenedDevice !== d) {
      listenedDevice.removeEventListener("gattserverdisconnected", handleDisconnected);
    }
    d.addEventListener("gattserverdisconnected", handleDisconnected);
    listenedDevice = d;
  }

  function detachDeviceListener(): void {
    if (listenedDevice) {
      listenedDevice.removeEventListener("gattserverdisconnected", handleDisconnected);
      listenedDevice = null;
    }
  }

  function persistDevice(d: BluetoothDevice): void {
    const name = d.name ?? "";
    try {
      localStorage.setItem(STORAGE_KEY, d.id);
      if (name) {
        localStorage.setItem(STORAGE_NAME_KEY, name);
        savedDeviceName.value = name;
      }
    } catch (err) {
      addLog("warn", "localStorage write gagal", String(err));
    }
  }

  /**
   * Coba restore device dari localStorage. Strategi 2-tier:
   * 1. Kalau browser support `navigator.bluetooth.getDevices()` (Chrome flag
   *    `enable-experimental-web-platform-features` enabled, atau Chrome 122+
   *    stable), panggil getDevices() → cari device dengan saved id → load
   *    BluetoothDevice instance ke ref. Saat user klik Reconnect, langsung
   *    GATT connect tanpa picker.
   * 2. Fallback: hanya simpan nama device. Tombol Reconnect akan buka picker
   *    dengan filter nama (lebih cepat dipilih).
   */
  async function tryRestoreDevice(): Promise<void> {
    if (!isSupported.value) return;

    let savedName: string | null = null;
    let savedId: string | null = null;
    try {
      savedName = localStorage.getItem(STORAGE_NAME_KEY);
      savedId = localStorage.getItem(STORAGE_KEY);
    } catch {
      return;
    }

    if (savedName) {
      savedDeviceName.value = savedName;
    }

    // Tier 1: getDevices() untuk dapat BluetoothDevice instance langsung
    if (savedId && typeof navigator.bluetooth.getDevices === "function") {
      try {
        const grantedDevices = await navigator.bluetooth.getDevices();
        const match = grantedDevices.find((d) => d.id === savedId);
        if (match) {
          attachDeviceListener(match);
          device.value = match;
          addLog(
            "success",
            `Device dipulihkan via getDevices(): ${match.name ?? "(unnamed)"}`,
            'Klik "Reconnect" untuk hubungkan instan tanpa picker'
          );
          return;
        }
        addLog(
          "info",
          "Device tersimpan tidak ada di getDevices()",
          "Permission mungkin di-revoke. Fallback ke filter-by-name picker."
        );
      } catch (err) {
        addLog("warn", "getDevices() error, fallback ke filter picker", String(err));
      }
    }

    // Tier 2 (fallback): hanya tampilkan nama device
    if (savedName) {
      addLog(
        "info",
        `Device sebelumnya: ${savedName}`,
        'Klik "Reconnect" untuk pilih cepat lewat filter nama (perlu konfirmasi picker)'
      );
    }
  }

  function clearStoredDevice(): void {
    try {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem(STORAGE_NAME_KEY);
    } catch {
      // ignore
    }
    savedDeviceName.value = null;
  }

  function cleanup(): void {
    writeChar.value = null;
    keepAliveChar.value = null;
  }

  async function disconnect(forget = false): Promise<void> {
    if (device.value?.gatt?.connected) {
      device.value.gatt.disconnect();
      addLog("info", "Disconnect manual dipanggil");
    }
    cleanup();
    if (forget) {
      detachDeviceListener();
      device.value = null;
      clearStoredDevice();
      addLog("info", "Device dilupakan (storage dibersihkan)");
    }
    discoveredChars.value = [];
    status.value = "disconnected";
  }

  /**
   * Pilih characteristic spesifik (manual override) berdasarkan UUID-nya.
   * Berguna kalau auto-pick salah pilih.
   */
  async function selectCharacteristic(serviceUuid: string, characteristicUuid: string): Promise<void> {
    if (!device.value?.gatt?.connected) {
      addLog("error", "Tidak bisa pilih characteristic - device tidak connected");
      return;
    }
    try {
      const service = await device.value.gatt.getPrimaryService(serviceUuid);
      const char = await service.getCharacteristic(characteristicUuid);
      writeChar.value = char;
      addLog("success", "Characteristic di-override manual", `${serviceUuid} / ${characteristicUuid}`);
    } catch (err) {
      addLog("error", "Gagal override characteristic", String(err));
    }
  }

  /**
   * Kirim bytes ke printer dengan chunking. BLE MTU default ~20 byte; 100-180 sering juga aman.
   * Pakai writeWithoutResponse jika tersedia (lebih cepat, tidak ada ack).
   * Android: chunk dipersempit ke 20 byte + delay 40ms karena Chrome Android
   * tidak auto-negotiate MTU besar dan stack-nya kurang reliable untuk chunk besar.
   */
  // Serialize ALL GATT operations (print writes + keep-alive reads) through one
  // chain: two overlapping ops on the same device make Web Bluetooth throw "GATT
  // operation already in progress". Each call waits for the previous one.
  let gattLock: Promise<unknown> = Promise.resolve();
  function runExclusive<T>(op: () => Promise<T>): Promise<T> {
    const p = gattLock.then(op, op);
    gattLock = p.catch(() => {});
    return p;
  }

  async function writeChunked(bytes: Uint8Array, chunkSize = 100, delayMs = 20): Promise<void> {
    const run = async (): Promise<void> => {
      if (!writeChar.value) {
        addLog("error", "Tidak bisa write - belum ada characteristic terpilih");
        throw new Error("Not connected");
      }

      const isAndroid =
        import.meta.client && /Android/i.test(navigator.userAgent);
      if (isAndroid) {
        chunkSize = Math.min(chunkSize, 20);
        delayMs = Math.max(delayMs, 40);
      }

      addLog(
        "data",
        `Mengirim ${bytes.length} bytes (chunk ${chunkSize}b, delay ${delayMs}ms)`,
        `Preview: ${bytesToHexPreview(bytes, 48)}${isAndroid ? "\nPlatform: Android (chunk size & delay disesuaikan)" : ""}`
      );

      // Prefer ACKed writes (writeValueWithResponse): the promise only resolves
      // once the printer confirms receipt, giving real flow control so chunks
      // are never silently dropped. Unacknowledged writes (writeWithoutResponse)
      // are faster but the browser hands them to the OS and resolves immediately
      // - if the printer's buffer isn't ready (classically, the first write
      // right after a reconnect) the data just vanishes and the label never
      // prints even though the code "succeeded". That is the usual cause of
      // intermittent auto-print misses, so we only fall back to no-ack when the
      // characteristic genuinely can't do ACKed writes.
      const useAckedWrite = writeChar.value.properties.write;

      let sent = 0;
      for (let offset = 0; offset < bytes.length; offset += chunkSize) {
        const chunk = bytes.slice(offset, offset + chunkSize);
        try {
          if (useAckedWrite) {
            await writeChar.value.writeValueWithResponse(chunk);
          } else {
            await writeChar.value.writeValueWithoutResponse(chunk);
          }
          sent += chunk.length;
        } catch (err) {
          addLog(
            "error",
            `Write gagal pada offset ${offset}`,
            err instanceof Error ? err.message : String(err)
          );
          throw err;
        }
        if (delayMs > 0 && offset + chunkSize < bytes.length) {
          await new Promise((r) => setTimeout(r, delayMs));
        }
      }

      addLog("success", `Berhasil mengirim ${sent} bytes (${useAckedWrite ? "ACKed" : "no-ack"})`);
    };

    return runExclusive(run);
  }

  /* ----------------------------- Keep-alive ------------------------------- */
  // Cheap BLE label printers drop the link (and sometimes deep-sleep their
  // radio) after a short idle, which is what forces a manual reconnect for
  // auto-print. A lightweight heartbeat keeps the link warm: while connected it
  // does a tiny serialized read (no print possible); if the link has dropped it
  // silently reconnects - so the printer is ready before the next scan and, in
  // practice, never idles long enough to fully sleep.
  let keepAliveTimer: ReturnType<typeof setInterval> | null = null;

  async function keepAliveTick(): Promise<void> {
    if (device.value?.gatt?.connected && keepAliveChar.value) {
      try {
        await runExclusive(() => keepAliveChar.value!.readValue());
        return;
      } catch {
        // The read failing means the link just dropped; fall through to reconnect.
      }
    }
    if (device.value?.gatt && !device.value.gatt.connected) {
      // Single quiet attempt: the 10s heartbeat cadence is itself the retry, so
      // an idle/asleep printer doesn't spam the log with failed reconnects.
      await ensureConnected({ attempts: 1, quiet: true });
    }
  }

  function startKeepAlive(intervalMs = 10000): void {
    if (keepAliveTimer || !isSupported.value) {
      return;
    }
    keepAliveTimer = setInterval(() => {
      void keepAliveTick();
    }, intervalMs);
  }

  function stopKeepAlive(): void {
    if (keepAliveTimer) {
      clearInterval(keepAliveTimer);
      keepAliveTimer = null;
    }
  }

  return {
    device,
    status,
    errorMessage,
    savedDeviceName,
    discoveredChars,
    logs,
    isSupported,
    connect,
    ensureConnected,
    disconnect,
    writeChunked,
    selectCharacteristic,
    tryRestoreDevice,
    clearStoredDevice,
    startKeepAlive,
    stopKeepAlive,
    addLog,
    clearLogs,
    copyLogs,
  };
}
