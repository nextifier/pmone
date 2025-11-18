import { ref } from "vue";
import { toast } from "vue-sonner";

export const usePageExport = () => {
  const isExporting = ref(false);

  /**
   * Prepare element for export by rendering it in an isolated iframe
   * @param {HTMLElement} element - The element to export
   * @returns {Promise<{canvas: HTMLCanvasElement, cleanup: Function}>}
   */
  const prepareElementForExport = async (element) => {
    if (!element) {
      throw new Error("Element not found");
    }

    // Create hidden iframe for isolated rendering
    const iframe = document.createElement("iframe");
    iframe.style.position = "fixed";
    iframe.style.top = "-9999px";
    iframe.style.left = "-9999px";
    iframe.style.width = "1024px";
    iframe.style.height = "100vh";
    iframe.style.border = "none";
    document.body.appendChild(iframe);

    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

    // Copy all stylesheets to iframe
    const stylesheets = Array.from(document.styleSheets);
    stylesheets.forEach((stylesheet) => {
      try {
        if (stylesheet.href) {
          const link = iframeDoc.createElement("link");
          link.rel = "stylesheet";
          link.href = stylesheet.href;
          iframeDoc.head.appendChild(link);
        } else if (stylesheet.cssRules) {
          const style = iframeDoc.createElement("style");
          Array.from(stylesheet.cssRules).forEach((rule) => {
            style.appendChild(iframeDoc.createTextNode(rule.cssText));
          });
          iframeDoc.head.appendChild(style);
        }
      } catch (e) {
        // Skip cross-origin stylesheets
      }
    });

    // Force light mode in iframe
    iframeDoc.documentElement.style.colorScheme = "light";

    // Clone and append element
    const clonedElement = element.cloneNode(true);
    iframeDoc.body.appendChild(clonedElement);
    iframeDoc.body.style.margin = "0";
    iframeDoc.body.style.padding = "0";
    iframeDoc.body.style.backgroundColor = "#ffffff";

    // Wait for render
    await new Promise((resolve) => setTimeout(resolve, 300));

    // Convert external images to data URLs via proxy
    const images = clonedElement.querySelectorAll("img");
    const imagePromises = Array.from(images).map(async (img) => {
      const src = img.src;

      // Skip if already a data URL or same origin
      if (src.startsWith("data:") || src.startsWith(window.location.origin)) {
        return;
      }

      try {
        const proxyUrl = `/image-proxy?url=${encodeURIComponent(src)}`;
        const response = await fetch(proxyUrl);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const blob = await response.blob();
        const reader = new FileReader();
        const dataUrl = await new Promise((resolve, reject) => {
          reader.onloadend = () => resolve(reader.result);
          reader.onerror = reject;
          reader.readAsDataURL(blob);
        });

        img.src = dataUrl;
      } catch (error) {
        console.error("[Export] Failed to proxy image:", src, error);
      }
    });

    await Promise.all(imagePromises);
    await new Promise((resolve) => setTimeout(resolve, 100));

    // Get actual content height
    const contentHeight = clonedElement.offsetHeight || clonedElement.scrollHeight;

    // Render to canvas
    const html2canvas = (await import("html2canvas-pro")).default;
    const canvas = await html2canvas(clonedElement, {
      scale: 2.5,
      useCORS: false,
      allowTaint: true,
      logging: false,
      backgroundColor: "#ffffff",
      windowWidth: 1024,
      windowHeight: contentHeight,
    });

    // Cleanup function
    const cleanup = () => {
      document.body.removeChild(iframe);
    };

    return { canvas, cleanup };
  };

  /**
   * Export element as PDF
   * @param {string} selector - CSS selector for element to export
   * @param {Object} options - Export options
   */
  const exportToPDF = async (selector, options = {}) => {
    if (isExporting.value) return;

    isExporting.value = true;

    const performExport = async () => {
      await new Promise((resolve) => setTimeout(resolve, 300));

      const element = document.querySelector(selector);
      const { canvas, cleanup } = await prepareElementForExport(element);

      try {
        const { jsPDF } = await import("jspdf");

        // PDF dimensions with margins
        const pageWidth = 210;
        const pageHeight = 297;
        const margin = 10;
        const contentWidth = pageWidth - 2 * margin;
        const contentHeight = pageHeight - 2 * margin;

        // Calculate image dimensions
        const imgWidth = contentWidth;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        // Create PDF
        const pdf = new jsPDF("p", "mm", "a4");
        const imgData = canvas.toDataURL("image/jpeg", 0.98);

        await new Promise((resolve) => setTimeout(resolve, 100));

        // Add first page
        pdf.addImage(imgData, "JPEG", margin, margin, imgWidth, imgHeight);

        // Add additional pages if needed
        let heightLeft = imgHeight - contentHeight;
        let position = 0;

        while (heightLeft > 0) {
          await new Promise((resolve) => setTimeout(resolve, 50));
          position = heightLeft - imgHeight;
          pdf.addPage();
          pdf.addImage(
            imgData,
            "JPEG",
            margin,
            position + margin,
            imgWidth,
            imgHeight
          );
          heightLeft -= contentHeight;
        }

        await new Promise((resolve) => setTimeout(resolve, 100));

        // Save PDF
        const filename = options.filename || `export_${Date.now()}.pdf`;
        pdf.save(filename);

        return { filename };
      } finally {
        cleanup();
        await new Promise((resolve) => setTimeout(resolve, 200));
      }
    };

    toast.promise(performExport, {
      loading: "Generating PDF...",
      success: "Exported to PDF successfully",
      error: (err) => err?.message || "Failed to export to PDF",
      finally: () => {
        isExporting.value = false;
      },
    });
  };

  /**
   * Export element as JPG
   * @param {string} selector - CSS selector for element to export
   * @param {Object} options - Export options
   */
  const exportToJPG = async (selector, options = {}) => {
    if (isExporting.value) return;

    isExporting.value = true;

    const performExport = async () => {
      await new Promise((resolve) => setTimeout(resolve, 300));

      const element = document.querySelector(selector);
      const { canvas, cleanup } = await prepareElementForExport(element);

      try {
        // Add margin and crop whitespace
        const margin = 80; // 80px margin on all sides (more margin as requested)

        // Get image data from original canvas to find actual content
        const originalCtx = canvas.getContext("2d");
        const imageData = originalCtx.getImageData(0, 0, canvas.width, canvas.height);
        let lastContentY = canvas.height;

        // Scan from bottom to find last non-white row
        for (let y = canvas.height - 1; y >= 0; y--) {
          let hasContent = false;
          for (let x = 0; x < canvas.width; x++) {
            const idx = (y * canvas.width + x) * 4;
            const r = imageData.data[idx];
            const g = imageData.data[idx + 1];
            const b = imageData.data[idx + 2];
            // Check if pixel is not white (with some tolerance)
            if (r < 250 || g < 250 || b < 250) {
              hasContent = true;
              break;
            }
          }
          if (hasContent) {
            lastContentY = y + 1;
            break;
          }
        }

        // Create new canvas with margin
        const croppedHeight = Math.min(lastContentY, canvas.height);
        const marginedCanvas = document.createElement("canvas");
        const ctx = marginedCanvas.getContext("2d");

        marginedCanvas.width = canvas.width + margin * 2;
        marginedCanvas.height = croppedHeight + margin * 2;

        // Fill with white background
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, marginedCanvas.width, marginedCanvas.height);

        // Draw original canvas with margin
        ctx.drawImage(
          canvas,
          0,
          0,
          canvas.width,
          croppedHeight,
          margin,
          margin,
          canvas.width,
          croppedHeight
        );

        // Convert canvas to blob
        const blob = await new Promise((resolve) => {
          marginedCanvas.toBlob(resolve, "image/jpeg", 0.98);
        });

        // Create download link
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        const filename = options.filename || `export_${Date.now()}.jpg`;
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        return { filename };
      } finally {
        cleanup();
        await new Promise((resolve) => setTimeout(resolve, 200));
      }
    };

    toast.promise(performExport, {
      loading: "Generating JPG...",
      success: "Exported to JPG successfully",
      error: (err) => err?.message || "Failed to export to JPG",
      finally: () => {
        isExporting.value = false;
      },
    });
  };

  return {
    isExporting,
    exportToPDF,
    exportToJPG,
  };
};
