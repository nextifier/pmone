<template>
  <DialogResponsive v-model:open="openModel" dialog-max-width="520px">
    <template #default>
      <div class="space-y-4 px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h2 class="text-lg font-semibold tracking-tighter">Redemptions</h2>
          <p v-if="code" class="text-muted-foreground text-sm tracking-tight">
            <span class="font-mono">{{ code.code }}</span>
            · {{ code.used_count }} / {{ code.max_uses ?? "∞" }} used
          </p>
        </div>

        <div v-if="pending" class="flex justify-center py-8">
          <Spinner class="size-5" />
        </div>

        <div v-else-if="!rows.length" class="text-muted-foreground py-8 text-center text-sm tracking-tight">
          No redemptions yet.
        </div>

        <div v-else class="divide-y rounded-lg border">
          <div v-for="r in rows" :key="r.id" class="flex items-center justify-between gap-3 px-3 py-2.5">
            <div class="min-w-0">
              <div class="truncate text-sm tracking-tight">{{ r.email || "—" }}</div>
              <div class="text-muted-foreground truncate text-xs tracking-tight">
                {{ r.order?.order_number || "No order" }}
              </div>
            </div>
            <Badge :variant="statusVariant(r.status)" plain>{{ statusLabel(r.status) }}</Badge>
          </div>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Spinner } from "@/components/ui/spinner";
import { computed, ref, watch } from "vue";

const props = defineProps({
  open: { type: Boolean, default: false },
  event: { type: Object, required: true },
  code: { type: Object, default: null },
});
const emit = defineEmits(["update:open"]);

const openModel = computed({
  get: () => props.open,
  set: (v) => emit("update:open", v),
});

const client = useSanctumClient();
const rows = ref([]);
const pending = ref(false);

watch(openModel, async (open) => {
  if (open && props.code) {
    pending.value = true;
    rows.value = [];
    try {
      const res = await client(`/api/events/${props.event.id}/access-codes/${props.code.ulid}/redemptions`);
      rows.value = res?.data ?? [];
    } catch {
      rows.value = [];
    } finally {
      pending.value = false;
    }
  }
});

const statusLabel = (s) => ({ held: "Held", confirmed: "Confirmed", released: "Released" }[s] || s);
const statusVariant = (s) => ({ held: "warning", confirmed: "success", released: "muted" }[s] || "muted");
</script>
