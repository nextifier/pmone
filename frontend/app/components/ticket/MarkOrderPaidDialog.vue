<template>
  <DialogResponsive :open="open" @update:open="(v) => emit('update:open', v)">
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="text-foreground text-lg font-semibold tracking-tight">Mark order as paid?</div>
        <p class="text-body mt-1.5 text-sm tracking-tight">
          This confirms order
          <span class="text-foreground font-medium">{{ attendee.order?.number }}</span>
          and emails the e-ticket(s) to every attendee on it. Use only when payment is verified but
          didn't sync automatically.
        </p>

        <div class="mt-4 space-y-3">
          <div class="space-y-1.5">
            <Label for="mark-paid-channel">Payment channel</Label>
            <Select v-model="channel">
              <SelectTrigger id="mark-paid-channel" class="w-full">
                <SelectValue>
                  <PaymentMethodBadge v-if="channel" :channel="channel" size="sm" />
                  <span v-else class="text-muted-foreground">Select payment channel</span>
                </SelectValue>
              </SelectTrigger>
              <SelectContent class="max-h-72">
                <SelectGroup v-for="group in PAYMENT_CHANNEL_GROUPS" :key="group.label">
                  <SelectLabel>{{ group.label }}</SelectLabel>
                  <SelectItem v-for="opt in group.options" :key="opt.value" :value="opt.value">
                    <PaymentMethodBadge :channel="opt.value" size="sm" />
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-1.5">
            <Label for="mark-paid-note">Note (optional)</Label>
            <Textarea
              id="mark-paid-note"
              v-model="note"
              :rows="2"
              placeholder="Reference / reason for manual confirmation"
            />
          </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button
            class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
            :disabled="pending"
            @click="emit('update:open', false)"
          >
            Cancel
          </button>
          <button
            class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="pending || !channel"
            @click="submit"
          >
            <Spinner v-if="pending" class="size-4" />
            <span v-else>Mark as paid</span>
          </button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import PaymentMethodBadge from "@/components/PaymentMethodBadge.vue";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Spinner } from "@/components/ui/spinner";
import { PAYMENT_CHANNEL_GROUPS } from "@/lib/payment-method-logos";
import { toast } from "vue-sonner";

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  attendee: {
    type: Object,
    required: true,
  },
  eventId: {
    type: [Number, String],
    required: true,
  },
});

const emit = defineEmits(["update:open", "success"]);

const client = useSanctumClient();
const channel = ref("");
const note = ref("");
const pending = ref(false);

// Reset the form each time the dialog opens.
watch(
  () => props.open,
  (open) => {
    if (open) {
      channel.value = "";
      note.value = "";
    }
  }
);

async function submit() {
  if (!channel.value) {
    return;
  }
  try {
    pending.value = true;
    const result = await client(
      `/api/events/${props.eventId}/ticket-orders/${props.attendee.order?.ulid}/mark-paid`,
      {
        method: "POST",
        body: {
          payment_channel: channel.value,
          note: note.value?.trim() || undefined,
        },
      }
    );
    toast.success(result.message || "Ticket order marked as paid", {
      description: props.attendee.order?.number,
    });
    // Close (unmount the teleported dialog) and let the DOM settle BEFORE the
    // parent refreshes the table - re-rendering rows while the overlay is still
    // tearing down trips a Vue teleport patch error.
    emit("update:open", false);
    await nextTick();
    emit("success");
  } catch (err) {
    toast.error("Failed to mark as paid", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    pending.value = false;
  }
}
</script>
