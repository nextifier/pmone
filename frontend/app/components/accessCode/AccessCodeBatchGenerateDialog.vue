<template>
  <DialogResponsive v-model:open="openModel" dialog-max-width="640px">
    <template #default>
      <div class="space-y-5 px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h2 class="text-lg font-semibold tracking-tighter">Generate access codes</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Create codes that unlock gated tickets. Shared codes suit broadcasts; invitations are
            unique per person and can bind to an email or phone.
          </p>
        </div>

        <!-- Result -->
        <div v-if="result" class="space-y-4">
          <div
            class="bg-success/10 text-success-foreground flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm tracking-tight"
          >
            <Icon name="hugeicons:checkmark-circle-02" class="size-4 shrink-0" />
            Generated {{ result.codes_count }} code(s).
          </div>

          <div
            v-if="singleCode"
            class="flex items-center justify-between gap-2 rounded-lg border border-dashed px-3 py-2.5"
          >
            <span class="font-mono text-base font-semibold tracking-widest">{{ singleCode.code }}</span>
            <Button type="button" variant="outline" size="sm" @click="copyCode(singleCode.code)">
              <Icon name="hugeicons:copy-01" class="size-4 shrink-0" />
              Copy
            </Button>
          </div>

          <div class="flex items-center justify-between gap-2 border-t pt-3">
            <Button type="button" variant="ghost" size="sm" @click="resetForm">Generate another</Button>
            <div class="flex gap-2">
              <Button type="button" variant="outline" size="sm" :disabled="exporting" @click="exportCsv">
                <Spinner v-if="exporting" class="size-4" />
                <Icon v-else name="hugeicons:download-04" class="size-4 shrink-0" />
                Export CSV
              </Button>
              <Button type="button" size="sm" @click="openModel = false">Done</Button>
            </div>
          </div>
        </div>

        <!-- Form -->
        <form v-else class="space-y-4" @submit.prevent="submit">
          <div class="space-y-2">
            <Label for="ac-name">Batch name</Label>
            <Input id="ac-name" v-model="form.name" placeholder="e.g. VIP invitations - speakers" />
            <InputErrorMessage :errors="errors.name" />
          </div>

          <!-- Unlocked tickets -->
          <div class="space-y-2">
            <Label>Unlocks tickets</Label>
            <div class="space-y-2 rounded-lg border p-3">
              <p v-if="!tickets.length" class="text-muted-foreground text-sm tracking-tight">
                No tickets in this event yet.
              </p>
              <div v-for="t in tickets" :key="t.id" class="flex items-center gap-2">
                <Checkbox
                  :id="`ac-unlock-${t.id}`"
                  :model-value="form.unlocks.includes(t.id)"
                  @update:model-value="(c) => toggleUnlock(t.id, !!c)"
                />
                <Label :for="`ac-unlock-${t.id}`" class="grow cursor-pointer font-normal tracking-tight">
                  {{ t.title }}
                  <Badge v-if="t.visibility && t.visibility !== 'public'" variant="muted" plain class="ml-1">
                    {{ t.visibility === "hidden" ? "Hidden" : "Code required" }}
                  </Badge>
                </Label>
              </div>
            </div>
            <InputErrorMessage :errors="errors.unlocks" />
          </div>

          <!-- Kind -->
          <Tabs :model-value="form.kind" @update:model-value="(v) => (form.kind = v)">
            <TabsList class="grid w-full grid-cols-2">
              <TabsTrigger value="shared">Shared</TabsTrigger>
              <TabsTrigger value="invitation">Invitation</TabsTrigger>
            </TabsList>
          </Tabs>

          <div v-if="form.kind === 'shared'" class="space-y-2">
            <Label for="ac-max-uses">Max uses</Label>
            <InputNumber id="ac-max-uses" v-model="form.max_uses" :min="1" placeholder="Leave blank for unlimited" />
            <p class="text-muted-foreground text-xs tracking-tight">
              How many times this single code can be redeemed in total.
            </p>
          </div>

          <div v-else class="space-y-2">
            <Label for="ac-recipients">Recipients</Label>
            <Textarea
              id="ac-recipients"
              v-model="form.recipients_text"
              :rows="5"
              placeholder="One per line: Name, email, phone&#10;Jane Doe, jane@example.com, 0812xxxx&#10;John Smith, john@example.com"
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
              <input ref="csvInput" type="file" accept=".csv,text/csv,text/plain" class="hidden" @change="onCsv" />
            </div>
            <p class="text-muted-foreground text-xs tracking-tight">
              Each recipient gets a unique single-use code bound to their email/phone. Leave the list
              empty and set a quantity below to generate anonymous invitations.
            </p>
            <div class="pt-1">
              <Label for="ac-qty" class="text-xs">Or quantity (no binding)</Label>
              <InputNumber id="ac-qty" v-model="form.quantity" :min="0" placeholder="0" />
            </div>
          </div>

          <!-- Price effect -->
          <div class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="ac-effect">Price effect</Label>
              <Select :model-value="form.price_effect" @update:model-value="(v) => (form.price_effect = v)">
                <SelectTrigger id="ac-effect" class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No price effect (gate only)</SelectItem>
                  <SelectItem value="set_price">Set price</SelectItem>
                  <SelectItem value="percentage">Percentage off</SelectItem>
                  <SelectItem value="amount">Amount off</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div v-if="form.price_effect !== 'none'" class="space-y-2">
              <Label for="ac-value">
                {{ form.price_effect === "percentage" ? "Percent (%)" : "Value (IDR)" }}
              </Label>
              <InputNumber id="ac-value" v-model="form.price_value" :min="0" />
              <InputErrorMessage :errors="errors.price_value" />
            </div>
          </div>

          <div v-if="form.price_effect !== 'none'" class="flex items-center gap-2">
            <Switch id="ac-stackable" v-model="form.stackable" />
            <Label for="ac-stackable" class="font-normal tracking-tight">
              Allow stacking with a promo code
            </Label>
          </div>

          <!-- Validity + limits -->
          <div class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label>Valid from</Label>
              <DatePicker
                with-time
                :model-value="form._valid_from_obj"
                placeholder="Optional start"
                @update:model-value="(d) => (form._valid_from_obj = d)"
              />
            </div>
            <div class="space-y-2">
              <Label>Valid until</Label>
              <DatePicker
                with-time
                :model-value="form._valid_until_obj"
                placeholder="Optional end"
                @update:model-value="(d) => (form._valid_until_obj = d)"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="ac-maxqty">Max tickets per redemption</Label>
              <InputNumber id="ac-maxqty" v-model="form.max_qty_per_redemption" :min="1" :max="50" />
            </div>
            <div class="space-y-2">
              <Label for="ac-assigned">Assigned to (optional)</Label>
              <Input id="ac-assigned" v-model="form.assigned_to" placeholder="e.g. Gold Sponsor" />
            </div>
          </div>

          <div v-if="canSendInvites" class="flex items-center gap-2">
            <Switch id="ac-send" v-model="form.send_invites" />
            <Label for="ac-send" class="font-normal tracking-tight">
              Send invite links now (email / WhatsApp)
            </Label>
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Input } from "@/components/ui/input";
import { InputNumber } from "@/components/ui/input-number";
import { InputErrorMessage } from "@/components/ui/input-error-message";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

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
const base = computed(() => `/api/events/${props.event?.id}/access-codes`);

