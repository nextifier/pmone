<template>
  <DialogResponsive v-model:open="openModel" dialog-max-width="32rem" :overflow-content="true">
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h3 class="text-lg font-semibold tracking-tighter">Field library</h3>
          <p class="text-muted-foreground text-sm tracking-tight">
            Toggle common fields on. Labels come translated in all five languages.
          </p>
        </div>

        <div v-if="pending" class="mt-4 space-y-2">
          <Skeleton v-for="n in 4" :key="n" class="h-14 w-full rounded-xl" />
        </div>

        <Empty v-else-if="!items.length" class="mt-4 border border-dashed p-6 md:p-12">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Icon name="hugeicons:library" />
            </EmptyMedia>
            <EmptyTitle>No library fields</EmptyTitle>
            <EmptyDescription>There are no predefined fields for this section yet.</EmptyDescription>
          </EmptyHeader>
        </Empty>

        <div v-else class="mt-4 space-y-2">
          <div
            v-for="item in items"
            :key="item.system_key"
            class="bg-card flex items-center gap-x-3 rounded-xl border px-3 py-3"
          >
            <div
              class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
            >
              <Icon :name="getTypeIcon(item.type)" class="size-4" />
            </div>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium tracking-tight">{{ labelFor(item) }}</p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ getTypeLabel(item.type) }}
              </p>
            </div>

            <Switch
              :model-value="item.enabled"
              :disabled="savingKey === item.system_key"
              @update:model-value="(next) => toggle(item, next)"
            />
          </div>
        </div>

        <div class="mt-4 flex justify-end">
          <Button variant="outline" type="button" @click="openModel = false">Done</Button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { getTypeIcon, getTypeLabel, localizedLabel } from "@/lib/formFieldTypes";
import { computed, ref, watch } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  open: { type: Boolean, default: false },
  eventId: { type: [Number, String], required: true },
  context: { type: String, required: true },
});
const emit = defineEmits(["update:open", "changed"]);

const openModel = computed({
  get: () => props.open,
  set: (v) => emit("update:open", v),
});

const client = useSanctumClient();

const items = ref([]);
const pending = ref(false);
const savingKey = ref(null);
const loaded = ref(false);

const baseUrl = computed(() => `/api/events/${props.eventId}/custom-fields/predefined`);

const labelFor = (item) =>
  localizedLabel(item.label_translations, "en") || item.system_key;

async function load() {
  pending.value = true;
  try {
    const res = await client(`${baseUrl.value}?context=${props.context}`);
    items.value = res?.data ?? [];
    loaded.value = true;
  } catch {
    items.value = [];
  } finally {
    pending.value = false;
  }
}

watch(
  () => props.open,
  (open) => {
    if (open && !loaded.value) load();
  }
);

async function toggle(item, next) {
  savingKey.value = item.system_key;
  const previous = item.enabled;
  item.enabled = next;
  try {
    await client(`${baseUrl.value}/${item.system_key}`, {
      method: "PUT",
      body: { context: props.context, enabled: next },
    });
    toast.success(next ? "Field enabled" : "Field disabled");
    emit("changed");
  } catch (err) {
    item.enabled = previous;
    toast.error("Update failed", { description: err?.data?.message || err?.message });
  } finally {
    savingKey.value = null;
  }
}
</script>
