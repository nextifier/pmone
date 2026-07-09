<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:mail-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Email Delivery</h1>
      </div>

      <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
        <DialogResponsive dialog-max-width="640px">
          <template #trigger="{ open }">
            <Button variant="outline" size="sm" @click="open()">
              <Icon name="hugeicons:information-circle" class="size-4 shrink-0" />
              <span>About suppressions</span>
            </Button>
          </template>

          <template #default>
            <div class="space-y-6 px-4 pt-2 pb-8 md:px-6 md:py-4">
              <div class="space-y-2">
                <h2 class="text-lg font-semibold tracking-tighter">Apa itu suppression list</h2>
                <p class="text-muted-foreground text-sm tracking-tight">
                  Suppression list adalah blacklist alamat email. Alamat yang ada di dalamnya tidak
                  akan dikirimi email lagi.
                </p>
              </div>

              <!-- Dua angka ini yang menentukan segalanya, jadi dibuat full-bleed sebagai jangkar visual. -->
              <dl
                class="-mx-4 grid grid-cols-1 divide-y border-y sm:grid-cols-2 sm:divide-x sm:divide-y-0 md:-mx-6"
              >
                <div class="space-y-1 px-4 py-4 md:px-6">
                  <dt class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                    Batas bounce
                  </dt>
                  <dd class="text-xl font-semibold tracking-tighter tabular-nums">5%</dd>
                  <dd class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                    Email ditolak dan dipantulkan balik
                  </dd>
                </div>
                <div class="space-y-1 px-4 py-4 md:px-6">
                  <dt class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                    Batas complaint
                  </dt>
                  <dd class="text-xl font-semibold tracking-tighter tabular-nums">0,1%</dd>
                  <dd class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                    Satu laporan spam dari seribu email
                  </dd>
                </div>
              </dl>

              <p class="text-muted-foreground -mt-3 text-sm tracking-tight">
                Lewat salah satu angka itu, Amazon bisa membekukan akun SES.
              </p>

              <div class="divide-y text-sm">
                <section class="space-y-2 py-5 first:pt-0 last:pb-0">
                  <h3 class="font-medium tracking-tight">Bounce dan complaint</h3>
                  <p class="text-muted-foreground tracking-tight">
                    Bounce berarti email ditolak dan dipantulkan balik. Umumnya karena alamatnya
                    sudah tidak ada, misalnya email kantor seseorang yang sudah resign. Mirip
                    mengirim surat ke rumah yang sudah dibongkar, lalu tukang pos mengembalikannya.
                  </p>
                  <p class="text-muted-foreground tracking-tight">
                    Complaint berarti emailnya sampai, tapi penerimanya menekan tombol Report spam.
                    Gmail lalu melapor ke Amazon bahwa orang ini tidak mau menerima email itu.
                  </p>
                </section>

                <section class="space-y-2 py-5 first:pt-0 last:pb-0">
                  <h3 class="font-medium tracking-tight">
                    Kenapa dua angka ini menentukan nasib seluruh akun
                  </h3>
                  <p class="text-muted-foreground tracking-tight">
                    Yang berhenti bukan cuma email promosi. E-ticket, voucher hotel, konfirmasi
                    order, dan magic link keluar lewat akun yang sama, jadi ikut mati.
                  </p>
                  <p class="text-muted-foreground tracking-tight">
                    Spammer menembak ribuan alamat asal-asalan. Kalau email terus dikirim ke alamat
                    yang jelas sudah mati, Gmail dan Amazon menganggap daftar kontaknya tidak
                    dirawat. Reputasi turun, lalu email yang sah pun mulai mendarat di spam folder.
                  </p>
                </section>

                <section class="space-y-2 py-5 first:pt-0 last:pb-0">
                  <h3 class="font-medium tracking-tight">Cara suppression list melindungi akun</h3>
                  <p class="text-muted-foreground tracking-tight">
                    Misalnya email dikirim ke budi@contoh.com. Alamatnya mati, Amazon memantulkannya,
                    lalu mengabarkannya lewat webhook. Alamat itu masuk suppression list. Berikutnya,
                    saat ada yang mencoba mengirim ke sana, pengirimannya dibatalkan sebelum email
                    itu sampai ke Amazon.
                  </p>
                  <p class="text-muted-foreground tracking-tight">
                    Satu bounce cukup dicatat sekali, bukan memantul seratus kali.
                  </p>
                  <p class="text-muted-foreground tracking-tight">
                    Tidak semua bounce masuk daftar. Bounce sementara seperti mailbox penuh dibiarkan
                    lewat, karena mailbox penuh bukan berarti alamatnya mati. Hanya bounce permanen
                    yang dicatat.
                  </p>
                </section>

                <section class="space-y-2 py-5 first:pt-0 last:pb-0">
                  <h3 class="font-medium tracking-tight">Kalau mau menghapus alamat dari daftar</h3>
                  <p class="text-muted-foreground tracking-tight">
                    Alamat yang masuk daftar karena kesalahan, misalnya mailbox-nya sempat penuh lalu
                    sudah dibereskan, bisa dihapus lewat tombol di tab Suppressions.
                  </p>
                </section>
              </div>

              <!-- Strip penutup dibuat rata ke tepi dialog supaya tidak terbaca sebagai kartu bersarang. -->
              <div
                class="bg-muted/40 -mx-4 -mb-8 flex items-start gap-3 border-t px-4 py-5 md:-mx-6 md:-mb-4 md:px-6"
              >
                <Icon name="hugeicons:alert-02" class="text-warning mt-0.5 size-4 shrink-0" />
                <div class="space-y-1 text-sm">
                  <p class="font-medium tracking-tight">Daftarnya ada di dua tempat</p>
                  <p class="text-muted-foreground tracking-tight">
                    Yang terhapus cuma daftar di aplikasi ini. Amazon menyimpan daftarnya sendiri di
                    level akun, dan halaman ini tidak menyentuhnya. Kalau setelah dihapus email ke
                    alamat itu tetap tidak sampai, berarti alamatnya masih tertahan di sisi Amazon
                    dan harus dibersihkan dari konsol AWS.
                  </p>
                </div>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </div>
    </div>

    <Alert
      v-if="quota && !quota.production_access"
      class="bg-warning/10 border-warning/20 text-warning-foreground"
    >
      <Icon name="hugeicons:alert-02" />
      <AlertTitle class="tracking-tight">Your SES account is still in the sandbox</AlertTitle>
      <AlertDescription class="text-warning-foreground/90 tracking-tight">
        Only verified addresses can receive mail, and the daily quota stays at
        {{ formatNumber(quota.max_24_hour_send) }}. Production access is pending review by AWS.
      </AlertDescription>
    </Alert>

    <Alert v-if="quota && !quota.available" class="bg-info/10 border-info/20 text-info-foreground">
      <Icon name="hugeicons:information-circle" />
      <AlertTitle class="tracking-tight">Sending quota is unavailable</AlertTitle>
      <AlertDescription class="text-info-foreground/90 tracking-tight">
        The SES API could not be reached. The counts below are drawn from this application's own
        records and remain accurate.
      </AlertDescription>
    </Alert>

    <section
      class="t-panel-slide space-y-3"
      :data-open="revealed"
      :style="{ '--panel-translate-y': '16px' }"
    >
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-x-2">
          <Icon name="hugeicons:chart-line-data-02" class="text-muted-foreground size-4 shrink-0" />
          <h2 class="text-muted-foreground text-sm font-semibold tracking-tight">Overview</h2>
        </div>
        <Badge v-if="quota?.enforcement_status" :variant="enforcementVariant" plain>
          {{ quota.enforcement_status }}
        </Badge>
      </div>

      <GridFill :count="5" min-col-width="210px" rounded="xl">
        <template v-if="overviewPending">
          <div v-for="i in 5" :key="`sk-${i}`" class="flex flex-col gap-y-3 p-4 sm:p-5">
            <Skeleton class="size-5 rounded" />
            <div class="space-y-1.5">
              <Skeleton class="h-3.5 w-20" />
              <Skeleton class="h-3 w-28" />
            </div>
            <Skeleton class="h-6 w-16" />
          </div>
        </template>

        <template v-else>
          <div class="flex flex-col items-center justify-center gap-y-1 p-4">
            <ChartSemiCircle
              :value="quota?.sent_last_24_hours ?? 0"
              :max="Math.max(quota?.max_24_hour_send ?? 0, 1)"
              :center-label="`${quotaPercent}% of daily quota`"
              show-max
              :animate-value="true"
              class="w-full max-w-47.5"
            />
          </div>

          <div class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
            <Icon name="hugeicons:mail-01" class="size-5 text-violet-500" />
            <div class="min-w-0">
              <span class="text-foreground text-sm font-medium tracking-tight">Sent</span>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Last 30 days</p>
            </div>
            <NumberFlow :class="statValueClass" :value="last30?.sent ?? 0" locales="en-US" />
          </div>

          <div class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
            <Icon name="hugeicons:checkmark-circle-02" class="size-5 text-emerald-500" />
            <div class="min-w-0">
              <span class="text-foreground text-sm font-medium tracking-tight">Delivered</span>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Accepted by the mailbox
              </p>
            </div>
            <NumberFlow :class="statValueClass" :value="last30?.delivered ?? 0" locales="en-US" />
          </div>

          <div class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
            <Icon name="hugeicons:cancel-circle" class="size-5 text-rose-500" />
            <div class="min-w-0">
              <span class="text-foreground text-sm font-medium tracking-tight">Bounced</span>
              <p
                class="text-xs tracking-tight sm:text-sm"
                :class="bounceVariant === 'success' ? 'text-muted-foreground' : 'text-destructive'"
              >
                {{ formatRate(last30?.bounce_rate) }} of sent, limit 5%
              </p>
            </div>
            <NumberFlow :class="statValueClass" :value="last30?.bounced ?? 0" locales="en-US" />
          </div>

          <div class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
            <Icon name="hugeicons:alert-02" class="size-5 text-amber-500" />
            <div class="min-w-0">
              <span class="text-foreground text-sm font-medium tracking-tight">Complaints</span>
              <p
                class="text-xs tracking-tight sm:text-sm"
                :class="
                  complaintVariant === 'success' ? 'text-muted-foreground' : 'text-destructive'
                "
              >
                {{ formatRate(last30?.complaint_rate) }} of sent, limit 0.1%
              </p>
            </div>
            <NumberFlow :class="statValueClass" :value="last30?.complained ?? 0" locales="en-US" />
          </div>
        </template>
      </GridFill>
    </section>

    <Tabs v-model="activeTab" variant="segmented" class="flex flex-col gap-4">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger value="messages">Messages</TabsTrigger>
        <TabsTrigger value="suppressions">
          <span>Suppressions</span>
          <Badge v-if="suppressedTotal > 0" variant="muted" plain class="ml-2">
            {{ formatNumber(suppressedTotal) }}
          </Badge>
        </TabsTrigger>
      </TabsList>

      <TabsContent value="messages">
        <TableData
          ref="messagesTableRef"
          :client-only="false"
          :data="messages"
          :columns="messageColumns"
          :meta="messagesMeta"
          :pending="messagesPending"
          :error="messagesError"
          model="email-messages"
          label="Message"
          search-column="subject"
          search-placeholder="Search subject, sender, or recipient…"
          error-title="Error loading messages"
          :initial-pagination="messagesPagination"
          :initial-sorting="messagesSorting"
          :initial-column-filters="messagesFilters"
          :show-add-button="false"
          @update:pagination="(v) => (messagesPagination = v)"
          @update:sorting="(v) => (messagesSorting = v)"
          @update:column-filters="(v) => (messagesFilters = v)"
          @refresh="refreshMessages"
        >
          <template #filters>
            <ClientOnly>
              <Popover>
                <PopoverTrigger asChild>
                  <button
                    class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
                  >
                    <Icon name="hugeicons:filter-horizontal" class="size-4 shrink-0" />
                    <span class="hidden sm:flex">Filter</span>
                    <span
                      v-if="activeStatusFilters.length > 0"
                      class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
                    >
                      {{ activeStatusFilters.length }}
                    </span>
                  </button>
                </PopoverTrigger>
                <PopoverContent class="w-auto min-w-52 space-y-4 p-3" align="start">
                  <div class="space-y-2">
                    <div class="text-muted-foreground text-xs font-medium">Status</div>
                    <div class="space-y-2">
                      <div
                        v-for="option in statusOptions"
                        :key="option.value"
                        class="flex items-center gap-2"
                      >
                        <Checkbox
                          :id="`messages-status-${option.value}`"
                          :model-value="activeStatusFilters.includes(option.value)"
                          @update:model-value="
                            (checked) =>
                              toggleStatusFilter({ checked: !!checked, value: option.value })
                          "
                        />
                        <Label
                          :for="`messages-status-${option.value}`"
                          class="grow cursor-pointer font-normal tracking-tight"
                        >
                          {{ option.label }}
                        </Label>
                      </div>
                    </div>
                  </div>
                </PopoverContent>
              </Popover>
            </ClientOnly>
          </template>
        </TableData>
      </TabsContent>

      <TabsContent value="suppressions">
        <TableData
          ref="suppressionsTableRef"
          :client-only="false"
          :data="suppressions"
          :columns="suppressionColumns"
          :meta="suppressionsMeta"
          :pending="suppressionsPending"
          :error="suppressionsError"
          model="email-suppressions"
          label="Suppressed address"
          search-column="email"
          search-placeholder="Search email address…"
          error-title="Error loading suppression list"
          :initial-pagination="suppressionsPagination"
          :initial-sorting="suppressionsSorting"
          :initial-column-filters="suppressionsFilters"
          :show-add-button="false"
          @update:pagination="(v) => (suppressionsPagination = v)"
          @update:sorting="(v) => (suppressionsSorting = v)"
          @update:column-filters="(v) => (suppressionsFilters = v)"
          @refresh="refreshSuppressions"
        >
          <template #filters>
            <ClientOnly>
              <Popover>
                <PopoverTrigger asChild>
                  <button
                    class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
                  >
                    <Icon name="hugeicons:filter-horizontal" class="size-4 shrink-0" />
                    <span class="hidden sm:flex">Filter</span>
                    <span
                      v-if="activeReasonFilters.length > 0"
                      class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
                    >
                      {{ activeReasonFilters.length }}
                    </span>
                  </button>
                </PopoverTrigger>
                <PopoverContent class="w-auto min-w-52 space-y-4 p-3" align="start">
                  <div class="space-y-2">
                    <div class="text-muted-foreground text-xs font-medium">Reason</div>
                    <div class="space-y-2">
                      <div
                        v-for="option in reasonOptions"
                        :key="option.value"
                        class="flex items-center gap-2"
                      >
                        <Checkbox
                          :id="`suppressions-reason-${option.value}`"
                          :model-value="activeReasonFilters.includes(option.value)"
                          @update:model-value="
                            (checked) =>
                              toggleReasonFilter({ checked: !!checked, value: option.value })
                          "
                        />
                        <Label
                          :for="`suppressions-reason-${option.value}`"
                          class="grow cursor-pointer font-normal tracking-tight"
                        >
                          {{ option.label }}
                        </Label>
                      </div>
                    </div>
                  </div>
                </PopoverContent>
              </Popover>
            </ClientOnly>
          </template>
        </TableData>
      </TabsContent>
    </Tabs>


    <DialogResponsive v-model:open="detailOpen" dialog-max-width="640px">
      <template #default>
        <div class="space-y-6 px-4 pt-2 pb-8 md:px-6 md:py-4">
          <div class="space-y-1">
            <h2 class="text-lg font-semibold tracking-tighter">
              {{ detail?.subject || "Message" }}
            </h2>
            <p class="text-muted-foreground text-sm tracking-tight break-all">
              {{ detail?.message_id }}
            </p>
          </div>

          <div v-if="detailPending" class="space-y-2">
            <Skeleton v-for="n in 3" :key="n" class="h-14 w-full rounded-lg" />
          </div>

          <template v-else-if="detail">
            <div class="grid grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2">
              <div class="space-y-1">
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">From</p>
                <p class="text-sm tracking-tight">{{ detail.from_address }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">To</p>
                <p class="text-sm tracking-tight break-all">{{ detail.recipients.join(", ") }}</p>
              </div>
            </div>

            <div class="space-y-2">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Timeline</p>

              <Empty v-if="!detail.events?.length">
                <EmptyHeader>
                  <EmptyMedia variant="stacked">
                    <Icon name="hugeicons:inbox" class="size-5" />
                  </EmptyMedia>
                  <EmptyTitle>No events yet</EmptyTitle>
                  <EmptyDescription>
                    SES has not reported anything for this message beyond acceptance.
                  </EmptyDescription>
                </EmptyHeader>
              </Empty>

              <ul v-else class="space-y-2">
                <li
                  v-for="event in detail.events"
                  :key="event.id"
                  class="bg-card flex flex-col gap-y-1 rounded-lg border p-3"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <Badge :variant="statusVariant(event.type)" plain>{{ event.type_label }}</Badge>
                    <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                      {{ $dayjs(event.occurred_at).format("D MMM YYYY, HH:mm:ss") }}
                    </span>
                  </div>
                  <div v-if="event.recipient" class="text-sm tracking-tight">
                    {{ event.recipient }}
                  </div>
                  <div
                    v-if="event.diagnostic"
                    class="text-muted-foreground text-xs tracking-tight sm:text-sm"
                  >
                    {{ event.diagnostic }}
                  </div>
                </li>
              </ul>
            </div>
          </template>
        </div>
      </template>
    </DialogResponsive>

    <DialogResponsive v-model:open="removeOpen">
      <template #default>
        <div class="space-y-6 px-4 pt-2 pb-8 md:px-6 md:py-4">
          <div class="space-y-1">
            <h2 class="text-lg font-semibold tracking-tighter">Remove from suppression list?</h2>
            <p class="text-muted-foreground text-sm tracking-tight">
              Sending to
              <span class="text-foreground">{{ removeTarget?.email }}</span>
              will resume. This does not clear the address from Amazon's own account-level
              suppression list.
            </p>
          </div>

          <div class="flex justify-end gap-2">
            <Button variant="outline" :disabled="removePending" @click="removeOpen = false">
              Cancel
            </Button>
            <Button variant="destructive" :disabled="removePending" @click="confirmRemove">
              <Spinner v-if="removePending" />
              <span>Remove</span>
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import SuppressionRowActions from "@/components/email/SuppressionRowActions.vue";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Skeleton } from "@/components/ui/skeleton";
import { TableData } from "@/components/ui/table-data";
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { h } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["emails.view"],
  layout: "app",
});

