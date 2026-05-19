<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/promo-codes" />

      <div v-if="code" class="flex flex-col">
        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2">
          <h1 class="page-title font-mono">{{ code.code }}</h1>
          <ButtonCopy :text="code.code" />
          <span
            :class="[
              'inline-flex items-center rounded-full px-2 py-0.5 text-xs sm:text-sm tracking-tight',
              !code.is_active
                ? 'bg-muted text-muted-foreground'
                : code.is_exhausted
                  ? 'bg-warning/15 text-warning-foreground'
                  : 'bg-success/15 text-success-foreground',
            ]"
          >
            {{ !code.is_active ? "Inactive" : code.is_exhausted ? "Exhausted" : "Active" }}
          </span>
        </div>
        <NuxtLink
          v-if="code.promotion_rule"
          :to="`/promotion-rules/${code.promotion_rule.ulid}/show`"
          class="page-description mt-1.5 hover:underline"
        >
          From rule: {{ code.promotion_rule.name }}
        </NuxtLink>
      </div>
    </div>

    <div v-if="pending" class="space-y-3">
      <Skeleton class="h-32 w-full rounded-md" />
    </div>

    <div v-else-if="code" class="space-y-6">
      <div class="flex flex-wrap gap-2">
        <Button v-if="canEdit" as-child variant="outline" size="sm">
          <NuxtLink :to="`/promo-codes/${route.params.ulid}/edit`">
            <Icon name="lucide:pencil" class="size-4 shrink-0" />
            Edit
          </NuxtLink>
        </Button>
        <Button v-if="canDelete" variant="destructive" size="sm" @click="deleteDialogOpen = true">
          <Icon name="lucide:trash" class="size-4 shrink-0" />
          Delete
        </Button>
      </div>

      <!-- Usage Stats -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-md border p-4">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Times Used</p>
          <p class="mt-1 text-xl font-medium tabular-nums tracking-tighter">
            {{ code.usage_count }} / {{ code.usage_limit ?? "∞" }}
          </p>
        </div>
        <div class="rounded-md border p-4">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Per Email Limit</p>
          <p class="mt-1 text-xl font-medium tabular-nums tracking-tighter">
            {{ code.usage_limit_per_email ?? "∞" }}
          </p>
        </div>
        <div class="rounded-md border p-4">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Valid Until</p>
          <p class="mt-1 text-xl font-medium tracking-tighter">
            {{ code.valid_until ? formatDate(code.valid_until) : "No expiry" }}
          </p>
        </div>
      </div>

      <!-- Code Detail -->
      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tighter">Code Detail</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Issued To Email</p>
            <p>{{ code.issued_to_email ?? "Public (any email)" }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Valid From</p>
            <p>{{ formatDate(code.valid_from) }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Created</p>
            <p>{{ formatDate(code.created_at) }}</p>
          </div>
          <div v-if="code.event">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Event Scope</p>
            <p>{{ code.event.title }}</p>
          </div>
        </div>
      </div>

      <!-- Usage Ledger -->
      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tighter">Usage History</h2>
        <div v-if="usagesLoading" class="space-y-2">
          <Skeleton class="h-10 w-full rounded-md" />
        </div>
        <Empty v-else-if="usages.length === 0" class="border-none p-2 md:p-6">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Icon name="hugeicons:clock-04" />
            </EmptyMedia>
            <EmptyTitle>No usages yet</EmptyTitle>
            <EmptyDescription>
              When customers redeem this code, their usage history will appear here.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>
        <div v-else class="space-y-2">
          <div
            v-for="usage in usages"
            :key="usage.id"
            class="flex items-center justify-between gap-3 border-b pb-2 last:border-b-0 last:pb-0"
          >
            <div class="min-w-0">
              <p class="text-sm tracking-tight truncate">{{ usage.email }}</p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ adjustableLabel(usage.adjustable_type) }} #{{ usage.adjustable_id }}
                · {{ formatDate(usage.created_at) }}
              </p>
            </div>
            <div class="text-right">
              <p class="text-sm tracking-tight tabular-nums font-medium">
                -Rp{{ formatRupiah(usage.amount_discounted) }}
              </p>
              <p v-if="usage.voided_at" class="text-destructive text-xs tracking-tight">Voided</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <DialogResponsive v-model:open="deleteDialogOpen" dialog-max-width="28rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">Delete Promo Code</h3>
          <p class="text-muted-foreground text-sm tracking-tight mt-2">
            Soft-delete this code. Existing usages remain in history.
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
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import ButtonCopy from "@/components/ui/button-copy/ButtonCopy.vue";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promo_codes.read"],
  layout: "app",
});

const route = useRoute();
const client = useSanctumClient();
const { hasPermission } = usePermission();

const canDelete = computed(() => hasPermission("promo_codes.delete"));
const canEdit = computed(() => hasPermission("promo_codes.update"));

const {
  data: response,
  pending,
} = await useLazySanctumFetch(() => `/api/promo-codes/${route.params.ulid}`, {
  key: () => `promo-code-${route.params.ulid}`,
});

const code = computed(() => response.value?.data);

usePageMeta(null, {
  title: computed(() => (code.value ? `${code.value.code} · Promo Codes` : "Promo Code")),
});

const usages = ref([]);
const usagesLoading = ref(false);

async function fetchUsages() {
  usagesLoading.value = true;
  try {
    const res = await client(`/api/promo-codes/${route.params.ulid}/usages?per_page=50`);
    usages.value = res?.data ?? [];
  } catch (e) {
    // ignore - permission may be missing
  } finally {
    usagesLoading.value = false;
  }
}
onMounted(fetchUsages);

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
const formatDate = (iso) => (iso ? new Date(iso).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" }) : "-");
const adjustableLabel = (cls) => (cls ? cls.split("\\").pop() : "");

const deleteDialogOpen = ref(false);
const deleting = ref(false);

async function handleDelete() {
  deleting.value = true;
  try {
    await client(`/api/promo-codes/${route.params.ulid}`, { method: "DELETE" });
    toast.success("Code deleted");
    await navigateTo("/promo-codes");
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message });
  } finally {
    deleting.value = false;
  }
}
</script>