const tickets = ref([]);
const errors = ref({});
const submitting = ref(false);
const exporting = ref(false);
const result = ref(null);
const csvInput = ref(null);

const form = reactive({
  name: "",
  unlocks: [],
  kind: "shared",
  max_uses: null,
  quantity: 0,
  recipients_text: "",
  price_effect: "none",
  price_value: null,
  stackable: false,
  max_qty_per_redemption: 1,
  assigned_to: "",
  send_invites: false,
  _valid_from_obj: null,
  _valid_until_obj: null,
});

watch(openModel, async (open) => {
  if (open) {
    resetForm();
    await loadTickets();
  }
});

async function loadTickets() {
  try {
    const res = await client(`/api/events/${props.event?.id}/tickets`);
    tickets.value = res?.data ?? [];
  } catch {
    tickets.value = [];
  }
}

function toggleUnlock(id, checked) {
  if (checked) {
    if (!form.unlocks.includes(id)) form.unlocks.push(id);
  } else {
    form.unlocks = form.unlocks.filter((x) => x !== id);
  }
}

const parsedRecipients = computed(() =>
  form.recipients_text
    .split(/\r?\n/)
    .map((line) => {
      const [name, email, phone] = line.split(",").map((s) => (s || "").trim());
      return name || email ? { name: name || null, email: email || null, phone: phone || null } : null;
    })
    .filter(Boolean)
);

