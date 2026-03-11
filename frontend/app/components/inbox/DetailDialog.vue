<template>
  <DialogResponsive v-model:open="open" dialogMaxWidth="600px" :overflow-content="true">
    <template #default>
      <div v-if="submission" class="px-4 pb-10 md:px-6 md:py-6">
        <!-- Header with Status -->
        <div class="flex items-start justify-between gap-x-2 sm:items-end">
          <div class="space-y-1">
            <h3 class="pr-8 text-lg font-semibold tracking-tight">
              {{ submission.subject || "No Subject" }}
            </h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              {{ submission.project?.name || "Unknown Project" }} ·
              {{ $dayjs(submission.created_at).format("MMM D, YYYY [at] h:mm A") }}
            </p>
          </div>
          <StatusDropdown
            v-if="!readonly"
            :status="submission.status"
            :disabled="statusUpdating"
            @update="handleStatusUpdate"
          />
          <span
            v-else
            :class="getStatusConfig(submission.status).color"
            class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium"
          >
            {{ getStatusConfig(submission.status).label }}
          </span>
        </div>

        <!-- Form Data -->
        <div class="divide-border mt-5 divide-y">
          <div
            v-for="(value, key) in submission.form_data"
            :key="key"
            class="space-y-1 py-3 first:pt-0"
          >
            <div class="text-muted-foreground text-sm font-medium tracking-tight">
              {{ formatFieldLabel(key) }}
            </div>
            <div class="text-sm tracking-tight">
              <template v-if="key === 'email'">
                <div class="flex items-center gap-2">
                  <a :href="`mailto:${value}`" class="text-primary hover:underline">
                    {{ value }}
                  </a>
                  <a
                    :href="`mailto:${value}`"
                    v-tippy="'Email'"
                    class="hover:bg-muted text-info-foreground inline-flex size-7 items-center justify-center rounded-md transition"
                  >
                    <Icon name="hugeicons:mail-01" class="size-4" />
                  </a>
                </div>
              </template>
              <template v-else-if="key === 'phone'">
                <div class="flex items-center gap-2">
                  <FlagComponent
                    v-if="getCountryFromPhone(value)"
                    v-tippy="getCountryFromPhone(value)?.name"
                    :country="getCountryFromPhone(value)?.code"
                    class="cursor-help"
                  />
                  <a
                    :href="`https://wa.me/${formatWhatsAppNumber(value)}`"
                    target="_blank"
                    class="text-primary hover:underline"
                  >
                    {{ value }}
                  </a>
                  <a
                    :href="`https://wa.me/${formatWhatsAppNumber(value)}`"
                    target="_blank"
                    v-tippy="'WhatsApp'"
                    class="hover:bg-muted text-success-foreground inline-flex size-7 items-center justify-center rounded-md transition"
                  >
                    <Icon name="hugeicons:whatsapp" class="size-4" />
                  </a>
                </div>
              </template>
              <template v-else-if="key === 'message'">
                <div class="whitespace-pre-wrap">{{ value }}</div>
              </template>
              <template v-else>
                {{ value }}
              </template>
            </div>
          </div>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import FlagComponent from "@/components/FlagComponent.vue";
import StatusDropdown from "@/components/inbox/StatusDropdown.vue";
import { toast } from "vue-sonner";

const props = defineProps({
  readonly: { type: Boolean, default: false },
});

const emit = defineEmits(["statusUpdated"]);

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();
const { getStatusConfig, formatFieldLabel, formatWhatsAppNumber, getCountryFromPhone } =
  useInboxHelpers();

const open = defineModel("open", { type: Boolean, default: false });
const submission = defineModel("submission", { type: Object, default: null });

const statusUpdating = ref(false);

async function handleStatusUpdate(newStatus) {
  if (!submission.value) return;
  statusUpdating.value = true;
  try {
    await client(`/api/contact-form-submissions/${submission.value.ulid}/status`, {
      method: "PATCH",
      body: { status: newStatus },
    });
    submission.value = { ...submission.value, status: newStatus };
    toast.success("Status updated");
    emit("statusUpdated", submission.value.ulid, newStatus);
  } catch (err) {
    toast.error("Failed to update status");
  } finally {
    statusUpdating.value = false;
  }
}
</script>
