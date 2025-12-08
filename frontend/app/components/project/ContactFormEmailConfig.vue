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
          <SwitchRoot
            :checked="props.modelValue?.enabled ?? false"
            @update:checked="(val) => emitUpdate({ enabled: val })"
            class="peer data-[state=checked]:bg-primary data-[state=unchecked]:bg-input focus-visible:border-ring focus-visible:ring-ring inline-flex h-[1.15rem] w-8 shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:ring-[1px] disabled:cursor-not-allowed disabled:opacity-50"
          >
            <SwitchThumb
              class="bg-background dark:data-[state=unchecked]:bg-foreground dark:data-[state=checked]:bg-primary-foreground pointer-events-none block size-4 rounded-full ring-0 transition-transform data-[state=checked]:translate-x-[calc(100%-2px)] data-[state=unchecked]:translate-x-0"
            />
          </SwitchRoot>
        </div>

        <template v-if="props.modelValue?.enabled">
          <!-- To Recipients (Array) -->
          <div class="space-y-3">
            <Label>To (Recipients)</Label>
            <p class="text-muted-foreground text-xs tracking-tight">
              Primary email recipients for form submissions
            </p>
            <div class="space-y-2">
              <div
                v-for="(email, index) in toEmails"
                :key="`to-${index}`"
                class="flex items-center gap-1.5"
              >
                <Input :model-value="email" @update:model-value="updateEmail('to', index, $event)" type="email" placeholder="email@example.com" />
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
            <div v-if="ccEmails.length > 0" class="space-y-2">
              <div
                v-for="(email, index) in ccEmails"
                :key="`cc-${index}`"
                class="flex items-center gap-1.5"
              >
                <Input :model-value="email" @update:model-value="updateEmail('cc', index, $event)" type="email" placeholder="email@example.com" />
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
            <div v-if="bccEmails.length > 0" class="space-y-2">
              <div
                v-for="(email, index) in bccEmails"
                :key="`bcc-${index}`"
                class="flex items-center gap-1.5"
              >
                <Input :model-value="email" @update:model-value="updateEmail('bcc', index, $event)" type="email" placeholder="email@example.com" />
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
              :model-value="fromName"
              @update:model-value="(val) => emitUpdate({ from_name: val })"
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
              :model-value="replyTo"
              @update:model-value="(val) => emitUpdate({ reply_to: val })"
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
import { SwitchRoot, SwitchThumb } from "reka-ui";

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

// Computed getters for reading other values
const toEmails = computed(() => props.modelValue?.email_config?.to ?? []);
const ccEmails = computed(() => props.modelValue?.email_config?.cc ?? []);
const bccEmails = computed(() => props.modelValue?.email_config?.bcc ?? []);
const fromName = computed(() => props.modelValue?.email_config?.from_name ?? "");
const replyTo = computed(() => props.modelValue?.email_config?.reply_to ?? "");

function emitUpdate(changes) {
  const currentConfig = props.modelValue?.email_config ?? {};

  const newValue = {
    enabled: changes.enabled !== undefined ? changes.enabled : (props.modelValue?.enabled ?? false),
    email_config: {
      to: changes.to !== undefined ? changes.to : (currentConfig.to ?? []),
      cc: changes.cc !== undefined ? changes.cc : (currentConfig.cc ?? []),
      bcc: changes.bcc !== undefined ? changes.bcc : (currentConfig.bcc ?? []),
      from_name: changes.from_name !== undefined ? changes.from_name : (currentConfig.from_name ?? ""),
      reply_to: changes.reply_to !== undefined ? changes.reply_to : (currentConfig.reply_to ?? ""),
    },
  };

  emit("update:modelValue", newValue);
}

function addEmail(type) {
  const currentEmails = props.modelValue?.email_config?.[type] ?? [];
  emitUpdate({ [type]: [...currentEmails, ""] });
}

function removeEmail(type, index) {
  const currentEmails = [...(props.modelValue?.email_config?.[type] ?? [])];
  currentEmails.splice(index, 1);
  emitUpdate({ [type]: currentEmails });
}

function updateEmail(type, index, value) {
  const currentEmails = [...(props.modelValue?.email_config?.[type] ?? [])];
  currentEmails[index] = value;
  emitUpdate({ [type]: currentEmails });
}
</script>
