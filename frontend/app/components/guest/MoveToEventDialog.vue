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
      <h3 class="text-lg font-semibold tracking-tight">Move to event</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        Move {{ count }} guest(s) to another event in this project.
      </p>
    </div>

    <div class="space-y-2">
      <Label>Select target event</Label>
      <Spinner v-if="fetching" class="size-5" />
      <p v-else-if="!events.length" class="text-muted-foreground text-sm tracking-tight">
        No other events in this project.
      </p>
      <Select v-else v-model="selected">
        <SelectTrigger>
          <SelectValue placeholder="Select target event" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="ev in events" :key="ev.id" :value="String(ev.id)">
            {{ ev.title }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="flex justify-end gap-2">
      <Button variant="outline" size="sm" :disabled="loading" @click="emit('cancel')">
        Cancel
      </Button>
      <Button size="sm" :disabled="!selected || loading" @click="handleSubmit">
        <Icon v-if="loading" name="svg-spinners:ring-resize" class="size-4" />
        Move
      </Button>
    </div>
  </div>
</template>
