/**
 * Starting point for an event's Purchase Terms (`settings.tickets.terms`).
 *
 * Loaded on demand from the Purchase Terms editor, never applied automatically:
 * these are the organizer's terms, not ours, and every event needs to read them
 * before they go in front of a buyer. Without this an event ships with the terms
 * blank and the checkout dialog falls back to a four-sentence i18n string.
 *
 * Deliberately organizer-neutral - no company name, no email, no venue. PM One
 * serves 16 event sites across several legal entities, so a hardcoded name would
 * be wrong more often than right. The bracketed placeholders are there to be
 * filled in.
 *
 * Markup is limited to what the TipTap editor round-trips cleanly: h3, p, ol, ul,
 * li, strong, em. No headings above h3 - this renders inside a dialog, under the
 * dialog's own title.
 */

const EN = `
<h3>1. Agreement</h3>
<p>By completing a ticket purchase you confirm that you have read, understood and accepted these terms. If you are buying on behalf of other attendees, you confirm that you are authorised to accept these terms for them.</p>

<h3>2. Tickets and entry</h3>
<ol>
  <li>A ticket is valid only for the event, date and session shown on it.</li>
  <li>Entry requires a valid QR code. A ticket that has been copied, altered or already scanned may be refused.</li>
  <li>Tickets are not transferable once personalised with an attendee's name, and may not be resold without written permission from the organizer.</li>
</ol>

<h3>3. Your details</h3>
<p>You are responsible for the accuracy of the details you provide. Tickets, invoices and event updates are sent to the email address entered at checkout, so an incorrect address may mean you do not receive them.</p>

<h3>4. Payment</h3>
<ol>
  <li>Payment is handled by a third-party payment provider. Your order is confirmed only once that provider reports the payment as successful.</li>
  <li>An order that is not paid within the time shown at checkout is cancelled automatically and the reserved tickets are released.</li>
  <li>Any fee charged by the payment provider is shown before you confirm.</li>
</ol>

<h3>5. Sharing your details with exhibitors</h3>
<p>When your ticket is scanned at the event, the registration details you provided are shared with the exhibitor or partner who scanned it, so they can follow up with you about their products and services. This is how badge scanning at an exhibition works and it applies to every attendee.</p>
<p>Marketing contact outside the event is separate and optional: you choose that at checkout, and you can change your mind by contacting the organizer.</p>

<h3>6. Photography and recording</h3>
<p>The event is photographed, filmed and recorded. By attending you agree that your image may appear in that material and that the organizer may use it for documentation, reporting and promotion, without payment. Exhibitors, partners and media may also record at the venue.</p>

<h3>7. Changes to the event</h3>
<p>Programme, speakers, exhibitors, layout, opening hours and venue may change for operational reasons or because of circumstances beyond the organizer's control. Material changes are announced through the event's official channels.</p>

<h3>8. Cancellation and refunds</h3>
<p>Tickets are non-refundable, except where the refund policy provides otherwise or where a refund is required by law. Where a refund is due, it is returned to the original payment method, net of any payment-provider and administrative fees.</p>

<h3>9. Conduct at the event</h3>
<p>The organizer may refuse entry to, or remove, anyone who breaches the event rules, endangers others, or disrupts the event, without a refund. Follow the instructions of event staff and security at all times.</p>

<h3>10. Governing law and contact</h3>
<p>These terms are governed by the laws of the Republic of Indonesia. Disputes are settled by discussion first, and failing that before the competent court.</p>
<p>Questions about your ticket or these terms: [organizer email] / [organizer WhatsApp].</p>
`.trim();

