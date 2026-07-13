<template>
  <div class="mx-auto w-full max-w-4xl pt-4 pb-16">
    <header>
      <ButtonBack />
      <h1
        class="text-foreground mt-6 text-3xl font-semibold tracking-tighter text-balance sm:text-4xl"
      >
        Panduan Sistem Promosi
      </h1>
      <p class="mt-3 max-w-2xl text-base leading-relaxed tracking-tight text-pretty">
        Panduan singkat soal Promotion Rule dan Promo Code di {{ appName }}: jenis potongan dan
        biaya tambahan, kapan promo aktif, sampai contoh settingan buat reservasi hotel dan order.
      </p>
    </header>

    <div class="mt-10 xl:flex xl:gap-x-12">
      <article id="guide-content" class="text-foreground w-full min-w-0 space-y-14">
        <!-- 1. Konsep dasar -->
        <section>
          <h2
            class="text-foreground scroll-mt-24 text-xl font-semibold tracking-tighter sm:text-2xl"
          >
            Konsep dasar
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Sistem promosi cuma punya dua bagian. Satu ngatur gimana harga berubah, satunya lagi
            ngatur siapa yang boleh pakai.
          </p>

          <div class="mt-6 grid gap-x-2 gap-y-4 sm:grid-cols-2">
            <Card v-for="block in buildingBlocks" :key="block.title" class="gap-0 p-5">
              <div class="bg-muted flex size-10 items-center justify-center rounded-lg">
                <Icon :name="block.icon" class="size-5" />
              </div>
              <p class="mt-4 font-semibold tracking-tight">{{ block.title }}</p>
              <p class="mt-2 text-sm leading-relaxed tracking-tight sm:text-base">
                {{ block.description }}
              </p>
            </Card>
          </div>

          <div class="bg-muted/40 mt-4 flex gap-3 rounded-xl border p-4 sm:p-5">
            <div
              class="bg-background flex size-8 shrink-0 items-center justify-center rounded-lg border"
            >
              <Icon name="hugeicons:link-01" class="size-4" />
            </div>
            <div class="space-y-1">
              <p class="text-sm font-semibold tracking-tight sm:text-base">
                Satu rule, banyak kode
              </p>
              <p class="text-sm leading-relaxed tracking-tight sm:text-base">
                Bikin satu rule diskon 15 persen, terus terbitkan ratusan kode yang semuanya
                nyambung ke rule itu. Rule juga bisa jalan tanpa kode sama sekali: denda pembatalan
                muncul otomatis, dan staff bisa pasang rule langsung ke sebuah reservasi.
              </p>
            </div>
          </div>
        </section>

        <!-- 2. Discount dan Penalty -->
        <section>
          <h2
            class="text-foreground scroll-mt-24 text-xl font-semibold tracking-tighter sm:text-2xl"
          >
            Discount dan Penalty
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Tiap rule punya satu jenis (Kind). Jenis inilah yang nentuin harga bakal turun atau
            naik.
          </p>

          <div class="mt-6 grid gap-x-2 gap-y-4 sm:grid-cols-2">
            <Card v-for="rt in ruleTypes" :key="rt.title" class="gap-0 p-5">
              <div class="flex size-10 items-center justify-center rounded-lg" :class="rt.boxClass">
                <Icon :name="rt.icon" class="size-5" />
              </div>
              <p class="mt-4 font-semibold tracking-tight">{{ rt.title }}</p>
              <Badge :variant="rt.variant" class="mt-1.5">{{ rt.badge }}</Badge>
              <p class="mt-3 text-sm leading-relaxed tracking-tight sm:text-base">
                {{ rt.description }}
              </p>
            </Card>
          </div>

          <div class="bg-muted/40 mt-4 flex gap-3 rounded-xl border p-4 sm:p-5">
            <div
              class="bg-background flex size-8 shrink-0 items-center justify-center rounded-lg border"
            >
              <Icon name="hugeicons:information-circle" class="size-4" />
            </div>
            <div class="space-y-1">
              <p class="text-sm font-semibold tracking-tight sm:text-base">
                Penalty cuma punya dua Value Type
              </p>
              <p class="text-sm leading-relaxed tracking-tight sm:text-base">
                Discount bebas pakai ketujuh Value Type di bawah. Tapi Penalty cuma bisa dua:
                Percentage sama Fixed Amount. Kalau kamu pilih Value Type lain buat Penalty, sistem
                bakal nolak pas disimpan.
              </p>
            </div>
          </div>
        </section>

        <!-- 3. Value Type -->
        <section>
          <h2
            class="text-foreground scroll-mt-24 text-xl font-semibold tracking-tighter sm:text-2xl"
          >
            Value Type
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Value Type itu cara ngitung potongan atau tambahan harganya. Ada tujuh pilihan, dan nama
            di bawah ditulis sama persis kayak di form.
          </p>

          <div class="mt-6 grid gap-x-2 gap-y-4 sm:grid-cols-2">
            <Card v-for="vt in valueTypes" :key="vt.name" class="gap-0 p-5">
              <div class="flex items-start justify-between gap-3">
                <div class="bg-muted flex size-10 items-center justify-center rounded-lg">
                  <Icon :name="vt.icon" class="size-5" />
                </div>
                <Badge variant="outline">
                  {{ vt.dual ? "Discount & Penalty" : "Discount" }}
                </Badge>
              </div>
              <p class="mt-4 font-semibold tracking-tight">{{ vt.name }}</p>
              <p class="mt-2 text-sm leading-relaxed tracking-tight sm:text-base">
                {{ vt.description }}
              </p>
              <div class="bg-muted/50 mt-3 rounded-lg px-3 py-2.5">
                <p class="text-xs font-medium tracking-tight sm:text-sm">Contoh</p>
                <p class="mt-0.5 text-sm leading-relaxed tracking-tight sm:text-base">
                  {{ vt.example }}
                </p>
              </div>
            </Card>
          </div>
        </section>

        <!-- 4. Kapan promo berlaku -->
        <section>
          <h2
            class="text-foreground scroll-mt-24 text-xl font-semibold tracking-tighter sm:text-2xl"
          >
            Kapan promo berlaku
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Rule gak langsung berlaku ke semua transaksi. Lima settingan ini yang nentuin kapan rule
            aktif dan buat siapa.
          </p>

          <Card class="mt-6 gap-0 p-0">
            <div class="divide-border divide-y">
              <div v-for="c in conditions" :key="c.title" class="flex gap-4 p-4 sm:p-5">
                <div class="bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg">
                  <Icon :name="c.icon" class="size-5" />
                </div>
                <div class="min-w-0">
                  <p class="font-semibold tracking-tight">{{ c.title }}</p>
                  <p class="mt-1 text-sm leading-relaxed tracking-tight sm:text-base">
                    {{ c.description }}
                  </p>
                </div>
              </div>
            </div>
          </Card>
        </section>

        <!-- 5. Menggabungkan promo -->
        <section>
          <h2
            class="text-foreground scroll-mt-24 text-xl font-semibold tracking-tighter sm:text-2xl"
          >
            Menggabungkan promo
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Satu pemesanan kadang cocok sama beberapa rule sekaligus. Stacking Mode yang nentuin
            boleh gaknya sebuah rule dipakai barengan rule lain.
          </p>

          <div class="mt-6 grid gap-x-2 gap-y-4 sm:grid-cols-2">
            <Card v-for="m in stackingModes" :key="m.title" class="gap-0 p-5">
              <div class="bg-muted flex size-10 items-center justify-center rounded-lg">
                <Icon :name="m.icon" class="size-5" />
              </div>
              <p class="mt-4 font-semibold tracking-tight">{{ m.title }}</p>
              <p class="mt-1.5 text-sm leading-relaxed tracking-tight sm:text-base">
                {{ m.description }}
              </p>
            </Card>
          </div>

          <div class="bg-muted/40 mt-4 flex gap-3 rounded-xl border p-4 sm:p-5">
            <div
              class="bg-background flex size-8 shrink-0 items-center justify-center rounded-lg border"
            >
              <Icon name="hugeicons:exchange-01" class="size-4" />
            </div>
            <div class="space-y-1">
              <p class="text-sm font-semibold tracking-tight sm:text-base">
                Dua rule harus sama-sama ngebolehin
              </p>
              <p class="text-sm leading-relaxed tracking-tight sm:text-base">
                Dua rule baru bisa dipakai bareng kalau dua-duanya sama-sama ngebolehin. Jadi begitu
                satu rule diset Exclusive, rule lain otomatis gak bisa nempel, walaupun rule itu
                sendiri udah Combinable with All.
              </p>
            </div>
          </div>

          <p class="mt-8 font-semibold tracking-tight">
            Tiga settingan lain yang juga ngaruh ke hasil akhir
          </p>
          <Card class="mt-3 gap-0 p-0">
            <div class="divide-border divide-y">
              <div v-for="s in extraSettings" :key="s.title" class="flex gap-4 p-4 sm:p-5">
                <div class="bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg">
                  <Icon :name="s.icon" class="size-5" />
                </div>
                <div class="min-w-0">
                  <p class="font-semibold tracking-tight">{{ s.title }}</p>
                  <p class="mt-1 text-sm leading-relaxed tracking-tight sm:text-base">
                    {{ s.description }}
                  </p>
                </div>
              </div>
            </div>
          </Card>
        </section>

        <!-- 6. Penalty otomatis -->
        <section>
          <h2
            class="text-foreground scroll-mt-24 text-xl font-semibold tracking-tighter sm:text-2xl"
          >
            Penalty otomatis
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Penalty bisa dipasang manual sama staff, atau muncul sendiri lewat trigger pas kondisi
            tertentu terpenuhi. Rule discount gak pakai trigger.
          </p>

          <div class="mt-6 grid gap-x-2 gap-y-4 sm:grid-cols-2">
            <Card v-for="t in triggers" :key="t.title" class="gap-0 p-5">
              <div class="bg-muted flex size-10 items-center justify-center rounded-lg">
                <Icon :name="t.icon" class="size-5" />
              </div>
              <p class="mt-4 font-semibold tracking-tight">{{ t.title }}</p>
              <p class="mt-1.5 text-sm leading-relaxed tracking-tight sm:text-base">
                {{ t.description }}
              </p>
            </Card>
          </div>
        </section>

        <!-- 7. Promo Code -->
        <section>
          <h2
            class="text-foreground scroll-mt-24 text-xl font-semibold tracking-tighter sm:text-2xl"
          >
            Promo Code
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Promo Code itu lapisan kontrol di atas rule. Rule nentuin diskonnya, kode nentuin siapa
            yang boleh pakai dan berapa kali.
          </p>

          <Card class="mt-6 gap-0 p-0">
            <div class="divide-border divide-y">
              <div v-for="f in codeFields" :key="f.title" class="flex gap-4 p-4 sm:p-5">
                <div class="bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg">
                  <Icon :name="f.icon" class="size-5" />
                </div>
                <div class="min-w-0">
                  <p class="font-semibold tracking-tight">{{ f.title }}</p>
                  <p class="mt-1 text-sm leading-relaxed tracking-tight sm:text-base">
                    {{ f.description }}
                  </p>
                </div>
              </div>
            </div>
          </Card>

          <div class="bg-muted/40 mt-4 flex gap-3 rounded-xl border p-4 sm:p-5">
            <div
              class="bg-background flex size-8 shrink-0 items-center justify-center rounded-lg border"
            >
              <Icon name="hugeicons:calendar-02" class="size-4" />
            </div>
            <div class="space-y-1">
              <p class="text-sm font-semibold tracking-tight sm:text-base">
                Periode aktif ngikut yang paling sempit
              </p>
              <p class="text-sm leading-relaxed tracking-tight sm:text-base">
                Kode sama rule sama-sama punya periode berlaku, dan yang kepakai itu irisan paling
                sempitnya: tanggal mulai yang paling belakang, tanggal berakhir yang paling awal.
                Jadi kode yang aktif sepanjang Juni tapi nyambung ke rule yang berakhir 15 Juni
                tetap ikut berhenti tanggal 15. Kode yang kuotanya udah abis juga otomatis berhenti.
              </p>
            </div>
          </div>

          <Card class="mt-4 gap-0 p-5">
            <div class="flex items-center gap-3">
              <div
                class="bg-primary text-foreground-foreground flex size-9 items-center justify-center rounded-lg"
              >
                <Icon name="hugeicons:layers-01" class="size-5" />
              </div>
              <p class="font-semibold tracking-tight">Bulk generate buat campaign besar</p>
            </div>
            <p class="mt-3 text-sm leading-relaxed tracking-tight sm:text-base">
              Butuh banyak kode sekaligus? Bulk generate bisa bikin sampai 10.000 kode unik dalam
              sekali jalan. Kamu tinggal atur jumlahnya, prefix yang dipakai semua kode, sama
              panjang bagian randomnya.
            </p>
          </Card>
        </section>

        <!-- 8. Contoh konfigurasi -->
        <section>
          <h2
            class="text-foreground scroll-mt-24 text-xl font-semibold tracking-tighter sm:text-2xl"
          >
            Contoh konfigurasi
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Skenario yang paling sering kepakai, lengkap sama cara settingnya. Dipisah jadi dua:
            buat reservasi hotel dan buat tiket atau order.
          </p>

          <template v-for="(group, gi) in useCaseGroups" :key="group.label">
            <div class="flex items-center gap-2.5" :class="gi === 0 ? 'mt-6' : 'mt-10'">
              <div class="bg-muted flex size-7 items-center justify-center rounded-lg">
                <Icon :name="group.icon" class="size-4" />
              </div>
              <p class="font-semibold tracking-tight">{{ group.label }}</p>
            </div>

            <div class="mt-4 grid gap-x-2 gap-y-4 sm:grid-cols-2">
              <Card v-for="uc in group.cases" :key="uc.title" class="gap-0 overflow-hidden p-0">
                <div class="flex items-start gap-3 border-b p-4">
                  <div class="bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg">
                    <Icon :name="uc.icon" class="size-5" />
                  </div>
                  <div class="min-w-0">
                    <p class="font-semibold tracking-tight">{{ uc.title }}</p>
                    <Badge :variant="uc.type === 'Discount' ? 'success' : 'warning'" class="mt-1.5">
                      {{ uc.type }}
                    </Badge>
                  </div>
                </div>
                <div class="space-y-3 p-4">
                  <div>
                    <p class="text-xs font-medium tracking-tight sm:text-sm">Cara setting</p>
                    <p class="mt-1 text-sm leading-relaxed tracking-tight sm:text-base">
                      {{ uc.setup }}
                    </p>
                  </div>
                  <div class="bg-muted/80 -mx-2.5 mt-auto -mb-2.5 rounded-lg p-3">
                    <p class="text-xs font-medium tracking-tight sm:text-sm">Hasil</p>
                    <p class="mt-1 text-sm leading-relaxed tracking-tight sm:text-base">
                      {{ uc.result }}
                    </p>
                  </div>
                </div>
              </Card>
            </div>
          </template>
        </section>
      </article>

      <aside class="hidden shrink-0 xl:block xl:w-44">
        <div class="sticky top-20">
          <ScrollSpy :show-label="true" content-selector="#guide-content" />
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { ScrollSpy } from "@/components/ui/scroll-spy";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const appName = useAppConfig().app.name;