const canSendInvites = computed(
  () => form.kind === "invitation" && parsedRecipients.value.some((r) => r.email || r.phone)
);

const canSubmit = computed(() => {
  if (!form.name?.trim()) return false;
  if (!form.unlocks.length) return false;
  if (form.price_effect !== "none" && (form.price_value === null || form.price_value === "")) return false;
  if (form.kind === "invitation") {
    return parsedRecipients.value.length > 0 || Number(form.quantity) > 0;
  }
  return true;
});

const singleCode = computed(() => {
  const codes = result.value?.access_codes ?? [];
  return codes.length === 1 ? codes[0] : null;
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
        if (!cols[0] && !cols[1]) return "";
        return [cols[0] || "", cols[1] || "", cols[2] || ""].filter(Boolean).join(", ");
      })
      .filter(Boolean)
      .join("\n");
  };
  reader.readAsText(file);
  e.target.value = "";
}

function resetForm() {
  result.value = null;
  errors.value = {};
  Object.assign(form, {
    name: "",
    unlocks: [],
    kind: "shared",
    max_uses: null,
    quantity: 0,
    recipients_text: "",
    price_effect: "none",
    price_value: null,
    stackable: false,
    max_qty_per_redemption: 1,
    assigned_to: "",
    send_invites: false,
    _valid_from_obj: null,
    _valid_until_obj: null,
  });
}

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  errors.value = {};

  const body = {
    name: form.name,
    kind: form.kind,
    unlocks: form.unlocks,
    price_effect: form.price_effect,
    price_value: form.price_effect !== "none" ? Number(form.price_value) : null,
    stackable: form.stackable,
    max_qty_per_redemption: Number(form.max_qty_per_redemption) || 1,
    assigned_to: form.assigned_to || null,
    valid_from: form._valid_from_obj ? toLocalDateTimeString(form._valid_from_obj) : null,
    valid_until: form._valid_until_obj ? toLocalDateTimeString(form._valid_until_obj) : null,
    delivery: form.send_invites ? "send_invites" : "none",
  };

  if (form.kind === "shared") {
    body.max_uses = form.max_uses ? Number(form.max_uses) : null;
  } else if (parsedRecipients.value.length) {
    body.recipients = parsedRecipients.value;
  } else {
    body.quantity = Number(form.quantity);
  }

  try {
    const res = await client(base.value, { method: "POST", body });
    result.value = res.data;
    emit("generated");
  } catch (err) {
    errors.value = err?.data?.errors || {};
    toast.error(err?.data?.message || "Could not generate access codes.");
  } finally {
    submitting.value = false;
  }
}

function copyCode(code) {
  navigator.clipboard?.writeText(code);
  toast.success("Code copied");
}

async function exportCsv() {
  exporting.value = true;
  try {
    const blob = await client(`${base.value}/export`, { responseType: "blob" });
    const url = window.URL.createObjectURL(
      new Blob([blob], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      })
    );
    const link = document.createElement("a");
    link.href = url;
    link.download = `access-codes-${props.event?.slug || "event"}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch {
    toast.error("Could not export codes.");
  } finally {
    exporting.value = false;
  }
}
</script>
