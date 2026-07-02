<template>
  <DialogResponsive v-model:open="openModel" dialog-max-width="640px">
    <template #default>
      <div class="space-y-5 px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h2 class="text-lg font-semibold tracking-tighter">Bulk generate tickets</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Issue complimentary tickets in bulk (VIP, sponsors, press). Always free and outside the
            sale stock.
          </p>
        </div>

        <!-- Result / progress -->
        <div v-if="batch" class="space-y-4">
          <template v-if="batch.batch_status === 'completed'">
            <div
              class="bg-success/10 text-success-foreground flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm tracking-tight"
            >
              <Icon name="hugeicons:checkmark-circle-02" class="size-4 shrink-0" />
              Generated {{ batch.generated }} ticket(s).
            </div>
            <div class="flex flex-wrap gap-2">
              <Button type="button" variant="outline" size="sm" :disabled="downloading" @click="downloadCsv">
                <Icon name="hugeicons:download-04" class="size-4 shrink-0" />
                Download CSV
              </Button>
              <Button type="button" variant="outline" size="sm" :disabled="downloading" @click="downloadBadges">
                <Spinner v-if="downloading" class="size-4" />
                <Icon v-else name="hugeicons:id" class="size-4 shrink-0" />
                Download badge PDF
              </Button>
            </div>
            <div class="flex items-center justify-between gap-2 border-t pt-3">
              <Button type="button" variant="ghost" size="sm" @click="resetForm">Generate another</Button>
              <Button type="button" size="sm" @click="openModel = false">Done</Button>
            </div>
          </template>
          <template v-else-if="batch.batch_status === 'failed'">
            <div
              class="bg-destructive/10 text-destructive flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm tracking-tight"
            >
              <Icon name="hugeicons:alert-circle" class="size-4 shrink-0" />
              Generation failed. Please try again.
            </div>
            <Button type="button" variant="outline" size="sm" @click="resetForm">Back</Button>
          </template>
          <template v-else>
            <p class="text-muted-foreground text-sm tracking-tight">
              Generating {{ batch.generated }} of {{ batch.target }} tickets...
            </p>
            <Progress :model-value="progressPct" />
          </template>
        </div>

        <!-- Form -->
        <form v-else class="space-y-4" @submit.prevent="submit">
          <div class="space-y-2">
            <Label for="bulk-ticket">Ticket type</Label>
            <Select
              :model-value="form.ticket_id ? String(form.ticket_id) : ''"
              @update:model-value="onTicketChange"
            >
              <SelectTrigger id="bulk-ticket" class="w-full">
                <SelectValue placeholder="Select a ticket" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="t in tickets" :key="t.id" :value="String(t.id)">
                  {{ t.title }} <span class="text-muted-foreground">· {{ t.kind === "add_on" ? "Add-on" : "Entry" }}</span>
                </SelectItem>
              </SelectContent>
            </Select>
            <FieldError :errors="errors.ticket_id" />
          </div>

          <div v-if="needsSession" class="space-y-2">
            <Label for="bulk-session">Session</Label>
            <Select
              :model-value="form.ticket_session_id ? String(form.ticket_session_id) : ''"
              @update:model-value="(v) => (form.ticket_session_id = Number(v))"
            >
              <SelectTrigger id="bulk-session" class="w-full"><SelectValue placeholder="Choose a session" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="s in sessions" :key="s.id" :value="String(s.id)">{{ s.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="needsDay" class="space-y-2">
            <Label for="bulk-day">Day</Label>
            <Select
              :model-value="form.selected_event_day_id ? String(form.selected_event_day_id) : ''"
              @update:model-value="(v) => (form.selected_event_day_id = Number(v))"
            >
              <SelectTrigger id="bulk-day" class="w-full"><SelectValue placeholder="Choose a day" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="d in validDays" :key="d.id" :value="String(d.id)">{{ dayLabel(d) }}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <Tabs
            :model-value="form.mode"
            variant="segmented"
            @update:model-value="(v) => (form.mode = v)"
          >
            <TabsList class="grid w-full grid-cols-2">
              <TabsIndicator />
              <TabsTrigger value="anonymous">Anonymous</TabsTrigger>
              <TabsTrigger value="named">Named list</TabsTrigger>
            </TabsList>
          </Tabs>

          <div v-if="form.mode === 'anonymous'" class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="bulk-qty">Quantity</Label>
              <InputNumber id="bulk-qty" v-model="form.quantity" :min="1" :max="5000" />
            </div>
            <div class="space-y-2">
              <Label for="bulk-prefix">Name prefix</Label>
              <Input id="bulk-prefix" v-model="form.label_prefix" placeholder="Tamu" />
            </div>
          </div>

          <div v-else class="space-y-2">
            <Label for="bulk-recipients">Recipients</Label>
            <Textarea
              id="bulk-recipients"
              v-model="form.recipients_text"
              :rows="5"
              placeholder="One per line: Name, email&#10;Jane Doe, jane@example.com&#10;John Smith"
            />
            <div class="flex items-center gap-2 text-xs tracking-tight sm:text-sm">
              <span class="text-muted-foreground">{{ parsedRecipients.length }} recipient(s)</span>
              <span class="text-muted-foreground/60" aria-hidden="true">·</span>
              <Button
                type="button"
                variant="link"
                size="sm"
                class="h-auto p-0 text-xs sm:text-sm"
                @click="csvInput?.click()"
              >
                <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
                Import CSV
              </Button>
              <input
                ref="csvInput"
                type="file"
                accept=".csv,text/csv,text/plain"
                class="hidden"
                @change="onCsv"
              />
            </div>
          </div>

          <div class="space-y-2">
            <Label>Delivery</Label>
            <RadioGroup :model-value="form.delivery" @update:model-value="(v) => (form.delivery = v)" class="gap-2">
              <div class="flex items-center gap-2">
                <RadioGroupItem id="del-generate" value="generate_only" />
                <Label for="del-generate" class="font-normal">Generate only (share or export links myself)</Label>
              </div>
              <div class="flex items-center gap-2">
                <RadioGroupItem id="del-email" value="auto_email" :disabled="form.mode !== 'named'" />
                <Label for="del-email" class="font-normal" :class="{ 'opacity-50': form.mode !== 'named' }">
                  Auto-email each recipient
                </Label>
              </div>
            </RadioGroup>
            <p v-if="form.delivery === 'auto_email'" class="text-muted-foreground text-xs tracking-tight">
              Every recipient needs an email (named list only).
            </p>
            <FieldError :errors="errors.delivery" />
          </div>

          <div class="space-y-2">
            <Label for="bulk-label">Batch label (optional)</Label>
            <Input id="bulk-label" v-model="form.batch_label" placeholder="e.g. VIP invites - speakers" />
          </div>

          <div class="flex justify-end gap-2 border-t pt-4">
            <Button type="button" variant="outline" @click="openModel = false">Cancel</Button>
            <Button type="submit" :disabled="submitting || !canSubmit">
              <Spinner v-if="submitting" class="size-4" />
              Generate
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
import { InputNumber } from "@/components/ui/input-number";
import { FieldError } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";
import { useTicketBadgePdf } from "@/composables/useTicketBadgePdf";

const props = defineProps({
  open: { type: Boolean, default: false },
  event: { type: Object, required: true },
});
const emit = defineEmits(["update:open", "generated"]);

const openModel = computed({
  get: () => props.open,
  set: (v) => emit("update:open", v),
});

const client = useSanctumClient();
const { generate: generateBadges } = useTicketBadgePdf();

const base = computed(() => `/api/events/${props.event?.id}/tickets`);

const tickets = ref([]);
const selectedTicket = ref(null);
const sessions = ref([]);
const validDays = ref([]);

const form = reactive({
  ticket_id: null,
  ticket_session_id: null,
  selected_event_day_id: null,
  mode: "anonymous",
  quantity: 1,
  label_prefix: "",
  recipients_text: "",
  delivery: "generate_only",
  batch_label: "",
});

const errors = ref({});
const submitting = ref(false);
const downloading = ref(false);
const batch = ref(null);
const batchUlid = ref(null);
const csvInput = ref(null);
let pollTimer = null;

watch(openModel, async (open) => {
  if (open) {
    resetForm();
    await loadTickets();
  } else {
    stopPolling();
  }
});

async function loadTickets() {
  try {
    const res = await client(base.value);
    tickets.value = res?.data ?? [];
  } catch {
    tickets.value = [];
  }
}

async function onTicketChange(value) {
  form.ticket_id = Number(value);
  form.ticket_session_id = null;
  form.selected_event_day_id = null;
  sessions.value = [];
  validDays.value = [];
  selectedTicket.value = null;

  const picked = tickets.value.find((t) => t.id === form.ticket_id);
  if (!picked) return;

  try {
    // Tickets are route-bound by slug, not id.
    const res = await client(`${base.value}/${picked.slug}`);
    selectedTicket.value = res?.data ?? null;
    sessions.value = (selectedTicket.value?.sessions ?? []).filter((s) => s.is_active !== false);
    validDays.value = selectedTicket.value?.valid_days ?? [];
    if (validDays.value.length === 1) form.selected_event_day_id = validDays.value[0].id;
  } catch {
    selectedTicket.value = null;
  }
}

const needsSession = computed(
  () => selectedTicket.value?.kind === "add_on" && sessions.value.length > 1
);
const needsDay = computed(
  () => !!selectedTicket.value?.requires_day_selection && validDays.value.length > 0
);

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

const parsedRecipients = computed(() => {
  return form.recipients_text
    .split(/\r?\n/)
    .map((line) => {
      const [name, email] = line.split(",").map((s) => (s || "").trim());
      return name ? { name, email: email || null } : null;
    })
    .filter(Boolean);
});

const canSubmit = computed(() => {
  if (!form.ticket_id) return false;
  if (needsSession.value && !form.ticket_session_id) return false;
  if (needsDay.value && !form.selected_event_day_id) return false;
  if (form.mode === "anonymous") return Number(form.quantity) > 0;
  return parsedRecipients.value.length > 0;
});

const progressPct = computed(() => {
  if (!batch.value?.target) return 0;
  return Math.round((batch.value.generated / batch.value.target) * 100);
});

function onCsv(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    form.recipients_text = String(reader.result || "")
      .split(/\r?\n/)
      .map((line) => {
        const cols = line.split(",").map((s) => (s || "").trim());
        return cols[0] ? `${cols[0]}${cols[1] ? `, ${cols[1]}` : ""}` : "";
      })
      .filter(Boolean)
      .join("\n");
  };
  reader.readAsText(file);
  e.target.value = "";
}

function resetForm() {
  stopPolling();
  batch.value = null;
  batchUlid.value = null;
  errors.value = {};
  Object.assign(form, {
    ticket_id: null,
    ticket_session_id: null,
    selected_event_day_id: null,
    mode: "anonymous",
    quantity: 1,
    label_prefix: "",
    recipients_text: "",
    delivery: "generate_only",
    batch_label: "",
  });
  selectedTicket.value = null;
  sessions.value = [];
  validDays.value = [];
}

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  errors.value = {};

  const body = {
    ticket_id: form.ticket_id,
    mode: form.mode,
    delivery: form.delivery,
    batch_label: form.batch_label || null,
    ...(form.ticket_session_id ? { ticket_session_id: form.ticket_session_id } : {}),
    ...(form.selected_event_day_id ? { selected_event_day_id: form.selected_event_day_id } : {}),
    ...(form.mode === "anonymous"
      ? { quantity: Number(form.quantity), label_prefix: form.label_prefix || null }
      : { recipients: parsedRecipients.value }),
  };

  try {
    const res = await client(`${base.value}/bulk-generate`, { method: "POST", body });
    batchUlid.value = res.data.order_ulid;
    batch.value = { ...res.data, generated: 0 };
    emit("generated");
    startPolling(res.data.order_ulid);
  } catch (err) {
    errors.value = err?.data?.errors || {};
    toast.error(err?.data?.message || "Could not generate tickets.");
  } finally {
    submitting.value = false;
  }
}