usePageMeta(null, { title: "Panduan Sistem Promosi" });

const buildingBlocks = [
  {
    icon: "hugeicons:settings-02",
    title: "Promotion Rule",
    description:
      "Ini bagian yang ngatur. Di sini kamu nentuin gimana harga berubah, misalnya potong 20 persen atau tambah Rp100.000. Sekalian juga atur syarat kapan aturan ini boleh jalan.",
  },
  {
    icon: "hugeicons:ticket-01",
    title: "Promo Code",
    description:
      "Ini yang dipakai pelanggan. Kode yang mereka ketik pas checkout. Tiap kode nyambung ke satu rule, jadi begitu kodenya valid, rule-nya langsung jalan.",
  },
];

const ruleTypes = [
  {
    icon: "hugeicons:discount-tag-02",
    title: "Discount",
    badge: "Memotong harga",
    variant: "success",
    boxClass: "bg-success/10 text-success-foreground",
    description:
      "Harga turun, pelanggan bayar lebih murah. Semua diskon sama voucher pakai jenis ini.",
  },
  {
    icon: "hugeicons:arrow-up-02",
    title: "Penalty",
    badge: "Menambah harga",
    variant: "warning",
    boxClass: "bg-warning/10 text-warning-foreground",
    description:
      "Harga naik, pelanggan bayar lebih mahal. Dipakai buat denda pembatalan, biaya pesan mendadak, atau biaya tambahan tanggal ramai.",
  },
];

