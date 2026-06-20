<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/promo-codes" force-destination />
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Trashed Promo Codes</h1>
      </div>
    </div>

    <div v-if="pending" class="space-y-3">
      <Skeleton class="h-16 w-full rounded-md" />
      <Skeleton class="h-16 w-full rounded-md" />
    </div>

    <Empty v-else-if="data.length === 0" class="border">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:delete-01" />
        </EmptyMedia>
        <EmptyTitle>Trash is empty</EmptyTitle>
        <EmptyDescription>
          Deleted promo codes will appear here so you can restore them.
        </EmptyDescription>
      </EmptyHeader>
    </Empty>

    <ul v-else class="space-y-2">
      <li
        v-for="code in data"
        :key="code.id"
        class="hover:bg-muted/30 flex items-center justify-between rounded-md border p-4 transition-colors"
      >
        <div class="min-w-0">
          <p class="truncate font-mono text-sm tracking-tight">{{ code.code }}</p>
          <p class="text-muted-foreground mt-0.5 text-xs tracking-tight sm:text-sm">
            {{ code.promotion_rule?.name || "No rule" }} · deleted
            {{ formatRelative(code.deleted_at) }}
          </p>
        </div>
        <Button size="sm" variant="outline" @click="handleRestore(code)">
          <Icon name="lucide:undo-2" class="size-4 shrink-0" />
          Restore
        </Button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promo_codes.delete"],
  layout: "app",
});

usePageMeta(null, { title: "Trash · Promo Codes" });

const client = useSanctumClient();

const {
  data: response,
  pending,
  refresh,
} = await useLazySanctumFetch("/api/promo-codes/trash", { key: "promo-codes-trash" });

const data = computed(() => response.value?.data ?? []);

function formatRelative(iso) {
  if (!iso) return "";
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return "just now";
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

async function handleRestore(code) {
  try {
    await client(`/api/promo-codes/${code.ulid}/restore`, { method: "POST" });
    toast.success("Promo code restored");
    await refresh();
  } catch (err) {
    toast.error("Restore failed", { description: err?.data?.message });
  }
}
</script>