defineOptions({ name: "email-delivery" });

usePageMeta(null, { title: "Email Delivery" });

const { $dayjs } = useNuxtApp();
const { hasPermission } = usePermission();
const canManageSuppressions = computed(() => hasPermission("emails.manage_suppressions"));

const activeTab = ref("messages");

// Panel-reveal entrance, mirroring AttendeeAnalyticsSummary: mount closed, then
// flip open on the next frame so the section slides into place.
const revealed = ref(false);
onMounted(() => {
  requestAnimationFrame(() => requestAnimationFrame(() => (revealed.value = true)));
});

const statValueClass =
  "text-foreground -mb-1 text-lg leading-tight font-medium tracking-tighter sm:text-xl";

/* ------------------------------------------------------------- shared helpers */

// These take the ref itself, never the array. Vue unwraps refs in templates, so
// they must only ever be called from script - a template call would silently
// hand over a plain array and `filters.value` would be undefined.
const selectedFilter = (filters, id) => {
  const filter = filters.value.find((entry) => entry.id === id);
  return Array.isArray(filter?.value) ? filter.value : [];
};

// Multi-select column filters, mirroring the attendees table: values live in
// `columnFilters` so TableData's own clear-filters affordance keeps working.
const toggleFilter = (filters, id, { checked, value }) => {
  const current = selectedFilter(filters, id);
  const updated = checked ? [...current, value] : current.filter((entry) => entry !== value);
  const index = filters.value.findIndex((entry) => entry.id === id);

  if (updated.length) {
    if (index >= 0) filters.value[index].value = updated;
    else filters.value.push({ id, value: updated });
  } else if (index >= 0) {
    filters.value.splice(index, 1);
  }
};

