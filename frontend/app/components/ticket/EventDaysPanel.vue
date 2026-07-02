<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-2">
      <p class="text-muted-foreground text-sm tracking-tight">
        Each day of the event, in order. Entry tickets reference these to limit which days they
        admit.
      </p>
      <Button v-if="canCreate" size="sm" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add Day
      </Button>
    </div>

    <div v-if="error" class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight">
      Event days are available once ticketing is enabled. Enable it in the Settings tab.
    </div>

    <div v-else-if="pending" class="flex justify-center py-6">
      <Spinner class="size-5" />
    </div>

    <div
      v-else-if="!days.length"
      class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
    >
      No event days yet. Add the first day to get started.
    </div>

    <div v-else ref="listContainer" class="space-y-2">
      <div
        v-for="day in days"
        :key="day.id"
        :data-item-id="day.id"
        class="bg-card flex items-center gap-x-3 rounded-xl border px-3 py-3"
      >
        <Icon
          name="lucide:grip-vertical"
          class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
        />

        <div
          class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-medium tabular-nums"
        >
          {{ day.day_number }}
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-sm font-medium tracking-tight">{{ dayLabel(day) }}</span>
            <span
              v-if="!day.is_active"
              class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-xs tracking-tight"
            >
              Hidden
            </span>
          </div>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ day.date }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <Button variant="ghost" size="iconSm" v-tippy="'Edit'" @click="openEditDialog(day)">
            <Icon name="hugeicons:edit-02" class="size-4" />
          </Button>
          <Button
            v-if="canDelete"
            variant="ghost"
            size="iconSm"
            class="hover:bg-destructive/10 text-destructive"
            v-tippy="'Delete'"
            @click="confirmDelete(day)"
          >
            <Icon name="hugeicons:delete-02" class="size-4" />
          </Button>
        </div>
      </div>
    </div>

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="30rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">
            {{ editing ? "Edit Event Day" : "Add Event Day" }}
          </h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="grid grid-cols-2 gap-x-2">
              <div class="space-y-2">
                <Label>Day number</Label>
                <InputNumber v-model="form.day_number" :min="1" required />
                <FieldError :errors="errors.day_number" />
              </div>
              <div class="space-y-2">
                <Label>Date</Label>
                <DatePicker
                  :model-value="form._date_obj"
                  placeholder="Pick date"
                  @update:model-value="(d) => (form._date_obj = d)"
                />
                <FieldError :errors="errors.date" />
              </div>
            </div>

            <div class="space-y-2">
              <Label>Label</Label>
              <Tabs v-model="activeLocale" variant="segmented">
                <TabsList>
                  <TabsIndicator />
                  <TabsTrigger v-for="locale in LOCALES" :key="locale.value" :value="locale.value">
                    {{ locale.label }}
                  </TabsTrigger>
                </TabsList>
              </Tabs>
              <Input
                v-model="labelField"
                :placeholder="activeLocale === 'en' ? 'e.g. Opening Day' : 'mis. Hari Pembukaan'"
              />
              <FieldError :errors="localizedLabelErrors" />
            </div>

            <div class="flex items-center gap-2">
              <Switch id="day-active" v-model="form.is_active" />
              <Label for="day-active" class="cursor-pointer">Active</Label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Spinner v-if="saving" />
                {{ editing ? "Save Changes" : "Create" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tighter">Delete event day?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem ? dayLabel(deletingItem) : "This day" }}" will be removed. Tickets that
            referenced it will no longer admit on that day.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">Cancel</Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
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
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Input } from "@/components/ui/input";
import { FieldError } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useSortableList } from "@/composables/useSortableList";
import { parseLocalDateString, toLocalDateString } from "@/lib/utils";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  event: { type: Object, required: true },
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("tickets.update"));
const canDelete = computed(() => hasPermission("tickets.update"));

const baseUrl = computed(() => `/api/events/${props.event.id}/event-days`);

const {
  data,
  pending,
  error,
  refresh,
} = await useLazySanctumFetch(() => baseUrl.value, {
  key: () => `event-days-${props.event.id}`,
});

const days = ref([]);
watch(
  data,
  (v) => {
    days.value = v?.data ?? [];
  },
  { immediate: true }
);

const LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

const EMPTY_TRANSLATABLE = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const activeLocale = ref("en");

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});

const form = reactive({
  day_number: 1,
  label: EMPTY_TRANSLATABLE(),
  is_active: true,
  _date_obj: null,
});

const labelField = computed({
  get: () => form.label[activeLocale.value] ?? "",
  set: (value) => {
    form.label = { ...form.label, [activeLocale.value]: value };
  },
});

const localizedLabelErrors = computed(
  () => errors.value[`label.${activeLocale.value}`] ?? errors.value.label ?? null
);

const dayLabel = (day) => {
  const l = day.label;
  const text = l && typeof l === "object" ? l.en ?? Object.values(l).find(Boolean) : l;
  return text || `Day ${day.day_number}`;
};

const resetForm = (nextDayNumber) => {
  Object.assign(form, {
    day_number: nextDayNumber ?? 1,
    label: EMPTY_TRANSLATABLE(),
    is_active: true,
    _date_obj: null,
  });
  errors.value = {};
  activeLocale.value = "en";
};

const openCreateDialog = () => {
  editing.value = null;
  const maxNumber = days.value.reduce((m, d) => Math.max(m, Number(d.day_number) || 0), 0);
  resetForm(maxNumber + 1);
  dialogOpen.value = true;
};

const openEditDialog = (day) => {
  editing.value = day;
  errors.value = {};
  activeLocale.value = "en";
  Object.assign(form, {
    day_number: day.day_number ?? 1,
    label: { ...EMPTY_TRANSLATABLE(), ...(day.label ?? {}) },
    is_active: day.is_active ?? true,
    _date_obj: parseLocalDateString(day.date),
  });
  dialogOpen.value = true;
};

function cleanTranslatable(t) {
  const out = {};
  for (const [k, v] of Object.entries(t ?? {})) {
    out[k] = v && String(v).trim().length > 0 ? v : null;
  }
  return out;
}

const handleSubmit = async () => {
  saving.value = true;
  errors.value = {};
  try {
    const payload = {
      day_number: form.day_number,
      date: form._date_obj ? toLocalDateString(form._date_obj) : null,
      label: cleanTranslatable(form.label),
      is_active: form.is_active,
    };

    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Event day updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Event day created");
    }
    dialogOpen.value = false;
    await refresh();
  } catch (err) {
    // Surface 422 unique errors (day_number / date already used for this event).
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error("Save failed", { description: err?.data?.message || err?.message });
  } finally {
    saving.value = false;
  }
};

const deleteDialogOpen = ref(false);
const deletingItem = ref(null);
const deleting = ref(false);

const confirmDelete = (day) => {
  deletingItem.value = day;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Event day deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

// --- Drag reorder ---
const listContainer = ref(null);
useSortableList(listContainer, days, {
  enabled: canCreate,
  onReorder: async () => {
    const orders = days.value.map((d, idx) => ({ id: d.id, order: idx + 1 }));
    try {
      await client(`${baseUrl.value}/reorder`, { method: "POST", body: { orders } });
      days.value.forEach((d, idx) => (d.order_column = idx + 1));
    } catch (err) {
      toast.error("Failed to reorder days");
      await refresh();
    }
  },
});
</script>
