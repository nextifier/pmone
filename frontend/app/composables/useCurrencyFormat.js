const formatter = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  notation: "compact",
  minimumFractionDigits: 0,
  maximumFractionDigits: 1, // Izinkan satu angka desimal seperti "Rp95,5rb"
});

/**
 * Composable untuk memformat angka menjadi format mata uang Rupiah yang ringkas.
 * Contoh: 100000 -> "Rp100rb"
 * @returns {{format: (value: number) => string}}
 */
export function useCurrencyFormat() {
  /**
   * Fungsi untuk memformat nilai angka yang diberikan.
   * @param {number | undefined | null} value - Nilai angka yang akan diformat.
   * @returns {string} - String mata uang yang sudah diformat.
   */
  const format = (value) => {
    // Jika nilai tidak valid (null, undefined), kembalikan string kosong.
    if (value === null || typeof value === "undefined") {
      return "";
    }

    if (isNaN(value)) {
      return value;
    }

    // Format nilai dan hapus spasi antara "Rp" dan angka.
    return formatter.format(value).replace(/\s/g, "");
  };

  // Kembalikan fungsi format agar bisa digunakan di komponen.
  return { format };
}