const sortParam = (sorting, fallback) => {
  const entry = sorting.value[0];
  if (!entry) return `-${fallback}`;
  return entry.desc ? `-${entry.id}` : entry.id;
};

/* ------------------------------------------------------------------ overview */

const {
  data: overviewResponse,
  pending: overviewPending,
  refresh: refreshOverview,
} = await useLazySanctumFetch("/api/email-delivery/overview", {
  key: "email-delivery-overview",
});

const quota = computed(() => overviewResponse.value?.data?.quota ?? null);
const last30 = computed(() => overviewResponse.value?.data?.last_30_days ?? null);
const suppressedTotal = computed(() => overviewResponse.value?.data?.suppressed_total ?? 0);

const enforcementVariant = computed(() =>
  quota.value?.enforcement_status === "HEALTHY" ? "success" : "destructive",
);

const quotaPercent = computed(() => {
  const max = quota.value?.max_24_hour_send ?? 0;
  if (max <= 0) return 0;
  return Math.round(((quota.value?.sent_last_24_hours ?? 0) / max) * 100);
});

// AWS suspends an account above 5% bounces, and warns well before that.
const bounceVariant = computed(() => {
  const rate = last30.value?.bounce_rate ?? 0;
  if (rate >= 5) return "destructive";
  if (rate >= 2) return "warning";
  return "success";
});

