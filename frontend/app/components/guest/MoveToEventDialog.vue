<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";

const props = defineProps({
  username: { type: String, required: true },
  currentEventId: { type: [Number, String], required: true },
  count: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["submit", "cancel"]);

const client = useSanctumClient();
const { t } = useI18n();

const events = ref([]);
const fetching = ref(true);
const selected = ref("");

const fetchEvents = async () => {
  fetching.value = true;
  try {
    const response = await client(`/api/projects/${props.username}/events?per_page=100`);
    events.value = (response.data ?? []).filter(
      (ev) => Number(ev.id) !== Number(props.currentEventId)
    );
  } catch {
    events.value = [];
  } finally {
    fetching.value = false;
  }
};

onMounted(fetchEvents);

const handleSubmit = () => {
  if (!selected.value) return;
  emit("submit", Number(selected.value));
};
</script>

<template>
  <div class="space-y-4">
    <div>
      <h3 class="text-lg font-semibold tracking-tight">{{ $t("guests.moveToEvent") }}</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        {{ $t("guests.moveDescription", { count }) }}
      </p>
    </div>

    <div class="space-y-2">
      <Label>{{ $t("guests.selectTargetEvent") }}</Label>
      <Spinner v-if="fetching" class="size-5" />
      <p
        v-else-if="!events.length"
        class="text-muted-foreground text-sm tracking-tight"
      >
        {{ $t("guests.noOtherEvents") }}
      </p>
      <Select v-else v-model="selected">
        <SelectTrigger>
          <SelectValue :placeholder="$t('guests.selectTargetEvent')" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="ev in events" :key="ev.id" :value="String(ev.id)">
            {{ ev.title }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="flex justify-end gap-2">
      <button
        type="button"
        :disabled="loading"
        class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm tracking-tight disabled:opacity-50"
        @click="emit('cancel')"
      >
        {{ $t("guests.cancel") }}
      </button>
      <button
        type="button"
        :disabled="!selected || loading"
        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight disabled:opacity-50"
        @click="handleSubmit"
      >
        <Icon v-if="loading" name="svg-spinners:ring-resize" class="size-4" />
        {{ $t("guests.moveTo") }}
      </button>
    </div>
  </div>
</template>
