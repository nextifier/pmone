<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <!-- Basic info -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic info</div>
        <div class="frame-description">Code value and the rule it is attached to.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div v-if="isCreate" class="space-y-2">
            <Label for="rule_ulid">Promotion Rule</Label>
            <Select v-model="form.rule_ulid" :disabled="loadingRules">
              <SelectTrigger class="w-full"><SelectValue placeholder="Select a rule" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="rule in rules" :key="rule.id" :value="rule.ulid">
                  {{ rule.name }} ({{ rule.kind_label }} - {{ rule.value_type === "percentage" ? `${rule.value}%` : `Rp${formatRupiah(rule.value)}` }})
                </SelectItem>
              </SelectContent>
            </Select>
            <InputErrorMessage :errors="errors.promotion_rule_id" />
          </div>

          <div class="space-y-2">
            <Label for="code">Code</Label>
            <Input id="code" v-model="form.code" type="text" required maxlength="60" pattern="[A-Za-z0-9_-]+" class="uppercase font-mono" />
            <p class="text-muted-foreground text-xs tracking-tight">Uppercase letters, numbers, dashes only. Stored uppercase.</p>
            <InputErrorMessage :errors="errors.code" />
          </div>

          <div class="space-y-2">
            <Label for="issued_to_email">Issued To Email (optional)</Label>
            <Input id="issued_to_email" v-model="form.issued_to_email" type="email" maxlength="255" placeholder="Restrict to specific email" />
            <InputErrorMessage :errors="errors.issued_to_email" />
          </div>
        </div>
      </div>
    </div>

    <!-- Limits -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Usage Limits</div>
        <div class="frame-description">How many times this code can be used.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
          <div class="space-y-2">
            <Label for="usage_limit">Usage Limit (total)</Label>
            <InputNumber id="usage_limit" v-model="form.usage_limit" :min="1" placeholder="Unlimited" />
            <p class="text-muted-foreground text-xs tracking-tight">Leave empty for unlimited.</p>
            <InputErrorMessage :errors="errors.usage_limit" />
          </div>

          <div class="space-y-2">
            <Label for="usage_limit_per_email">Per Email</Label>
            <InputNumber id="usage_limit_per_email" v-model="form.usage_limit_per_email" :min="1" placeholder="1" />
            <p class="text-muted-foreground text-xs tracking-tight">Max times each email can use this code.</p>
            <InputErrorMessage :errors="errors.usage_limit_per_email" />
          </div>
        </div>
      </div>
    </div>

    <!-- Validity & Active -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Validity</div>
        <div class="frame-description">Code-level dates override the rule's window.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
          <div class="space-y-2">
            <Label for="valid_from">Valid From</Label>
            <DatePicker
              v-model="form.valid_from"
              with-time
              placeholder="Select start date & time"
              :default-hour="0"
              :default-minute="0"
            />
            <InputErrorMessage :errors="errors.valid_from" />
          </div>

          <div class="space-y-2">
            <Label for="valid_until">Valid Until</Label>
            <DatePicker
              v-model="form.valid_until"
              with-time
              placeholder="Select end date & time"
              :default-hour="23"
              :default-minute="59"
            />
            <InputErrorMessage :errors="errors.valid_until" />
          </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
          <Switch id="is_active" v-model="form.is_active" />
          <Label for="is_active" class="cursor-pointer">Active</Label>
        </div>
      </div>
    </div>

    <!-- Submit -->
    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" @click="$router.back()">Cancel</Button>
      <Button type="submit" :disabled="loading">
        <Spinner v-if="loading" />
        {{ loading ? submitLoadingText : submitText }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";

const props = defineProps({
  isCreate: { type: Boolean, default: false },
  initialData: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  submitText: { type: String, default: "Save" },
  submitLoadingText: { type: String, default: "Saving.." },
});

const emit = defineEmits(["submit"]);

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const form = ref({
  rule_ulid: null,
  code: "",
  usage_limit: 1,
  usage_limit_per_email: 1,
  valid_from: null,
  valid_until: null,
  is_active: true,
  issued_to_email: "",
});

if (props.initialData) {
  Object.assign(form.value, {
    ...props.initialData,
    valid_from: props.initialData.valid_from ? new Date(props.initialData.valid_from) : null,
    valid_until: props.initialData.valid_until ? new Date(props.initialData.valid_until) : null,
  });
}

function formatDateTimeForBackend(date) {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day} ${hours}:${minutes}:00`;
}

// Load rules for the select (only on create)
const rules = ref([]);
const loadingRules = ref(false);
const client = useSanctumClient();

if (props.isCreate) {
  loadingRules.value = true;
  client("/api/promotion-rules?per_page=200&filter_is_active=true")
    .then((res) => {
      rules.value = res?.data ?? [];
    })
    .catch(() => {})
    .finally(() => {
      loadingRules.value = false;
    });
}

function handleSubmit() {
  const payload = { ...form.value };
  payload.code = (payload.code || "").trim().toUpperCase();
  payload.valid_from = formatDateTimeForBackend(form.value.valid_from);
  payload.valid_until = formatDateTimeForBackend(form.value.valid_until);
  payload.issued_to_email = payload.issued_to_email || null;
  if (!payload.usage_limit) payload.usage_limit = null;
  if (!payload.usage_limit_per_email) payload.usage_limit_per_email = null;
  emit("submit", payload);
}
</script>
