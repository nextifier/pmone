<template>
  <div class="mx-auto w-full max-w-4xl pt-4 pb-16">
    <header>
      <ButtonBack destination="/" force-destination />
      <h1
        class="text-foreground mt-6 text-3xl font-semibold tracking-tighter text-balance sm:text-4xl"
      >
        Panduan Setup Payment Gateway
      </h1>
      <p class="mt-3 max-w-2xl text-base leading-relaxed tracking-tight text-pretty">
        Cara nyambungin Midtrans sama Xendit ke {{ appName }}, dari ambil API key sampai isi Webhook dan
        Redirect URL di dashboard provider. Di bawah juga ada error yang paling sering muncul
        beserta cara benerinnya.
      </p>
    </header>

    <!-- Mobile "On this page": sticky bar right under the app header.
         -mx-4 cancels the app layout's px-4 so it spans edge to edge. -->
    <ScrollSpyPopover class="-mx-4" content-selector="#guide-content" :title="pageTitle" />

    <div class="mt-10 xl:flex xl:gap-x-12">
      <article id="guide-content" class="text-foreground w-full min-w-0 space-y-14">
        <!-- 1. Sebelum mulai -->
        <section>
          <h2
            class="text-foreground scroll-mt-28 text-xl font-semibold tracking-tighter sm:text-2xl xl:scroll-mt-24"
          >
            Sebelum mulai
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Kamu butuh akun Midtrans atau Xendit yang udah aktif, plus akses ke menu Payment Gateways
            di project {{ appName }}. Tiga alamat di bawah ini yang nanti kamu tempel di dashboard provider.
            Salin dari sini, atau ambil langsung dari kartu gateway setelah dibuat.
          </p>

          <Card class="mt-6 gap-0 p-0">
            <div class="divide-border divide-y">
              <div v-for="v in importantValues" :key="v.label" class="p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                  <p class="font-medium tracking-tight">{{ v.label }}</p>
                  <Badge variant="outline">{{ v.provider }}</Badge>
                </div>
                <div class="mt-2 flex items-center gap-x-1.5">
                  <code
                    class="bg-muted/60 min-w-0 flex-1 truncate rounded px-1.5 py-0.5 font-mono text-xs sm:text-sm"
                  >
                    {{ v.value }}
                  </code>
                  <ButtonCopy :text="v.value" />
                </div>
              </div>
            </div>
          </Card>

          <div class="bg-muted/40 mt-4 flex flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:gap-3 sm:p-5">
            <div
              class="bg-background flex size-8 shrink-0 items-center justify-center rounded-lg border"
            >
              <Icon name="hugeicons:information-circle" class="size-4" />
            </div>
            <div class="space-y-1">
              <p class="text-sm font-semibold tracking-tight sm:text-base">
                Webhook URL beda sama Redirect URL
              </p>
              <p class="text-sm leading-relaxed tracking-tight sm:text-base">
                Webhook URL yang ngabarin {{ appName }} kalau pembayaran berhasil, jadi status
                reservasi berubah otomatis. Redirect URL yang mulangin pembeli ke website setelah
                selesai bayar. Dua-duanya alamat berbeda dan diisi di tempat berbeda. Kalau salah
                satu kosong atau salah, alurnya pincang: paling sering pembayaran masuk tapi status
                di {{ appName }} gak ikut berubah.
              </p>
            </div>
          </div>
        </section>

        <!-- 2. Setup Midtrans -->
        <section>
          <h2
            class="text-foreground scroll-mt-28 text-xl font-semibold tracking-tighter sm:text-2xl xl:scroll-mt-24"
          >
            Setup Midtrans
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Empat langkah. Midtrans verifikasi webhook pakai Server Key, jadi gak ada webhook token
            terpisah seperti Xendit.
          </p>

          <Card class="mt-6 gap-0 p-0">
            <div class="divide-border divide-y">
              <div v-for="(step, i) in midtransSteps" :key="step.title" class="flex flex-col gap-2 p-4 sm:flex-row sm:gap-4 sm:p-5">
                <div
                  class="bg-muted text-foreground flex size-9 shrink-0 items-center justify-center rounded-lg font-medium tracking-tight tabular-nums"
                >
                  {{ i + 1 }}
                </div>
                <div class="min-w-0">
                  <p class="font-semibold tracking-tight">{{ step.title }}</p>
                  <p class="mt-1 text-sm leading-relaxed tracking-tight sm:text-base">
                    {{ step.description }}
                  </p>
                </div>
              </div>
            </div>
          </Card>

          <div class="bg-muted/40 mt-4 flex flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:gap-3 sm:p-5">
            <div
              class="bg-background flex size-8 shrink-0 items-center justify-center rounded-lg border"
            >
              <Icon name="hugeicons:alert-02" class="size-4" />
            </div>
            <div class="space-y-1">
              <p class="text-sm font-semibold tracking-tight sm:text-base">
                Sandbox dan Production setelannya terpisah
              </p>
              <p class="text-sm leading-relaxed tracking-tight sm:text-base">
                Midtrans nyimpen Access Keys dan Configuration sendiri-sendiri buat Sandbox sama
                Production. Yang kamu isi di Sandbox gak kebawa ke Production. Jadi pas naik ke Live,
                ulang lagi tempel Webhook dan Redirect URL-nya di environment Production. Banyak kasus
                "status gak berubah" ujungnya cuma gara-gara Production-nya belum diisi.
              </p>
            </div>
          </div>
        </section>

        <!-- 3. Setup Xendit -->
        <section>
          <h2
            class="text-foreground scroll-mt-28 text-xl font-semibold tracking-tighter sm:text-2xl xl:scroll-mt-24"
          >
            Setup Xendit
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Lima langkah. Bedanya sama Midtrans, Xendit butuh Verification Token sendiri buat webhook,
            dan IP server kamu harus didaftarin ke allowlist.
          </p>

          <Card class="mt-6 gap-0 p-0">
            <div class="divide-border divide-y">
              <div v-for="(step, i) in xenditSteps" :key="step.title" class="flex flex-col gap-2 p-4 sm:flex-row sm:gap-4 sm:p-5">
                <div
                  class="bg-muted text-foreground flex size-9 shrink-0 items-center justify-center rounded-lg font-medium tracking-tight tabular-nums"
                >
                  {{ i + 1 }}
                </div>
                <div class="min-w-0">
                  <p class="font-semibold tracking-tight">{{ step.title }}</p>
                  <p class="mt-1 text-sm leading-relaxed tracking-tight sm:text-base">
                    {{ step.description }}
                  </p>
                </div>
              </div>
            </div>
          </Card>

          <div class="bg-muted/40 mt-4 flex flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:gap-3 sm:p-5">
            <div
              class="bg-background flex size-8 shrink-0 items-center justify-center rounded-lg border"
            >
              <Icon name="hugeicons:information-circle" class="size-4" />
            </div>
            <div class="space-y-1">
              <p class="text-sm font-semibold tracking-tight sm:text-base">
                Redirect Xendit gak perlu diisi manual
              </p>
              <p class="text-sm leading-relaxed tracking-tight sm:text-base">
                {{ appName }} nentuin halaman tujuan sukses dan gagal di tiap invoice, jadi pembeli
                otomatis balik ke website event yang bener. Kamu cukup ngurus Webhook URL sama IP
                allowlist.
              </p>
            </div>
          </div>
        </section>

        <!-- 4. Error umum dan solusinya -->
        <section>
          <h2
            class="text-foreground scroll-mt-28 text-xl font-semibold tracking-tighter sm:text-2xl xl:scroll-mt-24"
          >
            Error umum dan solusinya
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed tracking-tight sm:text-base">
            Tiga masalah yang paling sering kejadian waktu setup, sama cara benerinnya.
          </p>

          <div class="mt-6 space-y-4">
            <Card v-for="err in commonErrors" :key="err.symptom" class="gap-0 p-5">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
                <div
                  class="bg-warning/10 text-warning-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
                >
                  <Icon name="hugeicons:alert-02" class="size-5" />
                </div>
                <div class="min-w-0">
                  <Badge variant="outline">{{ err.provider }}</Badge>
                  <p class="mt-1.5 font-semibold tracking-tight">{{ err.symptom }}</p>
                  <p class="mt-3 text-sm leading-relaxed tracking-tight sm:text-base">
                    {{ err.solution }}
                  </p>
                  <a
                    v-if="err.link"
                    :href="err.link"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-primary hover:bg-muted mt-3 inline-flex w-fit items-center gap-x-1 rounded-md text-sm tracking-tight underline-offset-4 hover:underline"
                  >
                    <Icon name="hugeicons:link-01" class="size-4 shrink-0" />
                    <span>{{ err.linkLabel }}</span>
                  </a>
                </div>
              </div>
            </Card>
          </div>
        </section>
      </article>

      <aside class="hidden shrink-0 xl:block xl:w-44">
        <div class="sticky top-20">
          <ScrollSpy content-selector="#guide-content" />
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { ScrollSpy, ScrollSpyPopover } from "@/components/ui/scroll-spy";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const pageTitle = "Panduan Setup Payment Gateway";