const valueTypes = [
  {
    icon: "hugeicons:percent-square",
    name: "Percentage (%)",
    dual: true,
    description:
      "Potong atau tambah harga sekian persen dari total. Khusus discount, paling besar 100 persen.",
    example: "Diskon 20 persen dari subtotal kamar.",
  },
  {
    icon: "hugeicons:money-bag-02",
    name: "Fixed Amount (Rp)",
    dual: true,
    description: "Potong atau tambah pakai angka rupiah yang sama, berapa pun total belanjanya.",
    example: "Diskon Rp150.000, atau denda Rp500.000.",
  },
  {
    icon: "hugeicons:gift",
    name: "Buy X Get Y Free",
    dual: false,
    description:
      "Pelanggan dapat beberapa unit gratis kalau udah beli jumlah tertentu. Kamu yang nentuin berapa belinya dan berapa gratisnya.",
    example: "Beli 3 malam, malam keempat gratis.",
  },
  {
    icon: "hugeicons:analytics-01",
    name: "Tiered Percentage",
    dual: false,
    description:
      "Makin banyak yang dibeli, makin besar persen diskonnya. Kamu nentuin dasar jenjangnya, jumlah unit atau total belanja, terus isi daftar jenjangnya.",
    example: "1-2 kamar potong 5 persen, 3-5 kamar 10 persen, 6 kamar ke atas 15 persen.",
  },
  {
    icon: "hugeicons:presentation-bar-chart-01",
    name: "Tiered Fixed Amount",
    dual: false,
    description:
      "Mirip Tiered Percentage, tapi tiap jenjang potongannya berupa angka rupiah yang tetap.",
    example: "Menginap 1-3 malam potong Rp50.000, 4-6 malam Rp150.000.",
  },
  {
    icon: "hugeicons:package",
    name: "Bundle Price",
    dual: false,
    description:
      "Beberapa unit dijual satu harga paket, gantiin harga satuannya. Kamu nentuin jumlah unit sama harga paketnya.",
    example: "Paket 3 kamar Rp5.000.000, dari harga normal Rp6.000.000.",
  },
  {
    icon: "hugeicons:shopping-bag-02",
    name: "Free Add-on",
    dual: false,
    description:
      "Ngegratisin add-on tertentu kayak transfer bandara atau biaya tambahan. Kamu pilih item mana yang gratis, plus batas jumlahnya kalau perlu.",
    example: "Gratis transfer bandara untuk pemesanan paket tertentu.",
  },
];