const complaintVariant = computed(() => {
  const rate = last30.value?.complaint_rate ?? 0;
  if (rate >= 0.1) return "destructive";
  if (rate >= 0.05) return "warning";
  return "success";
});

const formatNumber = (value) => new Intl.NumberFormat("en-US").format(Math.round(value ?? 0));
const formatRate = (value) => `${(value ?? 0).toFixed(2)}%`;

const statusVariant = (status) =>
  ({
    send: "muted",
    delivery: "success",
    open: "info",
    click: "info",
    delivery_delay: "warning",
    reject: "destructive",
    rendering_failure: "destructive",
    bounce: "destructive",
    complaint: "destructive",
  })[status] ?? "muted";

/* ------------------------------------------------------------------ messages */

const messagesTableRef = ref();
const messagesPagination = ref({ pageIndex: 0, pageSize: 25 });
const messagesSorting = ref([{ id: "sent_at", desc: true }]);
const messagesFilters = ref([]);

const statusOptions = [
  { value: "send", label: "Sent" },
  { value: "delivery", label: "Delivered" },
  { value: "bounce", label: "Bounced" },
  { value: "complaint", label: "Complaint" },
  { value: "delivery_delay", label: "Delayed" },
  { value: "reject", label: "Rejected" },
  { value: "rendering_failure", label: "Rendering failure" },
];

