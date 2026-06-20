import { buildQRSvgString } from "@/composables/useQRCode";

// Builds a print-ready QR-badge PDF entirely in the browser (jsPDF + the shared
// buildQRSvgString so the QR matches the on-screen <QRCode> component). Nothing
// is rendered or stored server-side, so badges always reflect live data.
export function useTicketBadgePdf() {
  // Render the shared styled QR SVG and rasterise it to a PNG data URL for jsPDF.
  async function qrPng(token, px) {
    const qrLib = (await import("qrcode")).default;
    const svg = buildQRSvgString(qrLib.create(String(token), { errorCorrectionLevel: "M" }), {
      size: px,
      margin: 2,
      fgColor: "#0a0a0a",
      bgColor: "#ffffff",
      styleVariant: "rounded",
    });
    return await new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        const c = document.createElement("canvas");
        c.width = px;
        c.height = px;
        const cx = c.getContext("2d");
        cx.fillStyle = "#ffffff";
        cx.fillRect(0, 0, px, px);
        cx.drawImage(img, 0, 0, px, px);
        resolve(c.toDataURL("image/png"));
      };
      img.onerror = () => resolve(null);
      img.src = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
    });
  }

  async function generate(attendees, { fileName = "badges.pdf" } = {}) {
    const list = (attendees ?? []).filter((a) => a?.qr_token);
    if (!list.length) return;

    const { jsPDF } = await import("jspdf");

    const doc = new jsPDF({ unit: "mm", format: "a4" });

    // 2 x 4 badge grid per A4 page.
    const pageW = 210;
    const pageH = 297;
    const margin = 10;
    const cols = 2;
    const rows = 4;
    const perPage = cols * rows;
    const cellW = (pageW - margin * 2) / cols;
    const cellH = (pageH - margin * 2) / rows;
    const qrSize = 30;

    for (let i = 0; i < list.length; i++) {
      const att = list[i];
      const slot = i % perPage;
      if (i > 0 && slot === 0) doc.addPage();

      const col = slot % cols;
      const row = Math.floor(slot / cols);
      const x = margin + col * cellW;
      const y = margin + row * cellH;

      doc.setDrawColor(225);
      doc.roundedRect(x + 2, y + 2, cellW - 4, cellH - 4, 2, 2);

      const qrDataUrl = await qrPng(att.qr_token, 320);
      if (qrDataUrl) doc.addImage(qrDataUrl, "PNG", x + (cellW - qrSize) / 2, y + 8, qrSize, qrSize);

      doc.setFontSize(11);
      doc.setTextColor(24);
      doc.text(String(att.name || "Guest"), x + cellW / 2, y + qrSize + 16, {
        align: "center",
        maxWidth: cellW - 10,
      });

      const sub = [att.ticket?.title, att.ticket?.tier].filter(Boolean).join("  ·  ");
      if (sub) {
        doc.setFontSize(8);
        doc.setTextColor(120);
        doc.text(sub, x + cellW / 2, y + qrSize + 22, { align: "center", maxWidth: cellW - 10 });
      }

      // Yield to the main thread so big batches don't freeze the tab.
      if (i % 50 === 49) await new Promise((r) => setTimeout(r, 0));
    }

    doc.save(fileName);
  }

  return { generate };
}