const conditions = [
  {
    icon: "hugeicons:checkmark-circle-02",
    title: "Status aktif",
    description:
      "Rule yang dimatiin gak bakal kepakai sama sekali, walaupun syarat lainnya udah pas semua.",
  },
  {
    icon: "hugeicons:calendar-03",
    title: "Periode aktif",
    description:
      "Tanggal mulai sama berakhirnya rule. Di luar rentang itu, rule berhenti. Kosongin aja kalau rule berlaku tanpa batas waktu.",
  },
  {
    icon: "hugeicons:shopping-cart-01",
    title: "Minimum belanja",
    description:
      "Subtotal minimal biar rule bisa kepakai. Pemesanan yang belum nyampe angka ini gak kebagian promo.",
  },
  {
    icon: "hugeicons:filter",
    title: "Kelayakan",
    description:
      "Filter yang lebih detail: event, hotel atau tipe kamar, produk atau jenis tiket, brand, minimum malam, minimum unit, pembeli pertama kali, domain email, sampai hari tertentu dalam seminggu. Filter yang dibiarin kosong artinya gak ngebatesin apa-apa.",
  },
  {
    icon: "hugeicons:layers-01",
    title: "Target",
    description: "Nentuin rule berlaku di reservasi hotel, order, atau dua-duanya.",
  },
];

