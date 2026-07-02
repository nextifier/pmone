<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="440px">
    <template #default>
      <div class="space-y-6 px-4 pt-2 pb-8 md:px-6 md:py-4">
        <div class="space-y-1">
          <h2 class="text-lg font-semibold tracking-tighter">Personalize Ticket</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Update the details printed on this ticket. The name is shown to event staff at
            check-in.
          </p>
        </div>

        <div
          v-if="attendee?.is_checked_in"
          class="bg-warning/10 border-warning/20 text-warning-foreground flex items-start gap-x-2 rounded-lg border p-3 text-sm tracking-tight"
        >
          <Icon name="hugeicons:checkmark-badge-01" class="mt-0.5 size-4 shrink-0" />
          <span>This ticket is already checked in and can no longer be edited.</span>
        </div>

        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="attendee-name">Full Name</Label>
            <Input
              id="attendee-name"
              v-model="form.name"
              type="text"
              required
              :disabled="attendee?.is_checked_in"
              autocomplete="off"
            />
            <FieldError :errors="errors.name" />
          </div>

          <div class="space-y-2">
            <Label for="attendee-email">Email</Label>
            <Input
              id="attendee-email"
              v-model="form.email"
              type="email"
              :disabled="attendee?.is_checked_in"
              autocomplete="off"
            />
            <FieldError :errors="errors.email" />
          </div>

          <div class="space-y-2">
            <Label for="attendee-phone">Phone</Label>
            <div
              :aria-disabled="attendee?.is_checked_in || undefined"
              :class="attendee?.is_checked_in ? 'pointer-events-none opacity-50' : ''"
            >
              <InputPhone id="attendee-phone" v-model="form.phone" />
            </div>
            <FieldError :errors="errors.phone" />
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="outline" :disabled="saving" @click="isOpen = false">Cancel</Button>
          <Button :disabled="saving || attendee?.is_checked_in" @click="handleSave">
            <Spinner v-if="saving" />
            <span>Save</span>
          </Button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import { FieldError } from "@/components/ui/field";
import { toast } from "vue-sonner";

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  attendee: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:open", "saved"]);

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit("update:open", value),
});

const client = useSanctumClient();

const form = reactive({
  name: "",
  email: "",
  phone: "",
});
const errors = ref({});
const saving = ref(false);

watch(
  () => props.attendee,
  (attendee) => {
    form.name = attendee?.name || "";
    form.email = attendee?.email || "";
    form.phone = attendee?.phone || "";
    errors.value = {};
  },
  { immediate: true }
);

const handleSave = async () => {
  if (!props.attendee?.ulid) return;

  saving.value = true;
  errors.value = {};

  try {
    const response = await client(`/api/my/attendees/${props.attendee.ulid}`, {
      method: "PATCH",
      body: {
        name: form.name,
        email: form.email || null,
        phone: form.phone || null,
      },
    });

    toast.success(response?.message || "Ticket updated");
    emit("saved", response?.data || null);
    isOpen.value = false;
  } catch (err) {
    const status = err?.response?.status || err?.statusCode;
    const message = err?.response?._data?.message || err?.data?.message;

    if (status === 422 && err?.response?._data?.errors) {
      errors.value = err.response._data.errors;
    }

    toast.error(message || "Failed to update ticket");
  } finally {
    saving.value = false;
  }
};
</script>
