/**
 * Composable untuk memproses string HTML dari CMS.
 * Ia akan menemukan semua tag heading (h2-h6), memastikan mereka memiliki
 * ID yang unik, dan mengembalikannya sebagai computed property.
 * @param {import('vue').Ref<string>} htmlContent - Ref yang berisi string HTML mentah.
 */
export const useProcessedContent = (htmlContent) => {
  const createUniqueId = (text, index) => {
    const slug = text
      .toLowerCase()
      .replace(/\s+/g, "-")
      .replace(/[^\w-]+/g, "");
    return `${slug}-${index}`;
  };

  const processedHtml = computed(() => {
    const rawHtml = htmlContent.value;
    if (!rawHtml || typeof document === "undefined") {
      // Jika tidak ada HTML atau berada di lingkungan non-browser (saat SSR awal),
      // kita gunakan DOM parser sederhana dari sisi server jika perlu, atau regex.
      // Namun, untuk Nuxt 3, kita bisa mengandalkan DOMParser yang tersedia di Nitro.
      // Untuk kesederhanaan, kita akan menggunakan regex yang aman.

      let index = 0;
      // Regex untuk menemukan semua tag h2-h6
      return rawHtml.replace(
        /<h([2-6])(.*?)>(.*?)<\/h\1>/gi,
        (match, level, attrs, innerText) => {
          const text = innerText.replace(/<[^>]+>/g, "").trim(); // Ambil teks bersih
          const newId = createUniqueId(text, index++);

          // Hapus atribut ID yang sudah ada jika ada
          const cleanAttrs = attrs.replace(/\s*id="[^"]*"/i, "");

          // Kembalikan tag heading dengan ID baru yang unik
          return `<h${level} id="${newId}"${cleanAttrs}>${innerText}</h${level}>`;
        },
      );
    }

    // Pendekatan sisi klien (setelah hydration) tetap lebih andal dengan DOMParser
    const parser = new DOMParser();
    const doc = parser.parseFromString(rawHtml, "text/html");
    const headingNodes = doc.querySelectorAll("h2, h3, h4, h5, h6");

    headingNodes.forEach((node, index) => {
      const id = createUniqueId(node.innerText, index);
      node.id = id;
    });

    return doc.body.innerHTML;
  });

  return { processedHtml };
};
