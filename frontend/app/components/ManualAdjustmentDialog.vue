<template>
  <DialogResponsive v-model:open="dialogOpen" dialog-max-width="32rem">
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5 space-y-4">
        <div>
          <h3 class="text-lg font-semibold tracking-tight">Add Adjustment</h3>
          <p class="text-muted-foreground text-sm tracking-tight mt-1">
            <template v-if="manualOnly && itemLabel">
              Apply a discount or penalty to
              <span class="text-foreground font-medium">{{ itemLabel }}</span>.
            </template>
            <template v-else>
              Apply a discount or penalty to this {{ targetType.toLowerCase() }}.
            </template>
          </p>
        </div>

        <Tabs v-model="mode" class="w-full">
          <TabsList v-if="!manualOnly" class="grid grid-cols-3 w-full">
            <TabsTrigger value="promo_code">Promo Code</TabsTrigger>
            <TabsTrigger value="promotion_rule">From Rule</TabsTrigger>
            <TabsTrigger value="manual">Manual</TabsTrigger>
          </TabsList>

          <!-- Mode: Promo Code -->
          <TabsContent value="promo_code" class="space-y-4 mt-4">
            <div class="space-y-2">
              <Label for="promo_code">Promo Code</Label>
              <Input id="promo_code" v-model="form.promo_code" type="text" class="uppercase font-mono" placeholder="ENTER CODE" />
              <InputErrorMessage :errors="errors.promo_code" />
            </div>
            <div class="space-y-2">
              <Label for="promo_email">Customer Email</Label>
              <Input id="promo_email" v-model="form.email" type="email" :placeholder="targetEmail || 'guest@example.com'" />
              <InputErrorMessage :errors="errors.email" />
            </div>
          </TabsContent>

          <!-- Mode: From Rule -->
          <TabsContent value="promotion_rule" class="space-y-4 mt-4">
            <div class="space-y-2">
              <Label for="rule_id">Promotion Rule</Label>
              <Select v-model.number="form.promotion_rule_id" :disabled="loadingRules">
                <SelectTrigger class="w-full"><SelectValue placeholder="Select rule" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="rule in rules" :key="rule.id" :value="rule.id">
                    {{ rule.name }} ({{ rule.kind_label }})
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputErrorMessage :errors="errors.promotion_rule_id" />
            </div>
            <div class="space-y-2">
              <Label for="override_value">Override Value (optional)</Label>
              <InputNumber id="override_value" v-model="form.override_value" :min="0" decimal placeholder="Use rule's default" />
              <InputErrorMessage :errors="errors.override_value" />
            </div>
            <div class="space-y-2">
              <Label for="reason">Reason</Label>
              <Textarea id="reason" v-model="form.reason" rows="2" maxlength="500" />
              <InputErrorMessage :errors="errors.reason" />
            </div>
          </TabsContent>

          <!-- Mode: Manual -->
          <TabsContent value="manual" class="space-y-4 mt-4">
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label for="kind">Kind</Label>
                <Select v-model="form.kind">
                  <SelectTrigger class="w-full"><SelectValue placeholder="Select kind" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="discount">Discount</SelectItem>
                    <SelectItem value="penalty">Penalty</SelectItem>
                  </SelectContent>
                </Select>
                <InputErrorMessage :errors="errors.kind" />
              </div>
              <div class="space-y-2">
                <Label for="value_type">Type</Label>
                <Select v-model="form.value_type">
                  <SelectTrigger class="w-full"><SelectValue placeholder="Select type" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="percentage">Percentage</SelectItem>
                    <SelectItem value="fixed_amount">Fixed Amount</SelectItem>
                  </SelectContent>
                </Select>
                <InputErrorMessage :errors="errors.value_type" />
              </div>
            </div>
            <div class="space-y-2">
              <Label for="value">
                Value                <span class="text-muted-foreground text-xs ml-1">
                  ({{ form.value_type === "percentage" ? "%" : "Rp" }})
                </span>
              </Label>
              <InputGroup>
                <InputNumber
                  id="value"
                  v-model="form.value"
                  :min="0"
                  decimal
                  data-slot="input-group-control"
                  class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                />
                <InputGroupAddon :align="form.value_type === 'percentage' ? 'inline-end' : 'inline-start'">
                  <InputGroupText>{{ form.value_type === "percentage" ? "%" : "Rp" }}</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
              <InputErrorMessage :errors="errors.value" />
            </div>
            <div class="space-y-2">
              <Label for="manual_reason">Reason</Label>
              <Textarea id="manual_reason" v-model="form.reason" rows="2" maxlength="500" placeholder="Why is this adjustment applied?" />
              <InputErrorMessage :errors="errors.reason" />
            </div>
          </TabsContent>
        </Tabs>

        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
          <Button type="button" @click="handleApply" :disabled="loading">
            <Spinner v-if="loading" />
            {{ loading ? "Applying..." : "Apply" }}
          </Button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  open: { type: Boolean, default: false },
  targetType: { type: String, default: "Reservation" },
  targetEmail: { type: String, default: "" },
  loading: { type: Boolean, default: false },
  // When true, only the Manual tab is shown (used for per-item adjustments).
  manualOnly: { type: Boolean, default: false },
  // Optional order item this adjustment is scoped to.
  itemId: { type: [Number, null], default: null },
  itemLabel: { type: String, default: "" },
});