const activeStatusFilters = computed(() => selectedFilter(messagesFilters, "status"));
const toggleStatusFilter = (payload) => toggleFilter(messagesFilters, "status", payload);

const buildMessagesQuery = () => {
  const params = new URLSearchParams();
  params.append("page", messagesPagination.value.pageIndex + 1);
  params.append("per_page", messagesPagination.value.pageSize);
  params.append("sort", sortParam(messagesSorting, "sent_at"));

  const search = messagesFilters.value.find((filter) => filter.id === "subject")?.value;
  if (search) params.append("search", search);

  if (activeStatusFilters.value.length) {
    params.append("status", activeStatusFilters.value.join(","));
  }

  return params.toString();
};

const {
  data: messagesResponse,
  pending: messagesPending,
  error: messagesError,
  refresh: refreshMessages,
} = await useLazySanctumFetch(() => `/api/email-delivery/messages?${buildMessagesQuery()}`, {
  key: "email-delivery-messages",
  watch: false,
});

const messages = computed(() => messagesResponse.value?.data ?? []);
const messagesMeta = computed(
  () => messagesResponse.value?.meta ?? { current_page: 1, last_page: 1, per_page: 25, total: 0 },
);

watch([messagesPagination, messagesSorting, messagesFilters], () => refreshMessages(), {
  deep: true,
});

