<template>
  <div class="mx-auto w-full max-w-5xl space-y-8 px-4 py-8 sm:py-10">
    <header class="space-y-1.5">
      <h1 class="page-title">{{ greeting }}</h1>
      <p class="page-description text-base">
        Your tickets, orders, and account in one place.
      </p>
    </header>

    <DashboardAnnouncement class="w-full" />

    <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
      <DashboardStatsCard
        title="My Tickets"
        description="Tickets you hold"
        icon="hugeicons:ticket-01"
        icon-color="text-primary"
        :value="tickets.length"
        :loading="pending"
        href="/account/tickets"
      />
      <DashboardStatsCard
        title="Orders"
        description="Purchases you've made"
        icon="hugeicons:shopping-bag-02"
        :value="orders.length"
        :loading="pending"
        href="/account/orders"
      />
      <DashboardStatsCard
        title="Checked in"
        description="Scanned at events"
        icon="hugeicons:checkmark-badge-01"
        icon-color="text-success"
        :value="checkedInCount"
        :loading="pending"
        href="/account/tickets"
      />
    </div>

    <section class="space-y-3">
      <div class="flex items-center justify-between gap-2">
        <h2 class="text-foreground text-lg font-medium tracking-tighter">My Tickets</h2>
        <Button v-if="tickets.length" as-child variant="ghost" size="sm">
          <NuxtLink to="/account/tickets">
            <span>View all</span>
            <Icon name="hugeicons:arrow-right-01" class="size-4" />
          </NuxtLink>
        </Button>
      </div>

      <div v-if="pending" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Skeleton v-for="i in 3" :key="i" class="h-28 w-full rounded-xl" />
      </div>

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

      <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <NuxtLink
          v-for="item in previewTickets"
          :key="item.attendee.ulid"
          to="/account/tickets"
          class="border-border bg-card hover:border-foreground/20 flex flex-col gap-y-3 rounded-xl border p-4 transition-colors"
        >
          <div class="flex items-start justify-between gap-x-2">
            <div class="min-w-0 space-y-0.5">
              <p class="text-muted-foreground truncate text-sm tracking-tight">
                {{ item.event?.title }}
              </p>
              <p class="truncate font-medium tracking-tighter">
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
          <div class="mt-auto flex items-center justify-between gap-x-2">
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs tracking-tight">Holder</p>
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
        </NuxtLink>
      </div>
    </section>
  </div>
</template>

<script setup>
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";

const client = useSanctumClient();
const user = useSanctumUser();

const tickets = ref([]);
const orders = ref([]);
const pending = ref(true);

const greeting = computed(() => {
  const name = user.value?.name?.split(" ")?.[0];
  return name ? `Welcome back, ${name}` : "Welcome back";
});

const checkedInCount = computed(
  () => tickets.value.filter((t) => t.attendee?.is_checked_in).length
);
const previewTickets = computed(() => tickets.value.slice(0, 6));

onMounted(async () => {
  try {
    const [t, o] = await Promise.all([
      client("/api/my/tickets").catch(() => ({ data: [] })),
      client("/api/my/ticket-orders").catch(() => ({ data: [] })),
    ]);
    tickets.value = t?.data || [];
    orders.value = o?.data || [];
  } finally {
    pending.value = false;
  }
});
</script>
