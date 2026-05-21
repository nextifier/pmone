<template>
  <DialogResponsive v-model:open="open" dialog-max-width="52rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">Webhook events</h3>
        <p class="page-description">
          Inbound <span class="capitalize">{{ gateway?.provider }}</span> webhooks received by PM
          One. Click a row to inspect the payload.
        </p>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <div class="relative flex min-w-44 flex-1 items-center">
          <Icon
            name="lucide:search"
            class="text-muted-foreground pointer-events-none absolute left-3 size-4"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by reference..."
            class="placeholder:text-muted-foreground h-8 w-full rounded-md border bg-transparent px-9 text-sm tracking-tight focus:outline-hidden"
          />
          <button
            v-if="searchQuery"
            type="button"
            aria-label="Clear search"
            class="bg-muted hover:bg-border absolute right-2 flex size-5 items-center justify-center rounded-full"
            @click="searchQuery = ''"
          >
            <Icon name="lucide:x" class="size-3 shrink-0" />
          </button>
        </div>

        <Select v-model="filterStatus">
          <SelectTrigger class="h-8 w-auto min-w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="processed">Processed</SelectItem>
            <SelectItem value="ignored">Ignored</SelectItem>
            <SelectItem value="rejected">Rejected</SelectItem>
            <SelectItem value="error">Error</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="mt-4 max-h-[55vh] overflow-y-auto">
        <div v-if="loading && !events.length" class="space-y-2">
          <Skeleton v-for="i in 6" :key="i" class="h-10 w-full" />
        </div>

        <div
          v-else-if="error && !events.length"
          class="border-destructive/40 bg-destructive/10 flex items-start gap-x-2 rounded-lg border p-3"
        >
          <Icon
            name="hugeicons:alert-circle"
            class="text-destructive mt-0.5 size-4 shrink-0"
          />
          <div class="flex-1 space-y-2">
            <p class="text-destructive text-sm tracking-tight">{{ error }}</p>
            <Button variant="outline" size="sm" @click="fetchPage({ reset: true })">
              Try again
            </Button>
          </div>
        </div>

        <Empty v-else-if="!filteredEvents.length" class="border">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Icon name="hugeicons:notification-01" />
            </EmptyMedia>
            <EmptyTitle>No webhook events</EmptyTitle>
            <EmptyDescription>
              No webhook events match the current filters.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>

        <div v-else class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="whitespace-nowrap">Received</TableHead>
                <TableHead class="whitespace-nowrap">Event</TableHead>
                <TableHead class="whitespace-nowrap">Reference</TableHead>
                <TableHead class="whitespace-nowrap">Status</TableHead>
                <TableHead class="whitespace-nowrap text-right">HTTP</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <template v-for="event in filteredEvents" :key="event.ulid">
                <TableRow class="cursor-pointer" @click="toggle(event.ulid)">
                  <TableCell class="text-muted-foreground whitespace-nowrap">
                    {{ formatDateTime(event.created_at) }}
                  </TableCell>
                  <TableCell class="whitespace-nowrap">{{ event.event_type || "-" }}</TableCell>
                  <TableCell>
                    <div
                      class="no-scrollbar scroll-fade-x max-w-40 overflow-x-scroll whitespace-nowrap"
                    >
                      {{ event.external_id || "-" }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge :variant="statusVariant(event.status)" with-icon plain>
                      {{ statusLabel(event.status) }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right font-medium whitespace-nowrap">
                    {{ event.http_status || "-" }}
                  </TableCell>
                </TableRow>
                <TableRow v-if="expandedId === event.ulid" class="hover:bg-transparent">
                  <TableCell :colspan="5" class="bg-muted/30">
                    <div class="space-y-2 py-1">
                      <p v-if="event.message" class="text-sm tracking-tight">
                        <span class="text-muted-foreground">Message:</span> {{ event.message }}
                      </p>
                      <p
                        v-if="event.ip_address"
                        class="text-muted-foreground text-xs tracking-tight sm:text-sm"
                      >
                        IP: {{ event.ip_address }}
                      </p>
                      <pre
                        class="bg-background border-border max-h-64 overflow-auto rounded-md border p-2 font-mono text-xs"
                        >{{ prettyPayload(event.payload) }}</pre
                      >
                    </div>
                  </TableCell>
                </TableRow>
              </template>
            </TableBody>
          </Table>

          <div v-if="hasMore" class="mt-3 flex justify-center">
            <Button
              variant="outline"
              size="sm"
              :disabled="loadingMore"
              @click="loadMore"
            >
              <Spinner v-if="loadingMore" />
              Load more
            </Button>
          </div>
        </div>
      </div>
    </div>
  </DialogResponsive>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { toast } from "vue-sonner";

const props = defineProps({
  projectUsername: { type: String, required: true },
  gateway: { type: Object, default: null },
});

const open = defineModel("open", { type: Boolean, default: false });

const client = useSanctumClient();
const { formatDateTime } = useFormatters();

const events = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const error = ref(null);
const hasMore = ref(false);
const page = ref(1);
const expandedId = ref(null);
const filterStatus = ref("all");
const searchQuery = ref("");

// Client-side substring search over the loaded rows, matching the reference
// and event type - same behaviour as the transactions dialog search.
const filteredEvents = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return events.value;
  return events.value.filter(
    (e) =>
      (e.external_id || "").toLowerCase().includes(q) ||
      (e.event_type || "").toLowerCase().includes(q)
  );
});

function statusVariant(status) {
  const map = {
    processed: "success",
    ignored: "muted",
    rejected: "destructive",
    error: "destructive",
  };
  return map[status] || "muted";
}

function statusLabel(status) {
  const s = (status || "").toLowerCase();
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : "-";
}

function prettyPayload(payload) {
  try {
    return JSON.stringify(payload, null, 2);
  } catch {
    return String(payload);
  }
}

function toggle(ulid) {
  expandedId.value = expandedId.value === ulid ? null : ulid;
}

async function fetchPage({ reset }) {
  if (!props.gateway) return;

  if (reset) {
    loading.value = true;
    error.value = null;
    page.value = 1;
  } else {
    loadingMore.value = true;
  }

  try {
    const query = { page: page.value };
    if (filterStatus.value !== "all") query.status = filterStatus.value;

    const res = await client(
      `/api/projects/${props.projectUsername}/payment-gateways/${props.gateway.id}/webhook-events`,
      { query }
    );

    const rows = res.data || [];
    events.value = reset ? rows : [...events.value, ...rows];
    const meta = res.meta || {};
    hasMore.value = (meta.current_page || 1) < (meta.last_page || 1);
  } catch (e) {
    const message = e?.data?.message || "Failed to load webhook events.";
    if (reset) {
      error.value = message;
    } else {
      toast.error(message);
    }
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function loadMore() {
  page.value += 1;
  fetchPage({ reset: false });
}

watch(filterStatus, () => {
  if (open.value) fetchPage({ reset: true });
});

watch(open, (isOpen) => {
  if (isOpen && props.gateway) {
    searchQuery.value = "";
    events.value = [];
    expandedId.value = null;
    fetchPage({ reset: true });
  }
});
</script>
