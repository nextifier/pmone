<template>
  <div>
    <!-- Loading -->
    <div v-if="pending && !orders.length" class="space-y-4">
      <Skeleton v-for="i in 3" :key="i" class="h-40 w-full rounded-xl" />
    </div>

    <!-- Error -->
    <Empty v-else-if="error" class="border">
      <EmptyMedia variant="icon">
        <Icon name="hugeicons:alert-02" class="text-destructive size-6" />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>Couldn't load your orders</EmptyTitle>
        <EmptyDescription>Something went wrong. Please try again.</EmptyDescription>
      </EmptyHeader>
      <Button variant="outline" size="sm" @click="fetchOrders">
        <Icon name="hugeicons:reload" class="size-4" />
        <span>Try again</span>
      </Button>
    </Empty>

    <!-- Empty -->
    <Empty v-else-if="!orders.length" class="border-dashed">
      <EmptyMedia variant="icon">
        <Icon name="hugeicons:shopping-bag-02" class="size-6" />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>You haven't placed any orders yet.</EmptyTitle>
        <EmptyDescription>
          Orders you make for event tickets will appear here, where you can manage attendees.
        </EmptyDescription>
      </EmptyHeader>
    </Empty>

    <!-- Orders -->
    <div v-else class="space-y-4">
      <div
        v-for="order in orders"
        :key="order.ulid"
        class="border-border bg-card rounded-xl border p-4 sm:p-5"
      >
        <div class="flex flex-col gap-y-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <p class="font-medium tracking-tight">{{ order.order_number }}</p>
              <span
                class="flex items-center gap-x-1 rounded-full border px-2.5 py-0.5 text-xs tracking-tight"
                :class="statusClass(order.status)"
              >
                {{ statusLabel(order.status) }}
              </span>
            </div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              {{ formatDateTime(order.created_at) }}
            </p>
          </div>

          <div class="flex items-center gap-x-3 sm:flex-col sm:items-end sm:gap-y-1">
            <p class="text-lg font-medium tracking-tighter">
              {{ order.is_free ? "Free" : formatPrice(order.total) }}
            </p>
            <p
              v-if="order.attendees?.length"
              class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >
              {{ order.attendees.length }}
              {{ order.attendees.length === 1 ? "attendee" : "attendees" }}
            </p>
          </div>
        </div>

        <!-- Pending payment CTA -->
        <div
          v-if="order.status === 'pending_payment' && order.payment_url"
          class="mt-4 flex flex-col gap-y-2 sm:flex-row sm:items-center sm:justify-between"
        >
          <p
            v-if="order.payment_expires_at"
            class="text-muted-foreground text-xs tracking-tight sm:text-sm"
          >
            Payment due by {{ formatDateTime(order.payment_expires_at) }}
          </p>
          <Button as-child size="sm" class="sm:ml-auto">
            <a :href="order.payment_url" target="_blank" rel="noopener">
              <Icon name="hugeicons:credit-card" class="size-4" />
              <span>Complete payment</span>
            </a>
          </Button>
        </div>

        <!-- Attendees toggle -->
        <div v-if="order.attendees?.length" class="mt-4 border-t pt-4">
          <Button
            variant="ghost"
            size="sm"
            class="text-muted-foreground -ml-2"
            :aria-expanded="!!expanded[order.ulid]"
            @click="toggle(order.ulid)"
          >
            <Icon
              name="hugeicons:arrow-down-01"
              class="size-4 transition-transform duration-200 ease-out motion-reduce:transition-none"
              :class="expanded[order.ulid] ? 'rotate-180' : ''"
            />
            <span>{{ expanded[order.ulid] ? "Hide attendees" : "Manage attendees" }}</span>
          </Button>

          <div v-if="expanded[order.ulid]" class="mt-3 divide-y">
            <div
              v-for="attendee in order.attendees"
              :key="attendee.ulid"
              class="flex items-center justify-between gap-x-3 py-3"
            >
              <div class="min-w-0 space-y-0.5">
                <p class="truncate text-sm font-medium tracking-tight">
                  {{ attendee.name || "Unassigned" }}
                </p>
                <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
                  {{ attendee.ticket?.title }}
                  <span
                    v-if="attendee.is_checked_in"
                    class="text-success-foreground"
                  >
                    · Checked in
                  </span>
                </p>
              </div>
              <Button
                variant="outline"
                size="sm"
                :disabled="attendee.is_checked_in"
                @click="openEdit(attendee)"
              >
                <Icon name="hugeicons:user-edit-01" class="size-4" />
                <span class="hidden sm:inline">Edit attendee</span>
              </Button>
            </div>
          </div>
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
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const client = useSanctumClient();
const { formatDateTime, formatPrice } = useFormatters();

const orders = ref([]);
const pending = ref(true);
const error = ref(null);
const expanded = reactive({});

const fetchOrders = async () => {
  pending.value = true;
  error.value = null;
  try {
    const response = await client("/api/my/ticket-orders");
    orders.value = response?.data || [];
  } catch (err) {
    error.value = err;
  } finally {
    pending.value = false;
  }
};

onMounted(fetchOrders);

const toggle = (ulid) => {
  expanded[ulid] = !expanded[ulid];
};

const statusLabel = (status) => {
  const map = {
    confirmed: "Confirmed",
    pending_payment: "Pending payment",
    expired: "Expired",
    cancelled: "Cancelled",
  };
  return map[status] || status;
};

const statusClass = (status) => {
  const map = {
    confirmed: "bg-success/10 text-success-foreground border-success/20",
    pending_payment: "bg-warning/10 text-warning-foreground border-warning/20",
    expired: "bg-muted text-muted-foreground border-border",
    cancelled: "bg-destructive/10 text-destructive border-destructive/20",
  };
  return map[status] || "bg-muted text-muted-foreground border-border";
};

const editOpen = ref(false);
const editingAttendee = ref(null);

const openEdit = (attendee) => {
  editingAttendee.value = attendee;
  editOpen.value = true;
};

const handleSaved = (updated) => {
  if (!updated) {
    fetchOrders();
    return;
  }
  for (const order of orders.value) {
    const attendee = order.attendees?.find((a) => a.ulid === updated.ulid);
    if (attendee) {
      Object.assign(attendee, updated);
    }
  }
};

usePageMeta(null, { title: "My Orders" });
</script>