const stackingModes = [
  {
    icon: "hugeicons:lock",
    title: "Exclusive",
    description: "Gak bisa digabung sama rule apa pun. Pelanggan cuma pakai satu promo.",
  },
  {
    icon: "hugeicons:ticket-01",
    title: "Combinable with Promo",
    description: "Boleh digabung sama kode promo lain, tapi gak sama potongan manual dari staff.",
  },
  {
    icon: "hugeicons:user-edit-01",
    title: "Combinable with Manual",
    description: "Boleh digabung sama potongan manual dari staff, tapi gak sama kode promo lain.",
  },
  {
    icon: "hugeicons:layers-01",
    title: "Combinable with All",
    description: "Boleh digabung sama promo apa pun.",
  },
];

const extraSettings = [
  {
    icon: "hugeicons:left-to-right-list-number",
    title: "Prioritas",
    description:
      "Urutan pemrosesan pas ada beberapa rule yang bersaing. Pakai ini kalau ada rule yang harus diproses duluan.",
  },
  {
    icon: "hugeicons:calculator-01",
    title: "Batas diskon maksimum",
    description:
      "Ngebatesin besarnya diskon persentase. Diskon 20 persen dengan batas Rp1.000.000 gak bakal motong lebih dari sejuta, walaupun 20 persen dari totalnya ternyata lebih besar.",
  },
  {
    icon: "hugeicons:invoice-03",
    title: "Posisi terhadap pajak",
    description:
      "Nentuin potongan atau tambahan dihitung sebelum atau sesudah pajak. Defaultnya sebelum pajak.",
  },
];

