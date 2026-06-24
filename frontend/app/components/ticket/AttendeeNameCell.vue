<template>
  <div class="flex min-w-0 flex-col gap-0.5">
    <div class="flex items-center gap-x-1">
      <span class="truncate text-sm tracking-tight">{{ attendee.name || "Unnamed" }}</span>

      <Icon
        v-if="attendee.is_personalized"
        v-tippy="'Personalized by attendee'"
        name="hugeicons:user-check-01"
        class="text-muted-foreground size-3.5 shrink-0"
      />
      <Icon
        v-if="attendee.has_account"
        v-tippy="'Has linked account'"
        name="hugeicons:user-circle"
        class="text-muted-foreground size-3.5 shrink-0"
      />

      <button
        v-if="qrReady"
        @click="qrDialogOpen = true"
        v-tippy="'QR Code'"
        aria-label="QR Code"
        class="text-muted-foreground hover:text-foreground -ml-1 flex size-7 shrink-0 items-center justify-center rounded-lg"
      >
        <Icon name="hugeicons:qr-code-01" class="size-4 shrink-0" />
      </button>
    </div>

    <div class="text-muted-foreground truncate text-xs tracking-tight">
      {{ attendee.email || attendee.phone || "-" }}
    </div>

    <AttendeeQrDialog v-if="qrReady" v-model:open="qrDialogOpen" :attendee="attendee" />
  </div>
</template>

<script setup>
import AttendeeQrDialog from "@/components/ticket/AttendeeQrDialog.vue";

const props = defineProps({
  attendee: {
    type: Object,
    required: true,
  },
});

const qrDialogOpen = ref(false);

// The QR is only usable once the order is confirmed (free/complimentary orders
// are auto-confirmed). Mirrors the backend gate that withholds the QR token for
// pending orders, so staff never see an unscannable QR.
const qrReady = computed(
  () => !!props.attendee.qr_token && props.attendee.order?.status === "confirmed"
);
</script>