const emit = defineEmits(["update:open", "apply"]);

const dialogOpen = computed({
  get: () => props.open,
  set: (v) => emit("update:open", v),
});

const mode = ref(props.manualOnly ? "manual" : "promo_code");
const errors = ref({});

const form = ref({
  promo_code: "",
  email: props.targetEmail || "",
  promotion_rule_id: null,
  override_value: null,
  kind: "discount",
  value_type: "percentage",
  value: 0,
  reason: "",
});

// Reset form on close
watch(dialogOpen, (open) => {
  if (!open) {
    errors.value = {};
    form.value = {
      promo_code: "",
      email: props.targetEmail || "",
      promotion_rule_id: null,
      override_value: null,
      kind: "discount",
      value_type: "percentage",
      value: 0,
      reason: "",
    };
    mode.value = props.manualOnly ? "manual" : "promo_code";
  }
});

watch(() => props.targetEmail, (email) => {
  if (email && !form.value.email) form.value.email = email;
});

// Load rules lazily for "From Rule" tab
const rules = ref([]);
const loadingRules = ref(false);
const client = useSanctumClient();

watch(mode, async (newMode) => {
  if (newMode === "promotion_rule" && rules.value.length === 0 && !loadingRules.value) {
    loadingRules.value = true;
    try {
      const res = await client("/api/promotion-rules?per_page=200&filter_is_active=true");
      rules.value = res?.data ?? [];
    } catch (e) {
      // ignore
    } finally {
      loadingRules.value = false;
    }
  }
});

function buildPayload() {
  if (mode.value === "promo_code") {
    return {
      mode: "promo_code",
      promo_code: form.value.promo_code,
      email: form.value.email,
    };
  }
  if (mode.value === "promotion_rule") {
    const payload = {
      mode: "promotion_rule",
      promotion_rule_id: form.value.promotion_rule_id,
      reason: form.value.reason,
    };
    if (form.value.override_value !== null && form.value.override_value !== "") {
      payload.override_value = form.value.override_value;
    }
    return payload;
  }
  const manualPayload = {
    mode: "manual",
    kind: form.value.kind,
    value_type: form.value.value_type,
    value: form.value.value,
    reason: form.value.reason,
  };
  if (props.itemId != null) {
    manualPayload.order_item_id = props.itemId;
  }
  return manualPayload;
}

function handleApply() {
  errors.value = {};
  const payload = buildPayload();
  emit("apply", payload, (errs) => {
    errors.value = errs ?? {};
  });
}
</script>