const triggers = [
  {
    icon: "hugeicons:cursor-pointer-02",
    title: "Manual",
    description: "Staff yang mutusin kapan penalty dipasang. Gak ada yang otomatis.",
  },
  {
    icon: "hugeicons:calendar-check-out-02",
    title: "Booking Window",
    description:
      "Penalty muncul kalau pemesanan masuk di window tertentu, misalnya window pemesanan onsite.",
  },
  {
    icon: "hugeicons:calendar-03",
    title: "Event Period",
    description: "Penalty muncul kalau pemesanan jatuh di fase event tertentu.",
  },
  {
    icon: "hugeicons:calendar-02",
    title: "Date Range",
    description:
      "Penalty muncul buat pemesanan di rentang tanggal tertentu. Cocok buat biaya tambahan musim ramai.",
  },
  {
    icon: "hugeicons:timer-01",
    title: "Lead Time",
    description:
      "Penalty muncul kalau tanggal check-in udah terlalu mepet. Dasar buat biaya pesan mendadak.",
  },
  {
    icon: "hugeicons:cancel-circle",
    title: "Cancellation Window",
    description:
      "Penalty muncul kalau pembatalan dilakuin beberapa hari sebelum check-in. Dasar buat biaya pembatalan.",
  },
];

const codeFields = [
  {
    icon: "hugeicons:barcode-scan",
    title: "Kode",
    description:
      "Teks yang diketik pelanggan. Boleh huruf, angka, tanda hubung, sama garis bawah. Sistem otomatis nyimpennya dalam huruf kapital.",
  },
  {
    icon: "hugeicons:calculator-01",
    title: "Batas pemakaian total",
    description:
      "Berapa kali kode ini boleh dipakai sama semua orang. Kosongin kalau mau tanpa batas.",
  },
  {
    icon: "hugeicons:mail-at-sign-01",
    title: "Batas per email",
    description: "Berapa kali satu alamat email boleh pakai kode yang sama.",
  },
  {
    icon: "hugeicons:calendar-03",
    title: "Periode berlaku",
    description: "Tanggal mulai sama berakhirnya kode.",
  },
  {
    icon: "hugeicons:user-check-01",
    title: "Voucher personal",
    description: "Isi satu alamat email, dan cuma email itu yang bisa pakai kodenya.",
  },
];