function startPolling(ulid) {
  const poll = async () => {
    try {
      const res = await client(`${base.value}/batches/${ulid}/status`);
      batch.value = res.data;
      if (res.data.batch_status === "processing") {
        pollTimer = setTimeout(poll, 1500);
      } else {
        stopPolling();
      }
    } catch {
      pollTimer = setTimeout(poll, 2500);
    }
  };
  poll();
}

function stopPolling() {
  if (pollTimer) clearTimeout(pollTimer);
  pollTimer = null;
}

async function downloadCsv() {
  if (!batch.value) return;
  downloading.value = true;
  try {
    const ulid = batchUlid.value;
    const blob = await client(`${base.value}/batches/${ulid}/export`, { responseType: "blob" });
    const url = window.URL.createObjectURL(new Blob([blob], { type: "text/csv" }));
    const link = document.createElement("a");
    link.href = url;
    link.download = `tickets-batch-${ulid}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch {
    toast.error("Could not download CSV.");
  } finally {
    downloading.value = false;
  }
}

async function downloadBadges() {
  if (!batch.value?.attendees?.length) return;
  downloading.value = true;
  try {
    await generateBadges(batch.value.attendees, {
      fileName: `badges-${batch.value.batch_label || "batch"}.pdf`,
    });
  } catch {
    toast.error("Could not build badge PDF.");
  } finally {
    downloading.value = false;
  }
}
</script>