const ID = `
<h3>1. Persetujuan</h3>
<p>Dengan menyelesaikan pembelian tiket, Anda menyatakan telah membaca, memahami, dan menyetujui ketentuan ini. Jika Anda membeli untuk peserta lain, Anda menyatakan berwenang menyetujui ketentuan ini atas nama mereka.</p>

<h3>2. Tiket dan akses masuk</h3>
<ol>
  <li>Tiket hanya berlaku untuk acara, tanggal, dan sesi yang tertera padanya.</li>
  <li>Masuk acara memerlukan kode QR yang valid. Tiket yang digandakan, diubah, atau sudah pernah dipindai dapat ditolak.</li>
  <li>Tiket tidak dapat dipindahtangankan setelah dipersonalisasi dengan nama peserta, dan tidak boleh dijual kembali tanpa izin tertulis dari penyelenggara.</li>
</ol>

<h3>3. Data Anda</h3>
<p>Anda bertanggung jawab atas kebenaran data yang Anda isi. Tiket, invoice, dan informasi acara dikirim ke alamat email yang diisi saat checkout, sehingga alamat yang keliru membuat Anda tidak menerimanya.</p>

<h3>4. Pembayaran</h3>
<ol>
  <li>Pembayaran diproses oleh penyedia pembayaran pihak ketiga. Pesanan Anda baru terkonfirmasi setelah penyedia tersebut menyatakan pembayaran berhasil.</li>
  <li>Pesanan yang tidak dibayar dalam batas waktu yang ditampilkan saat checkout otomatis dibatalkan, dan tiket yang ditahan dilepaskan kembali.</li>
  <li>Biaya dari penyedia pembayaran, jika ada, ditampilkan sebelum Anda mengonfirmasi.</li>
</ol>

<h3>5. Berbagi data dengan exhibitor</h3>
<p>Saat tiket Anda dipindai di lokasi acara, data registrasi yang Anda isi dibagikan kepada exhibitor atau mitra yang memindainya, agar mereka dapat menindaklanjuti mengenai produk dan layanan mereka. Begitulah cara kerja pemindaian badge di pameran, dan ini berlaku untuk semua peserta.</p>
<p>Kontak pemasaran di luar acara bersifat terpisah dan opsional: Anda memilihnya saat checkout, dan dapat mengubahnya dengan menghubungi penyelenggara.</p>

<h3>6. Dokumentasi dan perekaman</h3>
<p>Acara ini difoto, difilmkan, dan direkam. Dengan hadir, Anda menyetujui bahwa gambar Anda dapat muncul dalam materi tersebut dan penyelenggara dapat menggunakannya untuk dokumentasi, pelaporan, dan promosi, tanpa kompensasi. Exhibitor, mitra, dan media juga dapat melakukan perekaman di lokasi.</p>

<h3>7. Perubahan acara</h3>
<p>Rangkaian acara, pembicara, exhibitor, tata letak, jam buka, dan lokasi dapat berubah karena alasan operasional atau keadaan di luar kendali penyelenggara. Perubahan yang bersifat material diumumkan melalui kanal resmi acara.</p>

<h3>8. Pembatalan dan pengembalian dana</h3>
<p>Tiket tidak dapat dikembalikan, kecuali diatur lain dalam kebijakan pengembalian dana atau diwajibkan oleh hukum. Jika pengembalian dana berlaku, dana dikembalikan ke metode pembayaran semula, setelah dikurangi biaya penyedia pembayaran dan biaya administrasi.</p>

<h3>9. Ketertiban di lokasi acara</h3>
<p>Penyelenggara dapat menolak atau mengeluarkan siapa pun yang melanggar aturan acara, membahayakan orang lain, atau mengganggu jalannya acara, tanpa pengembalian dana. Ikuti arahan panitia dan petugas keamanan setiap saat.</p>

<h3>10. Hukum yang berlaku dan kontak</h3>
<p>Ketentuan ini tunduk pada hukum Republik Indonesia. Sengketa diselesaikan melalui musyawarah terlebih dahulu, dan bila tidak tercapai, melalui pengadilan yang berwenang.</p>
<p>Pertanyaan mengenai tiket atau ketentuan ini: [email penyelenggara] / [WhatsApp penyelenggara].</p>
`.trim();

export const TICKET_TERMS_TEMPLATE = { en: EN, id: ID };

/** Locales the template is actually written in; the rest fall back to English. */
export const TICKET_TERMS_TEMPLATE_LOCALES = ["en", "id"];

export const ticketTermsTemplateFor = (locale) =>
  TICKET_TERMS_TEMPLATE[locale] ?? TICKET_TERMS_TEMPLATE.en;
