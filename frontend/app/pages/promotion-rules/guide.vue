<template>
  <div class="mx-auto w-full max-w-4xl px-4 pt-4 pb-24 sm:px-6">
    <header class="mx-auto w-full max-w-2xl xl:mx-0">
      <ButtonBack v-slot="{ goBack }">
        <button
          @click="goBack"
          class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight transition active:scale-98"
        >
          <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
          <span>Kembali</span>
        </button>
      </ButtonBack>

      <h1
        class="text-primary mt-6 text-3xl font-semibold tracking-tighter text-balance sm:text-4xl"
      >
        Panduan Sistem Promosi
      </h1>
      <p
        class="text-muted-foreground mt-3 text-base leading-relaxed tracking-tight text-pretty"
      >
        Cara kerja Promotion Rule dan Promo Code di PM One: jenis diskon, biaya tambahan,
        syarat berlaku, dan contoh konfigurasi untuk reservasi hotel dan order.
      </p>
    </header>

    <div class="mt-8 xl:flex xl:gap-x-12">
      <article
        id="guide-content"
        class="format-html prose-headings:first:mt-0 prose-headings:scroll-mt-24 mx-auto w-full overflow-x-hidden xl:mx-0"
      >
        <h2>Konsep dasar</h2>
        <p>Sistem promosi bekerja dengan dua bagian yang saling terkait.</p>
        <p>
          <strong>Promotion Rule</strong> adalah aturannya. Rule menyimpan cara sebuah
          penyesuaian harga dihitung, misalnya potong 20 persen atau tambah Rp 100.000,
          beserta syarat kapan penyesuaian itu boleh dipakai.
        </p>
        <p>
          <strong>Promo Code</strong> adalah kode yang dimasukkan pelanggan saat checkout.
          Setiap kode terhubung ke satu rule. Saat pelanggan memasukkan kode yang valid,
          sistem menjalankan rule di belakangnya.
        </p>
        <p>
          Hubungannya satu ke banyak. Satu rule bisa punya banyak kode sekaligus: buat satu
          rule diskon 15 persen, lalu terbitkan ratusan kode berbeda yang semuanya mengarah
          ke rule itu.
        </p>
        <p>
          Rule juga bisa jalan tanpa kode. Biaya tambahan otomatis seperti denda pembatalan
          tidak butuh kode pelanggan, dan staff bisa menerapkan sebuah rule secara manual
          langsung ke reservasi.
        </p>

        <h2>Discount dan Penalty</h2>
        <p>
          Setiap rule punya satu jenis, dan jenis ini menentukan arah penyesuaian harganya.
        </p>
        <ul>
          <li>
            <strong>Discount</strong> memotong harga. Pelanggan membayar lebih sedikit.
            Semua diskon dan voucher memakai jenis ini.
          </li>
          <li>
            <strong>Penalty</strong> menambah harga. Pelanggan membayar lebih banyak.
            Dipakai untuk denda pembatalan, biaya pemesanan mendadak, atau surcharge tanggal
            ramai.
          </li>
        </ul>
        <p>
          Jenis berpengaruh ke tipe nilai yang tersedia. Discount bisa memakai ketujuh tipe
          nilai di bawah. Penalty hanya bisa memakai dua, yaitu persentase dan nominal
          tetap.
        </p>

        <h2>Tipe nilai</h2>
        <p>
          Tipe nilai menentukan rumus penyesuaian. Ada tujuh pilihan. Nama dalam kurung
          adalah pilihan yang kamu lihat di form.
        </p>
        <ul>
          <li>
            <strong>Persentase</strong> (Percentage). Memotong atau menambah harga sekian
            persen dari total. Tersedia untuk discount dan penalty; untuk discount, nilainya
            maksimum 100 persen. <em>Contoh: diskon 20 persen dari subtotal kamar.</em>
          </li>
          <li>
            <strong>Nominal tetap</strong> (Fixed Amount). Memotong atau menambah angka
            rupiah yang sama, berapa pun total belanjanya. Tersedia untuk discount dan
            penalty. <em>Contoh: diskon Rp 150.000, atau denda Rp 500.000.</em>
          </li>
          <li>
            <strong>Beli X gratis Y</strong> (Buy X Get Y Free). Pelanggan dapat sejumlah
            unit gratis setelah membeli sejumlah unit tertentu; kamu mengisi jumlah beli dan
            jumlah gratisnya. Hanya untuk discount.
            <em>Contoh: beli 3 malam, malam keempat gratis.</em>
          </li>
          <li>
            <strong>Persentase berjenjang</strong> (Tiered Percentage). Persen diskon naik
            mengikuti jumlah unit atau total belanja; kamu mengisi dasar jenjang dan daftar
            jenjangnya. Hanya untuk discount.
            <em>
              Contoh: 1 sampai 2 kamar potong 5 persen, 3 sampai 5 kamar 10 persen, 6 kamar
              ke atas 15 persen.
            </em>
          </li>
          <li>
            <strong>Nominal berjenjang</strong> (Tiered Fixed Amount). Sama seperti
            persentase berjenjang, tapi potongannya berupa rupiah tetap di tiap jenjang.
            Hanya untuk discount.
            <em>
              Contoh: menginap 1 sampai 3 malam potong Rp 50.000, 4 sampai 6 malam Rp
              150.000.
            </em>
          </li>
          <li>
            <strong>Harga paket</strong> (Bundle Price). Sejumlah unit dijual dengan satu
            harga paket yang menggantikan harga satuan; kamu mengisi jumlah unit dan harga
            paketnya. Hanya untuk discount.
            <em>Contoh: paket 3 kamar Rp 5.000.000, dari harga normal Rp 6.000.000.</em>
          </li>
          <li>
            <strong>Item gratis</strong> (Free Add-on). Menggratiskan item tambahan tertentu
            seperti transfer bandara atau surcharge; kamu memilih item yang digratiskan dan
            batas jumlahnya bila perlu. Hanya untuk discount.
            <em>Contoh: gratis transfer bandara untuk pemesanan paket tertentu.</em>
          </li>
        </ul>
        <p>
          Kalau jenis rule diset ke Penalty, memilih tipe nilai selain persentase atau
          nominal tetap akan ditolak saat disimpan.
        </p>

        <h2>Kapan promo berlaku</h2>
        <p>
          Sebuah rule tidak otomatis berlaku untuk semua transaksi. Pengaturan berikut
          menentukan kapan dan untuk siapa rule itu aktif.
        </p>
        <ul>
          <li>
            <strong>Status aktif</strong>. Rule yang dimatikan tidak pernah dipakai, meski
            semua syarat lain terpenuhi.
          </li>
          <li>
            <strong>Periode aktif</strong>. Tanggal mulai dan berakhir rule. Di luar rentang
            ini rule berhenti. Kosongkan kalau rule berlaku tanpa batas waktu.
          </li>
          <li>
            <strong>Minimum belanja</strong>. Subtotal minimal sebelum rule bisa dipakai.
            Pemesanan di bawah angka ini tidak memenuhi syarat.
          </li>
          <li>
            <strong>Kelayakan</strong>. Filter kondisi yang lebih rinci: event, hotel atau
            tipe kamar, produk atau jenis tiket, brand, minimum jumlah malam, minimum jumlah
            unit, pembeli pertama kali, domain email, dan hari tertentu dalam seminggu.
            Filter yang dikosongkan berarti tanpa batasan.
          </li>
          <li>
            <strong>Target</strong>. Menentukan rule berlaku untuk reservasi hotel, order,
            atau keduanya.
          </li>
        </ul>

        <h2>Menggabungkan beberapa promo</h2>
        <p>
          Satu pemesanan kadang memenuhi syarat beberapa rule sekaligus. Stacking mode
          menentukan apakah sebuah rule boleh dipakai bersama rule lain.
        </p>
        <ul>
          <li>
            <strong>Exclusive</strong>. Tidak bisa digabung dengan rule mana pun. Pelanggan
            memakai satu promo saja.
          </li>
          <li>
            <strong>Combinable with Promo</strong>. Bisa digabung dengan kode promo lain,
            tapi tidak dengan penyesuaian manual dari staff.
          </li>
          <li>
            <strong>Combinable with Manual</strong>. Bisa digabung dengan penyesuaian manual
            staff, tapi tidak dengan kode promo lain.
          </li>
          <li><strong>Combinable with All</strong>. Bisa digabung dengan promo apa pun.</li>
        </ul>
        <p>
          Penggabungan diperiksa dua arah. Dua rule baru bisa dipakai bersama kalau keduanya
          saling mengizinkan. Kalau satu rule diset Exclusive, rule lain tidak bisa menempel
          meski rule itu sendiri Combinable with All.
        </p>
        <p>Tiga pengaturan lain ikut memengaruhi hasil akhir.</p>
        <ul>
          <li>
            <strong>Prioritas</strong>. Menentukan urutan pemrosesan saat beberapa rule
            bersaing. Atur ini kalau kamu butuh satu rule diproses lebih dulu.
          </li>
          <li>
            <strong>Batas diskon maksimum</strong>. Membatasi nilai diskon persentase.
            Diskon 20 persen dengan batas Rp 1.000.000 tidak akan memotong lebih dari
            sejuta, meski 20 persen dari total melebihi angka itu.
          </li>
          <li>
            <strong>Posisi terhadap pajak</strong>. Menentukan penyesuaian dihitung sebelum
            atau sesudah pajak. Bawaannya sebelum pajak.
          </li>
        </ul>

        <h2>Penalty otomatis</h2>
        <p>
          Penalty bisa diterapkan dengan dua cara: manual oleh staff, atau otomatis lewat
          trigger. Trigger membuat penalty menyala sendiri saat kondisi tertentu terpenuhi,
          tanpa kode dan tanpa tindakan staff. Rule diskon tidak memakai trigger.
        </p>
        <ul>
          <li>
            <strong>Manual</strong>. Staff yang memutuskan kapan penalty dipasang. Tidak ada
            otomatisasi.
          </li>
          <li>
            <strong>Booking Window</strong>. Penalty menyala saat pemesanan masuk di window
            tertentu, misalnya window pemesanan onsite.
          </li>
          <li>
            <strong>Event Period</strong>. Penalty menyala saat waktu sekarang berada dalam
            fase event tertentu.
          </li>
          <li>
            <strong>Date Range</strong>. Penalty menyala untuk pemesanan dalam rentang
            tanggal tetap. Cocok untuk surcharge musim ramai.
          </li>
          <li>
            <strong>Lead Time</strong>. Penalty menyala saat tanggal check-in terlalu dekat.
            Dasar untuk biaya pemesanan mendadak.
          </li>
          <li>
            <strong>Cancellation Window</strong>. Penalty menyala saat pembatalan dilakukan
            dalam sekian hari sebelum check-in. Dasar untuk biaya pembatalan.
          </li>
        </ul>

        <h2>Promo Code</h2>
        <p>
          Promo Code adalah lapisan kontrol di atas rule. Rule menentukan diskonnya, kode
          menentukan siapa yang boleh memakai dan berapa kali.
        </p>
        <ul>
          <li>
            <strong>Kode</strong>. Teks yang dimasukkan pelanggan. Boleh huruf, angka, tanda
            hubung, dan garis bawah. Sistem menyimpannya dalam huruf kapital.
          </li>
          <li>
            <strong>Batas pemakaian total</strong>. Berapa kali kode boleh dipakai oleh
            semua orang. Kosongkan untuk pemakaian tanpa batas.
          </li>
          <li>
            <strong>Batas per email</strong>. Berapa kali satu alamat email boleh memakai
            kode yang sama.
          </li>
          <li><strong>Periode berlaku</strong>. Tanggal mulai dan berakhir kode.</li>
          <li>
            <strong>Voucher personal</strong>. Kalau kamu mengisi satu alamat email, hanya
            email itu yang bisa memakai kode tersebut.
          </li>
        </ul>
        <p>
          Kode dan rule sama-sama punya periode berlaku. Yang dipakai adalah irisan paling
          ketat dari keduanya: tanggal mulai paling akhir, tanggal berakhir paling awal.
          Kode yang berlaku sepanjang Juni tapi menunjuk rule yang berakhir 15 Juni tetap
          berhenti pada tanggal 15.
        </p>
        <p>
          Kode yang sudah mencapai batas pemakaian totalnya berstatus habis dan tidak bisa
          dipakai lagi.
        </p>
        <p>
          Untuk kampanye besar, fitur bulk generate membuat banyak kode unik sekaligus,
          sampai 10.000 dalam sekali jalan. Kamu mengatur jumlahnya, awalan kode yang sama
          untuk semua, dan panjang bagian acaknya.
        </p>

        <h2>Contoh konfigurasi</h2>
        <p>Skenario yang sering dipakai dan cara menyetelnya.</p>
        <ol>
          <li>
            <strong>Diskon 20 persen lewat kode</strong>. Untuk kampanye diskon umum yang
            disebar lewat email. Buat rule discount tipe persentase senilai 20 persen dengan
            stacking exclusive, lalu terbitkan kode seperti SUMMER20 dengan batas satu kali
            per email. Pelanggan yang memasukkan kode mendapat potongan 20 persen dari
            subtotal kamar.
          </li>
          <li>
            <strong>Voucher Rp 100.000 untuk satu tamu</strong>. Untuk kompensasi ke satu
            pelanggan tertentu. Buat rule discount tipe nominal tetap senilai Rp 100.000,
            lalu terbitkan kode yang diisi alamat email tamu dengan batas total satu kali.
            Hanya email itu yang bisa menukar kode, dan hanya sekali.
          </li>
          <li>
            <strong>Beli 3 malam gratis 1</strong>. Untuk mendorong tamu menginap lebih
            lama. Buat rule discount tipe beli X gratis Y dengan beli 3 gratis 1, dan set
            kelayakan minimum 3 malam. Tamu yang memesan 4 malam membayar 3 malam.
          </li>
          <li>
            <strong>Diskon volume berjenjang</strong>. Untuk harga lebih murah pada
            pemesanan grup. Buat rule discount tipe persentase berjenjang dengan dasar
            jumlah kamar, misalnya 3 sampai 5 kamar potong 10 persen dan 6 kamar ke atas 15
            persen. Potongan mengikuti jumlah kamar dalam satu pemesanan.
          </li>
          <li>
            <strong>Paket 3 kamar harga khusus</strong>. Untuk paket rombongan dengan harga
            bulat. Buat rule discount tipe harga paket, 3 kamar seharga Rp 5.000.000. Tiga
            kamar ditagih Rp 5.000.000, bukan akumulasi harga satuan.
          </li>
          <li>
            <strong>Gratis transfer bandara</strong>. Untuk bonus pada tipe kamar tertentu.
            Buat rule discount tipe item gratis dengan target transfer, dan set kelayakan ke
            tipe kamar yang dituju. Biaya transfer jadi nol untuk pemesanan yang memenuhi
            syarat.
          </li>
          <li>
            <strong>Biaya pemesanan mendadak</strong>. Untuk surcharge pada check-in yang
            terlalu dekat. Buat rule penalty tipe nominal tetap senilai Rp 150.000 dengan
            trigger lead time, misalnya check-in kurang dari 7 hari. Surcharge muncul
            otomatis tanpa perlu kode.
          </li>
          <li>
            <strong>Biaya pembatalan</strong>. Untuk potongan saat tamu membatalkan
            mendekati tanggal menginap. Buat rule penalty tipe persentase senilai 50 persen
            dengan trigger cancellation window, misalnya pembatalan kurang dari 2 hari
            sebelum check-in. Pembatalan H-1 dikenakan 50 persen dari total.
          </li>
        </ol>
      </article>

      <aside class="mt-12 hidden shrink-0 xl:mt-0 xl:block xl:w-44">
        <div class="sticky top-20">
          <p class="text-muted-foreground mb-1 text-xs font-medium tracking-tight sm:text-sm">
            Di halaman ini
          </p>
          <ScrollSpy :show-label="false" content-selector="#guide-content" />
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { ScrollSpy } from "@/components/ui/scroll-spy";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, { title: "Panduan Sistem Promosi" });
</script>