// Narrowing the result set while sitting on a later page would ask the server
// for a page that no longer exists.
watch(
  messagesFilters,
  () => {
    messagesPagination.value = { ...messagesPagination.value, pageIndex: 0 };
  },
  { deep: true },
);

const messageColumns = [
  {
    header: "Subject",
    accessorKey: "subject",
    cell: ({ row }) =>
      h(
        "button",
        {
          type: "button",
          class:
            "flex w-full min-w-0 cursor-pointer flex-col gap-0.5 text-left transition-opacity hover:opacity-80",
          onClick: () => openDetail(row.original.id),
        },
        [
          h(
            "span",
            { class: "truncate text-sm tracking-tight" },
            row.original.subject || "(no subject)",
          ),
          h(
            "span",
            { class: "text-muted-foreground truncate text-xs tracking-tight sm:text-sm" },
            (row.original.recipients || []).join(", "),
          ),
        ],
      ),
    size: 320,
    enableHiding: false,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) =>
      h(
        Badge,
        { variant: statusVariant(row.original.status), plain: true },
        () => row.original.status_label,
      ),
    size: 140,
  },
  {
    header: "Sent",
    accessorKey: "sent_at",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-xs tracking-tight sm:text-sm" },
        $dayjs(row.original.sent_at).format("D MMM YYYY, HH:mm"),
      ),
    size: 150,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    enableSorting: false,
    enableHiding: false,
    size: 56,
    cell: ({ row }) =>
      h("div", { class: "flex items-center justify-end" }, [
        h(
          Button,
          {
            variant: "ghost",
            size: "iconSm",
            "aria-label": "View delivery timeline",
            onClick: () => openDetail(row.original.id),
          },
          () => h(resolveComponent("Icon"), { name: "hugeicons:view", class: "size-4" }),
        ),
      ]),
  },
];