usePageMeta(null, { title: pageTitle });

const appName = useAppConfig().app.name;
const apiUrl = useRuntimeConfig().public.apiUrl;

const importantValues = [
  {
    label: "Webhook URL",
    provider: "Midtrans",
    value: `${apiUrl}/api/webhooks/midtrans`,
  },
  {
    label: "Webhook URL",
    provider: "Xendit",
    value: `${apiUrl}/api/webhooks/xendit`,
  },
  {
    label: "Redirect URL",
    provider: "Midtrans",
    value: `${apiUrl}/payment/redirect`,
  },
];

const midtransSteps = [
  {
    title: "Ambil Client Key dan Server Key",
    description:
      "Di dashboard Midtrans, buka Settings → Access Keys. Pilih environment-nya dulu: Sandbox buat test, Production buat live. Salin Client Key sama Server Key dari halaman itu.",
  },
  {
    title: `Tambah gateway di ${appName}`,
    description: `Balik ke ${appName}, buka Payment Gateways, klik Add Gateway. Pilih provider Midtrans, atur Mode biar cocok sama environment tadi, terus isi Server Key dan Client Key-nya. Klik Test Connection buat mastiin key-nya kebaca, baru Save.`,
  },
  {
    title: "Pasang Webhook dan Redirect URL di Midtrans",
    description: `Masih di dashboard Midtrans, buka Settings → Configuration. Isi Payment Notification URL pakai Webhook URL Midtrans di atas. Isi juga Finish, Unfinish, dan Error Redirect URL pakai Redirect URL Midtrans. Tanpa langkah ini, pembayaran yang berhasil gak akan ngubah status di ${appName}.`,
  },
  {
    title: "Aktifkan dan tes",
    description:
      "Nyalain toggle Active di kartu gateway-nya, terus coba satu transaksi test sampai statusnya berubah jadi Paid.",
  },
];

