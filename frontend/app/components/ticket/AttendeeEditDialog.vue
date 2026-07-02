<template>
  <DialogResponsive v-model:open="openModel" dialog-max-width="560px">
    <template #default>
      <div class="space-y-5 px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h2 class="text-lg font-semibold tracking-tighter">Edit attendee</h2>
          <p v-if="attendee?.order?.number" class="text-muted-foreground text-sm tracking-tight">
            {{ attendee.ticket?.title }} · Order {{ attendee.order.number }}
          </p>
        </div>

        <form class="space-y-4" @submit.prevent="save">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="space-y-2 sm:col-span-2">
              <Label for="att-name">Name</Label>
              <Input id="att-name" v-model="form.name" placeholder="Attendee name" />
              <FieldError :errors="errors.name" />
            </div>
            <div class="space-y-2">
              <Label for="att-email">Email</Label>
              <Input id="att-email" v-model="form.email" type="email" placeholder="name@example.com" />
              <FieldError :errors="errors.email" />
            </div>
            <div class="space-y-2">
              <Label for="att-phone">Phone</Label>
              <InputPhone id="att-phone" v-model="form.phone" />
              <FieldError :errors="errors.phone" />
            </div>
          </div>

          <div v-if="hasSessions" class="space-y-2">
            <Label for="att-session">Session</Label>
            <Select
              :model-value="form.ticket_session_id ? String(form.ticket_session_id) : ''"
              @update:model-value="(v) => (form.ticket_session_id = Number(v))"
            >
              <SelectTrigger id="att-session" class="w-full"><SelectValue placeholder="Choose a session" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="s in sessions" :key="s.id" :value="String(s.id)">{{ s.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div v-if="needsDay" class="space-y-2">
              <Label for="att-day">Day</Label>
              <Select
                :model-value="form.selected_event_day_id ? String(form.selected_event_day_id) : ''"
                @update:model-value="(v) => (form.selected_event_day_id = v ? Number(v) : null)"
              >
                <SelectTrigger id="att-day" class="w-full"><SelectValue placeholder="Choose a day" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="d in validDays" :key="d.id" :value="String(d.id)">{{ dayLabel(d) }}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="att-checked-in">Check-in</Label>
              <Button
                id="att-checked-in"
                type="button"
                :variant="form.checked_in ? 'outline' : 'default'"
                class="w-full justify-center"
                @click="form.checked_in = !form.checked_in"
              >
                <Icon
                  :name="form.checked_in ? 'hugeicons:cancel-circle' : 'hugeicons:checkmark-circle-02'"
                  class="size-4 shrink-0"
                />
                <span>{{ form.checked_in ? "Mark as not checked in" : "Mark as checked in" }}</span>
              </Button>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t pt-4">
            <Button type="button" variant="outline" :disabled="saving" @click="openModel = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="saving">
              <Spinner v-if="saving" class="size-4" />
              <span>Save changes</span>
            </Button>
          </div>
        </form>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Input } from "@/components/ui/input";
import { FieldError } from "@/components/ui/field";
import { InputPhone } from "@/components/ui/input-phone";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  open: { type: Boolean, default: false },
  event: { type: Object, required: true },
  attendee: { type: Object, default: null },
});
const emit = defineEmits(["update:open", "saved"]);

const openModel = computed({
  get: () => props.open,
  set: (v) => emit("update:open", v),
});

const client = useSanctumClient();

const ticketDetail = ref(null);
const validDays = ref([]);
const sessions = ref([]);
const errors = ref({});
const saving = ref(false);

const form = reactive({
  name: "",
  email: "",
  phone: "",
  selected_event_day_id: null,
  ticket_session_id: null,
  checked_in: false,
});

const needsDay = computed(() => !!ticketDetail.value?.requires_day_selection && validDays.value.length > 0);
const hasSessions = computed(() => sessions.value.length > 0);

watch(
  () => [props.open, props.attendee?.id],
  async ([open]) => {
    if (!open || !props.attendee) return;
    errors.value = {};
    Object.assign(form, {
      name: props.attendee.name ?? "",
      email: props.attendee.email ?? "",
      phone: props.attendee.phone ?? "",
      selected_event_day_id: props.attendee.day?.id ?? null,
      ticket_session_id: props.attendee.session?.id ?? null,
      checked_in: !!props.attendee.is_checked_in,
    });
    await loadTicket();
  }
);

async function loadTicket() {
  validDays.value = [];
  sessions.value = [];
  ticketDetail.value = null;
  const slug = props.attendee?.ticket?.slug;
  if (!slug) return;
  try {
    const res = await client(`/api/events/${props.event.id}/tickets/${slug}`);
    ticketDetail.value = res?.data ?? null;
    validDays.value = ticketDetail.value?.valid_days ?? [];
    sessions.value = (ticketDetail.value?.sessions ?? []).filter((s) => s.is_active !== false);
  } catch {
    ticketDetail.value = null;
  }
}

function dayLabel(d) {
  const label = d.label;
  let text;
  if (label && typeof label === "object") {
    text = label.en || label.id || Object.values(label)[0] || `Day ${d.day_number}`;
  } else {
    text = label || (d.day_number ? `Day ${d.day_number}` : "");
  }
  return appendDayDate(text, d?.date);
}

async function save() {
  saving.value = true;
  errors.value = {};

  const body = {
    name: form.name,
    email: form.email || null,
    phone: form.phone || null,
    checked_in: form.checked_in,
  };
  if (needsDay.value) body.selected_event_day_id = form.selected_event_day_id;
  if (hasSessions.value) body.ticket_session_id = form.ticket_session_id;

  try {
    await client(`/api/events/${props.event.id}/attendees/${props.attendee.id}`, {
      method: "PATCH",
      body,
    });
    toast.success("Attendee updated");
    emit("saved");
    openModel.value = false;
  } catch (err) {
    errors.value = err?.data?.errors || {};
    toast.error(err?.data?.message || "Could not update attendee.");
  } finally {
    saving.value = false;
  }
}
</script>
