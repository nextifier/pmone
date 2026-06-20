<template>
  <div>
    <!-- Loading -->
    <div v-if="pending && !tickets.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Skeleton v-for="i in 4" :key="i" class="h-64 w-full rounded-xl" />
    </div>

    <!-- Error -->
    <Empty v-else-if="error" class="border">
      <EmptyMedia variant="icon">
        <Icon name="hugeicons:alert-02" class="text-destructive size-6" />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>Couldn't load your tickets</EmptyTitle>
        <EmptyDescription>Something went wrong. Please try again.</EmptyDescription>
      </EmptyHeader>
      <Button variant="outline" size="sm" @click="fetchTickets">
        <Icon name="hugeicons:reload" class="size-4" />
        <span>Try again</span>
      </Button>
    </Empty>

    <!-- Empty -->
    <Empty v-else-if="!tickets.length" class="border-dashed">
      <EmptyMedia variant="icon">
        <Icon name="hugeicons:ticket-01" class="size-6" />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>You don't have any tickets yet.</EmptyTitle>
        <EmptyDescription>
          Tickets you hold for an event will appear here, ready to scan at check-in.
        </EmptyDescription>
      </EmptyHeader>
    </Empty>

    <!-- Tickets -->
    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div
        v-for="item in tickets"
        :key="item.attendee.ulid"
        class="border-border bg-card flex flex-col gap-y-4 rounded-xl border p-4 sm:p-5"
      >
        <div class="flex items-start justify-between gap-x-2">
          <div class="min-w-0 space-y-0.5">
            <p class="text-muted-foreground truncate text-sm tracking-tight">
              {{ item.event?.title }}
            </p>
            <p class="text-lg font-medium tracking-tighter">
              {{ item.attendee.ticket?.title }}
            </p>
          </div>
          <span
            v-if="item.attendee.ticket?.tier"
            class="bg-muted text-foreground shrink-0 rounded-full px-2.5 py-1 text-xs tracking-tight"
          >
            {{ item.attendee.ticket.tier }}
          </span>
        </div>

        <div class="bg-muted/40 flex items-center justify-center rounded-xl p-4">
          <TicketQr :token="item.attendee.qr_token" container-class="w-full max-w-[180px]" />
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between gap-x-2">
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Holder</p>
              <p class="truncate text-sm font-medium tracking-tight">
                {{ item.attendee.name || "Unassigned" }}
              </p>
            </div>
            <span
              v-if="item.attendee.is_checked_in"
              class="bg-success/10 text-success-foreground border-success/20 flex shrink-0 items-center gap-x-1 rounded-full border px-2.5 py-1 text-xs tracking-tight"
            >
              <Icon name="hugeicons:checkmark-badge-01" class="size-3.5" />
              <span>Checked in</span>
            </span>
          </div>

          <p
            v-if="item.attendee.is_checked_in && item.attendee.checked_in_at"
            class="text-muted-foreground text-xs tracking-tight sm:text-sm"
          >
            {{ formatDateTime(item.attendee.checked_in_at) }}
          </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
          <Button
            variant="outline"
            size="sm"
            class="w-full"
            :disabled="item.attendee.is_checked_in"
            @click="openEdit(item.attendee)"
          >
            <Icon name="hugeicons:user-edit-01" class="size-4" />
            <span>{{ item.attendee.is_personalized ? "Edit" : "Personalize" }}</span>
          </Button>
          <Button
            v-if="item.event?.website_url"
            variant="outline"
            size="sm"
            class="w-full"
            @click="copyLink(item)"
          >
            <Icon name="hugeicons:link-01" class="size-4" />
            <span>Copy link</span>
          </Button>
        </div>
      </div>
    </div>

    <EditAttendeeDialog
      v-model:open="editOpen"
      :attendee="editingAttendee"
      @saved="handleSaved"
    />
  </div>
</template>

<script setup>
import EditAttendeeDialog from "@/components/EditAttendeeDialog.vue";
import TicketQr from "@/components/TicketQr.vue";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const client = useSanctumClient();
const { formatDateTime } = useFormatters();

const tickets = ref([]);
const pending = ref(true);
const error = ref(null);

const fetchTickets = async () => {
  pending.value = true;
  error.value = null;
  try {
    const response = await client("/api/my/tickets");
    tickets.value = response?.data || [];
  } catch (err) {
    error.value = err;
  } finally {
    pending.value = false;
  }
};

onMounted(fetchTickets);

const editOpen = ref(false);
const editingAttendee = ref(null);

const openEdit = (attendee) => {
  editingAttendee.value = attendee;
  editOpen.value = true;
};

const copyLink = async (item) => {
  const base = (item.event?.website_url || "").replace(/\/$/, "");
  if (!base) return;
  try {
    await navigator.clipboard.writeText(`${base}/tickets/${item.attendee.ulid}`);
    toast.success("Ticket link copied");
  } catch {
    toast.error("Could not copy link");
  }
};

const handleSaved = (updated) => {
  if (!updated) {
    fetchTickets();
    return;
  }
  const item = tickets.value.find((t) => t.attendee.ulid === updated.ulid);
  if (item) {
    item.attendee = { ...item.attendee, ...updated };
  }
};

usePageMeta(null, { title: "My Tickets" });
</script>