const useCaseGroups = [
  {
    label: "Buat reservasi hotel",
    icon: "hugeicons:hotel-01",
    cases: [
      {
        icon: "hugeicons:discount-tag-02",
        type: "Discount",
        title: "Diskon 20 persen lewat kode",
        setup:
          "Bikin rule discount Value Type Percentage 20 persen, Stacking Mode-nya Exclusive. Terus terbitkan kode kayak SUMMER20 dengan batas satu kali per email.",
        result:
          "Pelanggan yang masukin kode langsung dapat potongan 20 persen dari subtotal kamar.",
      },
      {
        icon: "hugeicons:gift",
        type: "Discount",
        title: "Voucher Rp100.000 untuk satu tamu",
        setup:
          "Bikin rule discount Value Type Fixed Amount Rp100.000. Terus terbitkan kode, isi alamat email tamunya, set batas total satu kali.",
        result: "Cuma email itu yang bisa nukerin kodenya, dan cuma sekali pakai.",
      },
      {
        icon: "hugeicons:bed-single-01",
        type: "Discount",
        title: "Beli 3 malam gratis 1",
        setup:
          "Bikin rule discount Value Type Buy X Get Y Free, isi beli 3 gratis 1. Set kelayakannya ke minimum 3 malam.",
        result: "Tamu yang pesan 4 malam cukup bayar 3 malam.",
      },
      {
        icon: "hugeicons:analytics-01",
        type: "Discount",
        title: "Diskon volume berjenjang",
        setup:
          "Bikin rule discount Value Type Tiered Percentage, dasar jenjangnya jumlah kamar. Misalnya 3-5 kamar potong 10 persen, 6 kamar ke atas 15 persen.",
        result: "Makin banyak kamar dalam satu pemesanan, makin besar potongannya.",
      },
      {
        icon: "hugeicons:package",
        type: "Discount",
        title: "Paket 3 kamar harga khusus",
        setup: "Bikin rule discount Value Type Bundle Price, isi 3 kamar seharga Rp5.000.000.",
        result: "Tiga kamar ditagih Rp5.000.000, bukan dijumlah dari harga satuannya.",
      },
      {
        icon: "hugeicons:car-01",
        type: "Discount",
        title: "Gratis transfer bandara",
        setup:
          "Bikin rule discount Value Type Free Add-on, targetnya transfer. Set kelayakannya ke tipe kamar yang dituju.",
        result: "Biaya transfer jadi nol buat pemesanan yang memenuhi syarat.",
      },
      {
        icon: "hugeicons:timer-01",
        type: "Penalty",
        title: "Biaya pemesanan mendadak",
        setup:
          "Bikin rule penalty Value Type Fixed Amount Rp150.000, triggernya Lead Time, misalnya check-in kurang dari 7 hari.",
        result: "Biaya tambahan langsung muncul otomatis, pelanggan gak perlu masukin kode.",
      },
      {
        icon: "hugeicons:cancel-circle",
        type: "Penalty",
        title: "Biaya pembatalan",
        setup:
          "Bikin rule penalty Value Type Percentage 50 persen, triggernya Cancellation Window, misalnya pembatalan kurang dari 2 hari sebelum check-in.",
        result: "Pembatalan H-1 kena potongan 50 persen dari total.",
      },
    ],
  },
  {
    label: "Buat tiket dan order",
    icon: "hugeicons:ticket-01",
    cases: [
      {
        icon: "hugeicons:discount-tag-02",
        type: "Discount",
        title: "Tiket gratis 100 persen",
        setup:
          "Bikin rule discount Value Type Percentage 100 persen, target order. Terus terbitkan kode khusus, misalnya buat tamu undangan atau media partner.",
        result: "Tiket yang kena kode jadi Rp0. Pas buat complimentary ticket.",
      },
      {
        icon: "hugeicons:gift",
        type: "Discount",
        title: "Beli 1 tiket gratis 1 tiket",
        setup:
          "Bikin rule discount Value Type Buy X Get Y Free, isi beli 1 gratis 1, target order.",
        result:
          "Pelanggan beli 1 tiket dan bayar 1 tiket. Sistem otomatis ngubah jadi 2 tiket di pesanannya.",
      },
      {
        icon: "hugeicons:package",
        type: "Discount",
        title: "Paket bundle 3 tiket",
        setup:
          "Bikin rule discount Value Type Bundle Price, isi 3 tiket dengan satu harga paket, target order.",
        result: "Tiga tiket ditagih satu harga paket, lebih murah daripada beli satuan.",
      },
      {
        icon: "hugeicons:clock-01",
        type: "Discount",
        title: "Diskon early bird tiket",
        setup:
          "Bikin rule discount Value Type Percentage, misalnya 25 persen. Set periode aktif rule cuma sampai tanggal tertentu.",
        result: "Pembeli yang pesan sebelum tanggal itu dapat potongan 25 persen.",
      },
      {
        icon: "hugeicons:user-multiple",
        type: "Discount",
        title: "Diskon beli banyak tiket",
        setup:
          "Bikin rule discount Value Type Tiered Percentage, dasar jenjangnya jumlah tiket. Misalnya 5-9 tiket potong 10 persen, 10 tiket ke atas 15 persen.",
        result: "Makin banyak tiket dalam satu order, makin besar potongannya.",
      },
      {
        icon: "hugeicons:store-02",
        type: "Penalty",
        title: "Biaya admin tiket onsite",
        setup:
          "Bikin rule penalty Value Type Fixed Amount Rp25.000, triggernya Booking Window buat window pemesanan onsite.",
        result: "Tiket yang dibeli onsite otomatis kena biaya admin tambahan.",
      },
    ],
  },
];
</script>