/* -------------------------------------------------------------------- detail */

const detailOpen = ref(false);
const detailPending = ref(false);
const detail = ref(null);

const openDetail = async (id) => {
  detail.value = null;
  detailOpen.value = true;
  detailPending.value = true;

  try {
    const client = useSanctumClient();
    const response = await client(`/api/email-delivery/messages/${id}`);
    detail.value = response.data;
  } catch {
    toast.error("Could not load the delivery timeline.");
    detailOpen.value = false;
  } finally {
    detailPending.value = false;
  }
};

/* --------------------------------------------------------------- suppressions */

const suppressionsTableRef = ref();
const suppressionsPagination = ref({ pageIndex: 0, pageSize: 25 });
const suppressionsSorting = ref([{ id: "suppressed_at", desc: true }]);
const suppressionsFilters = ref([]);

const reasonOptions = [
  { value: "bounce", label: "Bounce" },
  { value: "complaint", label: "Complaint" },
  { value: "manual", label: "Manual" },
];

const activeReasonFilters = computed(() => selectedFilter(suppressionsFilters, "reason"));
const toggleReasonFilter = (payload) => toggleFilter(suppressionsFilters, "reason", payload);

const buildSuppressionsQuery = () => {
  const params = new URLSearchParams();
  params.append("page", suppressionsPagination.value.pageIndex + 1);
  params.append("per_page", suppressionsPagination.value.pageSize);
  params.append("sort", sortParam(suppressionsSorting, "suppressed_at"));

  const search = suppressionsFilters.value.find((filter) => filter.id === "email")?.value;
  if (search) params.append("search", search);

  if (activeReasonFilters.value.length) {
    params.append("reason", activeReasonFilters.value.join(","));
  }

  return params.toString();
};

