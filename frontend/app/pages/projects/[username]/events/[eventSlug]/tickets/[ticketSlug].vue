<template>
  <div class="flex flex-col gap-y-0">
    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <Empty v-else-if="!ticket" class="border-border border">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:ticket-01" class="size-6" />
        </EmptyMedia>
        <EmptyTitle>Ticket not found</EmptyTitle>
        <EmptyDescription>This ticket may have been removed or its link has changed.</EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button variant="outline" @click="navigateTo(`${ticketsBase}`)">
          <Icon name="lucide:arrow-left" class="size-4" />
          Back to tickets
        </Button>
      </EmptyContent>
    </Empty>

    <template v-else>
      <div class="mb-4">
        <ButtonBack :destination="`${ticketsBase}`" force-destination />
      </div>

      <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:items-start">
        <div class="bg-muted aspect-square w-24 shrink-0 overflow-hidden rounded-xl border sm:w-28">
          <img
            v-if="posterUrl"
            :src="posterUrl"
            :alt="ticketTitle"
            class="size-full object-cover"
            loading="lazy"
          />
          <div
            v-else
            class="text-muted-foreground flex size-full items-center justify-center"
          >
            <Icon name="hugeicons:ticket-01" class="size-6" />
          </div>
        </div>

        <div class="flex min-w-0 flex-col items-start gap-y-2">
          <p class="text-muted-foreground inline-flex items-center gap-1.5 text-xs tracking-tight sm:text-sm">
            <span class="truncate">{{ event?.title }}</span>
            <span aria-hidden="true">·</span>
            <span>Ticket</span>
          </p>

          <h1 class="text-xl font-semibold tracking-tighter sm:text-2xl">{{ ticketTitle }}</h1>

          <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="ticket.kind === 'entry' ? 'info' : 'muted'">
              {{ ticket.kind === "entry" ? "Entry" : "Add-on" }}
            </Badge>
            <Badge v-if="ticket.tier" variant="outline">
              {{ ticket.tier }}
            </Badge>
            <Badge :variant="ticket.is_active ? 'success' : 'muted'">
              {{ ticket.is_active ? "Active" : "Inactive" }}
            </Badge>
            <Badge v-if="ticket.purchase_type === 'external'" variant="outline">
              External
            </Badge>
          </div>
        </div>
      </div>

      <TabNav :tabs="ticketTabs" />

      <div class="pt-6">
        <NuxtPage :event="event" :project="project" :ticket="ticket" @refresh="refresh" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { TabNav } from "@/components/ui/tab-nav";
import { Spinner } from "@/components/ui/spinner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["tickets.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const ticketSlug = computed(() => route.params.ticketSlug);

const ticketsBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/tickets`
);

const base = computed(() => `${ticketsBase.value}/${ticketSlug.value}`);

const { data, pending, refresh } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/tickets/${ticketSlug.value}`,
  { key: () => `ticket-detail-${props.event?.id}-${ticketSlug.value}` }
);

const ticket = computed(() => data.value?.data);

const ticketTitle = computed(() => {
  const t = ticket.value?.title;
  if (!t) return "Ticket";
  if (typeof t === "string") return t;
  return t.en ?? Object.values(t)[0] ?? "Ticket";
});

const posterUrl = computed(() => {
  const poster = ticket.value?.poster;
  const p = Array.isArray(poster) ? poster[0] : poster;
  return p?.md || p?.sm || p?.url || null;
});

usePageMeta(null, {
  title: computed(() => `${ticketTitle.value} · Tickets`),
});

const ticketTabs = computed(() => {
  const tabs = [
    { label: "Details", icon: "hugeicons:ticket-01", to: base.value, exact: true },
    { label: "Pricing", icon: "hugeicons:money-bag-02", to: `${base.value}/pricing` },
  ];
  if (ticket.value?.kind === "add_on") {
    tabs.push({ label: "Sessions", icon: "hugeicons:calendar-03", to: `${base.value}/sessions` });
  }
  return tabs;
});
</script>
