<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div class="space-y-4">
      <h2 class="text-base font-medium tracking-tight">Company Information</h2>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-2">
          <Label>Company Name<span class="text-destructive">*</span></Label>
          <Input v-model="form.company_name" placeholder="PT PM One" required />
        </div>
        <div class="space-y-2">
          <Label>Tax ID (NPWP)</Label>
          <Input v-model="form.tax_id" placeholder="00.000.000.0-000.000" />
        </div>
      </div>

      <div class="space-y-2">
        <Label>Logo</Label>
        <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
          Company logo for Invoice & Receipt PDF header. JPG/PNG/WebP/SVG, max 5MB.
        </p>
        <InputFileImage
          v-model="logoFiles"
          v-model:delete-flag="deleteLogo"
          :initial-image="form.logo_url"
          container-class="relative isolate aspect-3/2 max-w-xs"
        />
      </div>

      <div class="space-y-2">
        <Label>Address</Label>
        <Textarea v-model="form.address" rows="2" placeholder="Full address line" />
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-2">
          <Label>City</Label>
          <Input v-model="form.city" placeholder="Jakarta" />
        </div>
        <div class="space-y-2">
          <Label>Country</Label>
          <Input v-model="form.country" placeholder="Indonesia" />
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-2">
          <Label>Phone</Label>
          <Input v-model="form.phone" placeholder="+62 21 ..." />
        </div>
        <div class="space-y-2">
          <Label>Email</Label>
          <Input v-model="form.email" type="email" placeholder="info@pmone.id" />
        </div>
        <div class="space-y-2">
          <Label>Website</Label>
          <Input v-model="form.website" type="url" placeholder="https://pmone.id" />
        </div>
      </div>
    </div>

    <div class="space-y-4 border-t pt-6">
      <div class="flex items-center justify-between">
        <h2 class="text-base font-medium tracking-tight">Bank Accounts</h2>
        <Button type="button" variant="outline" size="sm" @click="addBankAccount">
          <Icon name="lucide:plus" class="size-4" />
          Add Bank
        </Button>
      </div>

      <div v-if="!form.bank_accounts.length" class="text-muted-foreground rounded-md border border-dashed py-6 text-center text-sm tracking-tight">
        No bank accounts. Add one for invoice payment instructions.
      </div>

      <div v-for="(bank, idx) in form.bank_accounts" :key="idx" class="rounded-md border p-3 space-y-3">
        <div class="grid gap-3 lg:grid-cols-3">
          <div class="space-y-1.5">
            <Label class="text-xs sm:text-sm">Bank Name</Label>
            <Input v-model="bank.bank_name" placeholder="BCA" />
          </div>
          <div class="space-y-1.5">
            <Label class="text-xs sm:text-sm">Account Number</Label>
            <Input v-model="bank.account_number" placeholder="0000000000" />
          </div>
          <div class="space-y-1.5">
            <Label class="text-xs sm:text-sm">Account Name</Label>
            <Input v-model="bank.account_name" placeholder="PT PM One" />
          </div>
        </div>
        <div class="flex justify-end">
          <Button type="button" variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="removeBankAccount(idx)">
            <Icon name="lucide:trash-2" class="size-4" />
            Remove
          </Button>
        </div>
      </div>
    </div>

    <div class="space-y-4 border-t pt-6">
      <h2 class="text-base font-medium tracking-tight">Appearance</h2>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-2">
          <Label>Primary Color</Label>
          <div class="flex items-center gap-2">
            <input v-model="form.primary_color" type="color" class="size-9 cursor-pointer rounded-md border" />
            <Input v-model="form.primary_color" placeholder="#0F172A" class="flex-1" />
          </div>
          <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
            Accent color for PDF section headings.
          </p>
        </div>
      </div>

      <div class="space-y-2">
        <Label>Footer Note</Label>
        <Textarea v-model="form.footer_note" rows="2" placeholder="Thank you for your business." />
        <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
          Appears at the bottom of every Invoice & Receipt.
        </p>
      </div>
    </div>

    <div class="flex justify-end gap-2 border-t pt-4">
      <Button v-if="$slots.cancel || cancelLabel" variant="outline" type="button" @click="$emit('cancel')">{{ cancelLabel || "Cancel" }}</Button>
      <Button type="submit" :disabled="saving">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ submitLabel }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, watch } from "vue";
import InputFileImage from "@/components/InputFileImage.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  submitLabel: { type: String, default: "Save Branding" },
  cancelLabel: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "submit", "cancel"]);

const logoFiles = ref([]);
const deleteLogo = ref(false);

const blank = () => ({
  logo_url: "",
  company_name: "",
  address: "",
  city: "",
  country: "",
  phone: "",
  email: "",
  website: "",
  tax_id: "",
  bank_accounts: [],
  footer_note: "",
  primary_color: "#0F172A",
});

const form = reactive(blank());

watch(
  () => props.modelValue,
  (val) => {
    const next = { ...blank(), ...(val || {}) };
    next.bank_accounts = Array.isArray(next.bank_accounts)
      ? next.bank_accounts.map((b) => ({
          bank_name: b.bank_name ?? "",
          account_number: b.account_number ?? "",
          account_name: b.account_name ?? "",
        }))
      : [];
    Object.assign(form, next);
    logoFiles.value = [];
    deleteLogo.value = false;
  },
  { immediate: true, deep: true }
);

const addBankAccount = () => {
  form.bank_accounts.push({ bank_name: "", account_number: "", account_name: "" });
};

const removeBankAccount = (idx) => {
  form.bank_accounts.splice(idx, 1);
};

const handleSubmit = () => {
  const payload = JSON.parse(JSON.stringify(form));
  const tmp = logoFiles.value?.[0];
  payload.tmp_logo = typeof tmp === "string" && tmp.startsWith("tmp-") ? tmp : null;
  payload.delete_logo = !!deleteLogo.value;
  emit("update:modelValue", payload);
  emit("submit", payload);
};
</script>