const {
  data: suppressionsResponse,
  pending: suppressionsPending,
  error: suppressionsError,
  refresh: refreshSuppressions,
} = await useLazySanctumFetch(
  () => `/api/email-delivery/suppressions?${buildSuppressionsQuery()}`,
  {
    key: "email-delivery-suppressions",
    watch: false,
  },
);

const suppressions = computed(() => suppressionsResponse.value?.data ?? []);
const suppressionsMeta = computed(
  () =>
    suppressionsResponse.value?.meta ?? { current_page: 1, last_page: 1, per_page: 25, total: 0 },
);

watch(
  [suppressionsPagination, suppressionsSorting, suppressionsFilters],
  () => refreshSuppressions(),
  {
    deep: true,
  },
);

watch(
  suppressionsFilters,
  () => {
    suppressionsPagination.value = { ...suppressionsPagination.value, pageIndex: 0 };
  },
  { deep: true },
);

const suppressionColumns = [
  {
    header: "Address",
    accessorKey: "email",
    cell: ({ row }) =>
      h("span", { class: "block truncate text-sm tracking-tight" }, row.original.email),
    size: 260,
    enableHiding: false,
  },
  {
    header: "Reason",
    accessorKey: "reason",
    cell: ({ row }) =>
      h("div", { class: "flex min-w-0 flex-col gap-0.5" }, [
        h(
          "div",
          { class: "flex" },
          h(
            Badge,
            {
              variant: row.original.reason === "complaint" ? "destructive" : "warning",
              plain: true,
            },
            () => row.original.reason_label,
          ),
        ),
        row.original.diagnostic
          ? h(
              "span",
              { class: "text-muted-foreground truncate text-xs tracking-tight sm:text-sm" },
              row.original.diagnostic,
            )
          : null,
      ]),
    size: 220,
  },
  {
    header: "Suppressed",
    accessorKey: "suppressed_at",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-xs tracking-tight sm:text-sm" },
        $dayjs(row.original.suppressed_at).format("D MMM YYYY, HH:mm"),
      ),
    size: 150,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    enableSorting: false,
    enableHiding: false,
    size: 56,
    cell: ({ row }) =>
      canManageSuppressions.value
        ? h(SuppressionRowActions, { onRemove: () => askRemove(row.original) })
        : null,
  },
];

const removeOpen = ref(false);
const removePending = ref(false);
const removeTarget = ref(null);

const askRemove = (suppression) => {
  removeTarget.value = suppression;
  removeOpen.value = true;
};

const confirmRemove = async () => {
  if (!removeTarget.value) return;

  removePending.value = true;

  try {
    const client = useSanctumClient();
    await client(`/api/email-delivery/suppressions/${removeTarget.value.id}`, { method: "DELETE" });

    toast.success("Address removed from the suppression list.");
    removeOpen.value = false;
    await Promise.all([refreshSuppressions(), refreshOverview()]);
  } catch {
    toast.error("Could not remove the address.");
  } finally {
    removePending.value = false;
  }
};
</script>
