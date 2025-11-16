<template>
  <div class="frame">
    <div class="frame-header">
      <div class="frame-title">Contact Form Email Configuration</div>
      <p class="text-muted-foreground text-xs tracking-tight">
        Configure email settings for contact form submissions
      </p>
    </div>
    <div class="frame-panel">
      <div class="grid grid-cols-1 gap-y-6">
        <!-- Enable Contact Form -->
        <div class="flex items-center justify-between">
          <div class="space-y-0.5">
            <Label>Enable Contact Form</Label>
            <p class="text-muted-foreground text-xs">
              Allow external websites to submit contact forms for this project
            </p>
          </div>
          <Switch v-model:checked="config.enabled" />
        </div>

        <template v-if="config.enabled">
          <!-- To Recipients (Array) -->
          <div class="space-y-3">
            <Label>To (Recipients)</Label>
            <p class="text-muted-foreground text-xs tracking-tight">
              Primary email recipients for form submissions
            </p>
            <div class="space-y-2">
              <div
                v-for="(email, index) in config.to"
                :key="`to-${index}`"
                class="flex items-center gap-1.5"
              >
                <Input v-model="config.to[index]" type="email" placeholder="email@example.com" />
                <button
                  type="button"
                  @click="removeEmail('to', index)"
                  class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                >
                  <Icon name="hugeicons:delete-01" class="size-4" />
                </button>
              </div>
            </div>
            <button
              type="button"
              @click="addEmail('to')"
              class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
            >
              <Icon name="hugeicons:add-01" class="size-4" />
              Add To Email
            </button>
          </div>

          <!-- CC Recipients (Array) -->
          <div class="space-y-3">
            <Label>CC (Carbon Copy)</Label>
            <p class="text-muted-foreground text-xs tracking-tight">
              Optional CC recipients
            </p>
            <div v-if="config.cc.length > 0" class="space-y-2">
              <div
                v-for="(email, index) in config.cc"
                :key="`cc-${index}`"
                class="flex items-center gap-1.5"
              >
                <Input v-model="config.cc[index]" type="email" placeholder="email@example.com" />
                <button
                  type="button"
                  @click="removeEmail('cc', index)"
                  class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                >
                  <Icon name="hugeicons:delete-01" class="size-4" />
                </button>
              </div>
            </div>
            <button
              type="button"
              @click="addEmail('cc')"
              class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
            >
              <Icon name="hugeicons:add-01" class="size-4" />
              Add CC Email
            </button>
          </div>

          <!-- BCC Recipients (Array) -->
          <div class="space-y-3">
            <Label>BCC (Blind Carbon Copy)</Label>
            <p class="text-muted-foreground text-xs tracking-tight">
              Optional BCC recipients (hidden from other recipients)
            </p>
            <div v-if="config.bcc.length > 0" class="space-y-2">
              <div
                v-for="(email, index) in config.bcc"
                :key="`bcc-${index}`"
                class="flex items-center gap-1.5"
              >
                <Input v-model="config.bcc[index]" type="email" placeholder="email@example.com" />
                <button
                  type="button"
                  @click="removeEmail('bcc', index)"
                  class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                >
                  <Icon name="hugeicons:delete-01" class="size-4" />
                </button>
              </div>
            </div>
            <button
              type="button"
              @click="addEmail('bcc')"
              class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
            >
              <Icon name="hugeicons:add-01" class="size-4" />
              Add BCC Email
            </button>
          </div>

          <!-- From Name -->
          <div class="space-y-2">
            <Label for="from_name">From Name</Label>
            <Input
              id="from_name"
              v-model="config.from_name"
              type="text"
              placeholder="Your Project Name"
            />
            <p class="text-muted-foreground text-xs tracking-tight">
              The sender name that appears in the email
            </p>
          </div>

          <!-- Reply To -->
          <div class="space-y-2">
            <Label for="reply_to">Reply To Email</Label>
            <Input
              id="reply_to"
              v-model="config.reply_to"
              type="email"
              placeholder="noreply@example.com"
            />
            <p class="text-muted-foreground text-xs tracking-tight">
              Email address for replies (defaults to submitter's email if not set)
            </p>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({
      enabled: false,
      email_config: {
        to: [],
        cc: [],
        bcc: [],
        from_name: "",
        reply_to: "",
      },
    }),
  },
});

const emit = defineEmits(["update:modelValue"]);

// Initialize config with fallback values
const config = ref({
  enabled: props.modelValue?.enabled || false,
  to: props.modelValue?.email_config?.to || [],
  cc: props.modelValue?.email_config?.cc || [],
  bcc: props.modelValue?.email_config?.bcc || [],
  from_name: props.modelValue?.email_config?.from_name || "",
  reply_to: props.modelValue?.email_config?.reply_to || "",
});

// Watch for changes and emit
watch(
  config,
  (newValue) => {
    emit("update:modelValue", {
      enabled: newValue.enabled,
      email_config: {
        to: newValue.to.filter((e) => e && e.trim()),
        cc: newValue.cc.filter((e) => e && e.trim()),
        bcc: newValue.bcc.filter((e) => e && e.trim()),
        from_name: newValue.from_name,
        reply_to: newValue.reply_to,
      },
    });
  },
  { deep: true }
);

function addEmail(type) {
  config.value[type].push("");
}

function removeEmail(type, index) {
  config.value[type].splice(index, 1);
}
</script>