const xenditSteps = [
  {
    title: "Buat Secret API Key",
    description:
      "Di dashboard Xendit, buka Settings → Developers, bagian API Keys. Pilih dulu Test atau Live mode, buat Secret API Key-nya, lalu salin.",
  },
  {
    title: "Set Webhook URL dan salin token",
    description: `Buka Settings → Webhooks. Isi Webhook URL pakai Webhook URL Xendit di atas, terus salin Verification Token-nya buat ditempel di ${appName} nanti.`,
  },
  {
    title: "Daftarin IP ke allowlist",
    description: `Buka Settings → Developers → IP Allowlist. Tambahin IP server, atau IP komputer kamu kalau lagi jalan di local. Tanpa ini, ${appName} gak bisa manggil API Xendit dan requestnya bakal kena blokir.`,
  },
  {
    title: `Tambah gateway di ${appName}`,
    description:
      "Buka Payment Gateways, klik Add Gateway, pilih provider Xendit. Atur Mode, isi Secret API Key sama Verification Token, pilih Checkout Method-nya. Test Connection, baru Save.",
  },
  {
    title: "Aktifkan dan tes",
    description: `Nyalain Active, terus coba satu transaksi sampai statusnya berubah. Redirect sukses dan gagal udah diatur otomatis sama ${appName}, gak perlu kamu isi di dashboard Xendit.`,
  },
];

const commonErrors = [
  {
    provider: "Midtrans",
    symptom: `Pembayaran berhasil, tapi status di ${appName} masih belum Paid`,
    solution: `Webhook-nya belum nyampe ke ${appName}. Buka Settings → Configuration di dashboard Midtrans, pastiin Payment Notification URL-nya diisi Webhook URL Midtrans yang bener. Inget setelan Sandbox dan Production terpisah, jadi cek di environment yang lagi kamu pakai.`,
    link: "https://dashboard.midtrans.com/settings/vtweb_configuration",
    linkLabel: "Buka Configuration Midtrans",
  },
  {
    provider: "Xendit",
    symptom: "Muncul error \"Xendit blocked this request\"",
    solution:
      "Pesannya minta nambahin server IP ke Xendit IP allowlist lewat Settings → Developers → IP Allowlist. Tambahin IP komputer kamu kalau lagi di local, atau IP server kalau di production, terus coba lagi.",
    link: "https://dashboard.xendit.co/settings/developers",
    linkLabel: "Buka Developers Xendit",
  },
  {
    provider: "Midtrans & Xendit",
    symptom: "Test Connection gagal padahal key-nya udah dicopy",
    solution: `Biasanya key-nya ketuker sama Mode-nya. Key Sandbox gak jalan di Mode Live, begitu juga sebaliknya. Pastiin key dan Mode di ${appName} satu environment yang sama, lalu ulangi Test Connection.`,
  },
];
</script>
