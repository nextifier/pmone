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

export function useBluetoothPrinter() {
  const device = shallowRef<BluetoothDevice | null>(null);
  const writeChar = shallowRef<BluetoothRemoteGATTCharacteristic | null>(null);
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
        requestedDevice.addEventListener("gattserverdisconnected", handleDisconnected);
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
          fallbackDevice.addEventListener("gattserverdisconnected", handleDisconnected);
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

      addLog("info", "Discovering primary services...");
      const services = await server.getPrimaryServices();
      addLog("success", `${services.length} service ditemukan`);

      const candidates: DiscoveredCharacteristic[] = [];
      for (const service of services) {
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

      const preferred =
        candidates.find((c) => c.properties.includes("writeWithoutResponse")) ??
        candidates[0];

      if (!preferred) {
        throw new Error("Tidak ada characteristic terpilih");
      }

      writeChar.value = preferred.characteristic;
      addLog(
        "success",
        `Write characteristic terpilih`,
        `service=${preferred.serviceUuid}\ncharacteristic=${preferred.characteristicUuid}\nproperties=[${preferred.properties.join(", ")}]`
      );

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

  function handleDisconnected(): void {
    addLog("warn", "Device disconnected");
    cleanup();
    status.value = "disconnected";
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
          match.addEventListener("gattserverdisconnected", handleDisconnected);
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
  }

  async function disconnect(forget = false): Promise<void> {
    if (device.value?.gatt?.connected) {
      device.value.gatt.disconnect();
      addLog("info", "Disconnect manual dipanggil");
    }
    cleanup();
    if (forget) {
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
   */
  async function writeChunked(bytes: Uint8Array, chunkSize = 100, delayMs = 20): Promise<void> {
    if (!writeChar.value) {
      addLog("error", "Tidak bisa write - belum ada characteristic terpilih");
      throw new Error("Not connected");
    }

    addLog(
      "data",
      `Mengirim ${bytes.length} bytes (chunk ${chunkSize}b, delay ${delayMs}ms)`,
      `Preview: ${bytesToHexPreview(bytes, 48)}`
    );

    const useWriteWithoutResponse =
      writeChar.value.properties.writeWithoutResponse;

    let sent = 0;
    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
      const chunk = bytes.slice(offset, offset + chunkSize);
      try {
        if (useWriteWithoutResponse) {
          await writeChar.value.writeValueWithoutResponse(chunk);
        } else {
          await writeChar.value.writeValueWithResponse(chunk);
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

    addLog("success", `Berhasil mengirim ${sent} bytes`);
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
    disconnect,
    writeChunked,
    selectCharacteristic,
    tryRestoreDevice,
    clearStoredDevice,
    addLog,
    clearLogs,
    copyLogs,
  };
}
