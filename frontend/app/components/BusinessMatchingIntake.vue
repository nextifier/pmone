<template>
  <div class="bg-card rounded-xl border p-4 sm:p-5">
    <div class="flex items-start justify-between gap-x-3">
      <div class="min-w-0 space-y-0.5">
        <p class="text-sm font-medium tracking-tight">{{ eventTitle }}</p>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Share your details so other attendees can connect with you.
        </p>
      </div>
      <Switch v-model="optIn" :disabled="saving" aria-label="Opt in to Business Matching" />
    </div>

    <!-- Loading fields -->
    <div v-if="pending" class="mt-4 space-y-4">
      <Skeleton v-for="i in 2" :key="i" class="h-14 w-full rounded-md" />
    </div>

    <!-- Fields (only when opted in) -->
    <template v-else-if="optIn">
      <div v-if="fields.length" class="mt-5 space-y-6 border-t pt-5">
        <CustomFieldRenderer
          v-for="(field, index) in fields"
          :key="field.id"
          :field="field"
          :is-first="index === 0"
          :model-value="responses[field.id]"
          @update:model-value="responses[field.id] = $event"
        />

        <div class="flex justify-end">
          <Button :disabled="saving" @click="save">
            <Spinner v-if="saving" />
            <span>Save answers</span>
          </Button>
        </div>
      </div>

      <p v-else class="text-muted-foreground mt-4 text-sm tracking-tight">
        There are no Business Matching questions for this event yet.
      </p>
    </template>
  </div>
</template>

<script setup>
import { CustomFieldRenderer, normalizeStoredValue } from "@/components/ui/custom-field";
import { toast } from "vue-sonner";

const props = defineProps({
  eventId: {
    type: [Number, String],
    required: true,
  },
  eventTitle: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["opt-in-change"]);

const client = useSanctumClient();

const pending = ref(true);
const saving = ref(false);
const fields = ref([]);
const responses = reactive({});
const optIn = ref(false);

const fetchFields = async () => {
  pending.value = true;
  try {
    const response = await client(`/api/my/events/${props.eventId}/field-responses`);
    const data = response?.data || {};
    fields.value = data.fields || [];
    optIn.value = !!data.opt_in;
    const byId = new Map(fields.value.map((field) => [String(field.id), field]));
    Object.keys(responses).forEach((key) => delete responses[key]);
    Object.entries(data.responses || {}).forEach(([fieldId, value]) => {
      const field = byId.get(String(fieldId));
      // Stored answers are [value]-wrapped; unwrap per the field's type.
      responses[fieldId] = field ? normalizeStoredValue(field, value) : value;
    });
  } catch (err) {
    console.error("Failed to load business matching fields:", err);
  } finally {
    pending.value = false;
  }
};

const save = async () => {
  saving.value = true;
  try {
    const payload = {
      opt_in: optIn.value,
      responses: fields.value.map((field) => ({
        custom_field_id: field.id,
        value: responses[field.id] ?? null,
      })),
    };
    const response = await client(`/api/my/events/${props.eventId}/field-responses`, {
      method: "PUT",
      body: payload,
    });
    toast.success(response?.message || "Business Matching answers saved");
    emit("opt-in-change", optIn.value);
  } catch (err) {
    const message = err?.response?._data?.message || err?.data?.message;
    toast.error(message || "Failed to save answers");
  } finally {
    saving.value = false;
  }
};

watch(optIn, (value, oldValue) => {
  if (oldValue === undefined) return;
  emit("opt-in-change", value);
});

onMounted(fetchFields);
</script>
