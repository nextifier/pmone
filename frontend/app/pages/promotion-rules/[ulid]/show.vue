<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/promotion-rules" />

      <div v-if="rule" class="flex flex-col">
        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2">
          <h1 class="page-title">{{ rule.name }}</h1>
          <span
            :class="[
              'inline-flex items-center rounded-full px-2 py-0.5 text-xs sm:text-sm tracking-tight',
              rule.kind === 'discount' ? 'bg-success/15 text-success-foreground' : 'bg-warning/15 text-warning-foreground',
            ]"
          >
            {{ rule.kind_label }}
          </span>
          <span
            :class="[
              'inline-flex items-center rounded-full px-2 py-0.5 text-xs sm:text-sm tracking-tight',
              rule.is_active ? 'bg-success/15 text-success-foreground' : 'bg-muted text-muted-foreground',
            ]"
          >
            {{ rule.is_active ? "Active" : "Inactive" }}
          </span>
        </div>
        <p v-if="rule.description" class="page-description mt-1.5">{{ rule.description }}</p>
      </div>
    </div>

    <div v-if="pending" class="space-y-3">
      <Skeleton class="h-24 w-full rounded-md" />
      <Skeleton class="h-40 w-full rounded-md" />
    </div>

    <div v-else-if="rule" class="space-y-6">
      <div class="flex flex-wrap gap-2">
        <Button v-if="canEdit" as-child variant="outline" size="sm">
          <NuxtLink :to="`/promotion-rules/${rule.ulid}/edit`">
            <Icon name="lucide:pencil" class="size-4 shrink-0" />
            Edit
          </NuxtLink>
        </Button>

        <Button as-child variant="outline" size="sm">
          <NuxtLink :to="`/promo-codes?filter_rule_id=${rule.id}`">
            <Icon name="hugeicons:coupon-03" class="size-4 shrink-0" />
            View Codes ({{ rule.codes_count ?? 0 }})
          </NuxtLink>
        </Button>

        <Button
          v-if="canCreateCodes"
          size="sm"
          variant="outline"
          @click="bulkDialogOpen = true"
        >
          <Icon name="lucide:zap" class="size-4 shrink-0" />
          Bulk Generate Codes
        </Button>

        <Button
          v-if="canDelete"
          variant="destructive"
          size="sm"
          @click="deleteDialogOpen = true"
        >
          <Icon name="lucide:trash" class="size-4 shrink-0" />
          Delete
        </Button>
      </div>

      <!-- Report stats -->
      <div v-if="report" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-md border p-4">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Codes Issued</p>
          <p class="mt-1 text-xl font-medium tabular-nums tracking-tighter">
            {{ report.stats?.codes_issued ?? 0 }}
          </p>
        </div>
        <div class="rounded-md border p-4">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Codes Used</p>
          <p class="mt-1 text-xl font-medium tabular-nums tracking-tighter">
            {{ report.stats?.codes_used ?? 0 }}
          </p>
        </div>
        <div class="rounded-md border p-4">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Total Applications</p>
          <p class="mt-1 text-xl font-medium tabular-nums tracking-tighter">
            {{ report.stats?.total_uses ?? 0 }}
          </p>
        </div>
        <div class="rounded-md border p-4">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Total Amount</p>
          <p class="mt-1 text-xl font-medium tabular-nums tracking-tighter">
            Rp{{ formatRupiah(report.stats?.total_amount) }}
          </p>
        </div>
      </div>

      <!-- Value Details -->
      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tighter">Value</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Type</p>
            <p>{{ rule.value_type_label || rule.value_type }}</p>
          </div>
          <div v-if="['percentage', 'fixed_amount'].includes(rule.value_type)">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Value</p>
            <p class="tabular-nums">
              {{ rule.value_type === "percentage" ? `${rule.value}%` : `Rp${formatRupiah(rule.value)}` }}
            </p>
          </div>
          <template v-for="(item, idx) in valueConfigRows" :key="idx">
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ item.label }}</p>
              <p :class="item.mono ? 'tabular-nums' : ''">{{ item.value }}</p>
            </div>
          </template>
          <div v-if="rule.max_discount_amount">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Max Discount Cap</p>
            <p class="tabular-nums">Rp{{ formatRupiah(rule.max_discount_amount) }}</p>
          </div>
          <div v-if="rule.min_purchase_amount">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Min Purchase</p>
            <p class="tabular-nums">Rp{{ formatRupiah(rule.min_purchase_amount) }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Applies Before Tax</p>
            <p>{{ rule.applies_before_tax ? "Yes" : "No" }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Priority</p>
            <p>{{ rule.priority }}</p>
          </div>
        </div>
      </div>

      <!-- Applicability -->
      <div v-if="applicabilityRows.length" class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tighter">Applicability</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div v-for="(item, idx) in applicabilityRows" :key="idx">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ item.label }}</p>
            <p :class="item.mono ? 'tabular-nums' : ''">{{ item.value }}</p>
          </div>
        </div>
      </div>

      <!-- Validity -->
      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tighter">Stacking & Validity</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Stacking Mode</p>
            <p>{{ rule.stacking_mode_label }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Revert on Cancel</p>
            <p>{{ rule.revert_usage_on_cancel ? "Yes" : "No" }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Starts At</p>
            <p>{{ formatDate(rule.starts_at) }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Ends At</p>
            <p>{{ formatDate(rule.ends_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Trigger (penalty only) -->
      <div v-if="rule.kind === 'penalty'" class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tighter">Penalty Trigger</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Trigger Type</p>
            <p>{{ rule.trigger_type_label }}</p>
          </div>
          <template v-for="(item, idx) in triggerConfigRows" :key="idx">
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ item.label }}</p>
              <p :class="item.mono ? 'tabular-nums' : ''">{{ item.value }}</p>
            </div>
          </template>
        </div>
      </div>

      <!-- Targeting -->
      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tighter">Targeting</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Purchase Types</p>
            <p>{{ rule.target_types?.length ? rule.target_types.join(", ") : "All" }}</p>
          </div>
          <div v-if="rule.event">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Event</p>
            <p>{{ rule.event.title }}</p>
          </div>
          <div v-if="rule.project">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Project</p>
            <p>{{ rule.project.name }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen" dialog-max-width="28rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">Delete Promotion Rule</h3>
          <p class="text-muted-foreground text-sm tracking-tight mt-2">
            Soft delete this rule. Existing codes will become unusable. You can restore from Trash.
          </p>
          <div class="flex justify-end gap-2 pt-4">
            <Button variant="outline" @click="deleteDialogOpen = false">Cancel</Button>
            <Button variant="destructive" @click="handleDelete" :disabled="deleting">
              <Spinner v-if="deleting" />
              {{ deleting ? "Deleting..." : "Delete" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Bulk Generate Dialog -->
    <DialogResponsive v-model:open="bulkDialogOpen" dialog-max-width="32rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5 space-y-4">
          <h3 class="text-lg font-semibold tracking-tighter">Bulk Generate Promo Codes</h3>
          <p class="text-muted-foreground text-sm tracking-tight">
            Generates random codes attached to this rule.
          </p>
          <form @submit.prevent="handleBulkGenerate" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label for="quantity">Quantity</Label>
                <Input id="quantity" v-model.number="bulkForm.quantity" type="number" min="1" max="10000" required />
              </div>
              <div class="space-y-2">
                <Label for="length">Length</Label>
                <Input id="length" v-model.number="bulkForm.length" type="number" min="4" max="40" />
              </div>
            </div>
            <div class="space-y-2">
              <Label for="prefix">Prefix (optional)</Label>
              <Input id="prefix" v-model="bulkForm.prefix" maxlength="20" placeholder="e.g. WELCOME-" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label for="usage_limit">Usage Limit per Code</Label>
                <Input id="usage_limit" v-model.number="bulkForm.usage_limit" type="number" min="1" />
              </div>
              <div class="space-y-2">
                <Label for="usage_limit_per_email">Per Email</Label>
                <Input id="usage_limit_per_email" v-model.number="bulkForm.usage_limit_per_email" type="number" min="1" />
              </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" @click="bulkDialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="bulkGenerating">
                <Spinner v-if="bulkGenerating" />
                {{ bulkGenerating ? "Generating..." : "Generate Codes" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promotion_rules.read"],
  layout: "app",
});

const route = useRoute();
const client = useSanctumClient();
const { hasPermission } = usePermission();

const canEdit = computed(() => hasPermission("promotion_rules.update"));
const canDelete = computed(() => hasPermission("promotion_rules.delete"));
const canCreateCodes = computed(() => hasPermission("promotions.bulk_generate_codes"));

const {
  data: response,
  pending,
  refresh,
} = await useLazySanctumFetch(() => `/api/promotion-rules/${route.params.ulid}`, {
  key: () => `promotion-rule-${route.params.ulid}`,
});

const reportRef = ref(null);
async function fetchReport() {
  try {
    const res = await client(`/api/promotion-rules/${route.params.ulid}/report`);
    reportRef.value = res?.data ?? null;
  } catch (e) {
    // ignore - permission may be missing
  }
}
onMounted(fetchReport);

const rule = computed(() => response.value?.data);
const report = computed(() => reportRef.value);

usePageMeta(null, {
  title: computed(() => (rule.value ? `${rule.value.name} · Promotion Rules` : "Promotion Rule")),
});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
const formatDate = (iso) =>
  iso ? new Date(iso).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" }) : "-";

const OPERATOR_LABELS = {
  lt: "Less than",
  lte: "Less than or equal",
  gt: "Greater than",
  gte: "Greater than or equal",
};
const PHASE_LABELS = {
  normal: "Normal period",
  onsite: "Onsite period",
};

const WEEKDAY_LABELS = { 1: "Mon", 2: "Tue", 3: "Wed", 4: "Thu", 5: "Fri", 6: "Sat", 7: "Sun" };

const valueConfigRows = computed(() => {
  const r = rule.value;
  const cfg = r?.value_config;
  if (!cfg || typeof cfg !== "object") return [];

  switch (r.value_type) {
    case "buy_x_get_y":
      return [
        { label: "Buy quantity", value: cfg.buy_qty ?? "-", mono: true },
        { label: "Get free", value: cfg.get_free_qty ?? "-", mono: true },
      ];
    case "tiered_percentage":
    case "tiered_fixed_amount":
      return [
        { label: "Metric", value: cfg.metric === "amount" ? "By amount" : "By quantity" },
        {
          label: "Tiers",
          value: Array.isArray(cfg.tiers) && cfg.tiers.length
            ? cfg.tiers
                .map(
                  (t) =>
                    `≥ ${t.min} → ${r.value_type === "tiered_percentage" ? `${t.value}%` : `Rp${formatRupiah(t.value)}`}`,
                )
                .join("; ")
            : "-",
        },
      ];
    case "bundle_price":
      return [
        { label: "Bundle quantity", value: cfg.bundle_qty ?? "-", mono: true },
        { label: "Bundle price", value: cfg.bundle_price !== undefined ? `Rp${formatRupiah(cfg.bundle_price)}` : "-", mono: true },
      ];
    case "free_addon":
      return [
        { label: "Max free units", value: cfg.max_free_qty ?? "Unlimited", mono: true },
      ];
    default:
      return [];
  }
});

const applicabilityRows = computed(() => {
  const a = rule.value?.applicability;
  if (!a || typeof a !== "object") return [];
  const rows = [];

  if (Array.isArray(a.events) && a.events.length) {
    rows.push({ label: "Event IDs", value: a.events.join(", "), mono: true });
  }
  if (Array.isArray(a.hotels) && a.hotels.length) {
    rows.push({ label: "Hotel IDs", value: a.hotels.join(", "), mono: true });
  }
  if (Array.isArray(a.room_types) && a.room_types.length) {
    rows.push({ label: "Room Type IDs", value: a.room_types.join(", "), mono: true });
  }
  if (a.min_nights) rows.push({ label: "Min Nights", value: a.min_nights, mono: true });
  if (a.min_qty) rows.push({ label: "Min Quantity", value: a.min_qty, mono: true });
  if (Array.isArray(a.guest_email_domains) && a.guest_email_domains.length) {
    rows.push({ label: "Email Domains", value: a.guest_email_domains.join(", ") });
  }
  if (Array.isArray(a.weekdays) && a.weekdays.length) {
    rows.push({
      label: "Valid Weekdays",
      value: a.weekdays.map((d) => WEEKDAY_LABELS[d] || d).join(", "),
    });
  }
  if (a.first_purchase_only) rows.push({ label: "First Purchase Only", value: "Yes" });

  return rows;
});

const triggerConfigRows = computed(() => {
  const r = rule.value;
  const cfg = r?.trigger_config;
  if (!cfg || typeof cfg !== "object") return [];
  switch (r.trigger_type) {
    case "booking_window":
      return [{ label: "Window", value: PHASE_LABELS[cfg.window] || cfg.window || "-" }];
    case "event_period":
      return [{ label: "Phase", value: PHASE_LABELS[cfg.phase] || cfg.phase || "-" }];
    case "date_range":
      return [
        { label: "Trigger Starts", value: formatDate(cfg.start) },
        { label: "Trigger Ends", value: formatDate(cfg.end) },
      ];
    case "lead_time":
      return [
        { label: "Max Days Before Check-In", value: cfg.max_days ?? "-", mono: true },
        { label: "Operator", value: OPERATOR_LABELS[cfg.operator] || cfg.operator || "-" },
      ];
    case "cancellation_window":
      return [
        { label: "Min Days Before Check-In", value: cfg.min_days ?? "-", mono: true },
        { label: "Operator", value: OPERATOR_LABELS[cfg.operator] || cfg.operator || "-" },
      ];
    default:
      return [];
  }
});

const deleteDialogOpen = ref(false);
const deleting = ref(false);

async function handleDelete() {
  deleting.value = true;
  try {
    await client(`/api/promotion-rules/${route.params.ulid}`, { method: "DELETE" });
    toast.success("Rule deleted");
    await navigateTo("/promotion-rules");
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message });
  } finally {
    deleting.value = false;
  }
}

const bulkDialogOpen = ref(false);
const bulkGenerating = ref(false);
const bulkForm = ref({
  quantity: 10,
  length: 8,
  prefix: "",
  usage_limit: 1,
  usage_limit_per_email: 1,
});

async function handleBulkGenerate() {
  bulkGenerating.value = true;
  try {
    const res = await client(`/api/promotion-rules/${route.params.ulid}/codes/bulk`, {
      method: "POST",
      body: bulkForm.value,
    });
    toast.success(res?.message || "Codes generated");
    bulkDialogOpen.value = false;
    await refresh();
    await fetchReport();
  } catch (err) {
    toast.error(err?.response?._data?.message || "Failed to generate codes");
  } finally {
    bulkGenerating.value = false;
  }
}
</script>
