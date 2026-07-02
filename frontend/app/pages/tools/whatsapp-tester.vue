<template>
  <div class="mx-auto pt-4 pb-16 lg:max-w-4xl">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:whatsapp" class="size-5 sm:size-6" />
      <h1 class="page-title">WhatsApp Tester</h1>
    </div>

    <p class="page-description mt-2 max-w-2xl">
      Send a test WhatsApp template message to verify the Meta Cloud API setup. This calls the API
      directly, so it works regardless of the WHATSAPP_ENABLED flag and never touches the reservation
      flow. The template must already be approved in Meta, and for an unverified account the recipient
      must be registered as a test number.
    </p>

    <div class="bg-card mt-6 space-y-4 rounded-xl border p-4 sm:p-5">
      <div class="space-y-2">
        <Label for="to">Recipient phone number</Label>
        <InputPhone id="to" v-model="form.to" required />
        <p class="text-muted-foreground text-xs">
          The number is normalized server-side (08xxx becomes 628xxx).
        </p>
        <FieldError :errors="errors.to" />
      </div>

      <div class="space-y-2">
        <Label for="template">Template</Label>
        <Select v-model="form.template">
          <SelectTrigger id="template">
            <SelectValue placeholder="Select a template" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="hello_world">hello_world - connectivity test (no params)</SelectItem>
            <SelectItem value="ticket_confirmation">
              ticket_confirmation - booking confirmation (4 params)
            </SelectItem>
          </SelectContent>
        </Select>
        <p class="text-muted-foreground text-xs">
          Use hello_world first - it is pre-approved by Meta, so it confirms the token and phone number
          ID work before your custom template is live.
        </p>
        <FieldError :errors="errors.template" />
      </div>

      <div v-if="activeTemplate.params.length" class="grid grid-cols-1 gap-y-4">
        <div v-for="(param, index) in activeTemplate.params" :key="index" class="space-y-2">
          <Label :for="`param-${index}`">
            {{ param.label }}
            <span class="text-muted-foreground">{{ param.token }}</span>
          </Label>
          <Input :id="`param-${index}`" v-model="form.params[index]" :placeholder="param.placeholder" />
        </div>
      </div>

      <div>
        <Button :disabled="sending || !form.to" @click="send">
          <Spinner v-if="sending" class="size-4" />
          <Icon v-else name="hugeicons:sent" />
          <span>Send test message</span>
        </Button>
      </div>

      <div
        v-if="result"
        class="bg-success/10 border-success/20 text-success-foreground rounded-lg border p-4"
      >
        <p class="text-sm font-medium tracking-tight">{{ result.message }}</p>
        <pre
          v-if="result.result"
          class="text-muted-foreground mt-2 overflow-x-auto text-xs tracking-tight"
          >{{ JSON.stringify(result.result, null, 2) }}</pre
        >
      </div>

      <div
        v-if="failure"
        class="bg-destructive/10 border-destructive/20 text-destructive rounded-lg border p-4"
      >
        <p class="text-sm font-medium tracking-tight">{{ failure.message }}</p>
        <pre v-if="failure.error" class="mt-2 overflow-x-auto text-xs tracking-tight">{{
          JSON.stringify(failure.error, null, 2)
        }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  layout: "app",
  middleware: [
    "sanctum:auth",
    () => {
      const { hasAnyRole } = usePermission();
      if (!hasAnyRole(["master", "admin"])) {
        return createError({ statusCode: 403, statusMessage: "Forbidden", fatal: true });
      }
    },
  ],
});

usePageMeta(null, { title: "WhatsApp Tester" });

const client = useSanctumClient();

const templates = {
  hello_world: { lang: "en_US", params: [] },
  ticket_confirmation: {
    lang: "id",
    params: [
      { token: "{{1}}", label: "Guest name", placeholder: "Budi Santoso" },
      { token: "{{2}}", label: "Event", placeholder: "Megabuild Indonesia" },
      { token: "{{3}}", label: "Reservation number", placeholder: "HTL-20260609-ABCD" },
      { token: "{{4}}", label: "Reservation link", placeholder: "https://pmone.id/hotels/reservation/..." },
    ],
  },
};

const form = reactive({
  to: "",
  template: "hello_world",
  params: [],
});

const activeTemplate = computed(() => templates[form.template] ?? { lang: "id", params: [] });

watch(
  () => form.template,
  () => {
    form.params = activeTemplate.value.params.map(() => "");
  }
);

const sending = ref(false);
const errors = ref({});
const result = ref(null);
const failure = ref(null);

const send = async () => {
  if (sending.value) return;

  sending.value = true;
  errors.value = {};
  result.value = null;
  failure.value = null;

  try {
    const res = await client("/api/system/whatsapp/test", {
      method: "POST",
      body: {
        to: form.to,
        template: form.template,
        lang: activeTemplate.value.lang,
        params: form.params,
      },
    });

    result.value = res;
    toast.success("Test message sent", { description: res?.message });
  } catch (err) {
    const status = err?.response?.status;
    const body = err?.data;

    if (status === 422 && body?.errors) {
      errors.value = body.errors;
      toast.error("Please fix the errors and try again.");
    } else {
      failure.value = {
        message: body?.message || err?.message || "Failed to send the test message.",
        error: body?.error || null,
      };
      toast.error(failure.value.message);
    }
  } finally {
    sending.value = false;
  }
};
</script>
