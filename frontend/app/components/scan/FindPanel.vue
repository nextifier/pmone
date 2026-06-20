<template>
  <section class="space-y-4">
    <div class="space-y-2">
      <Label for="scan-search">Find a visitor</Label>
      <div class="relative">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <Input
          id="scan-search"
          v-model="searchQuery"
          class="pl-9"
          :class="searchQuery ? 'pr-10' : ''"
          placeholder="Search by name, email, phone, or order number"
          autocomplete="off"
        />
        <Button
          v-if="searchQuery"
          variant="ghost"
          size="iconSm"
          class="absolute top-1/2 right-1.5 size-7 -translate-y-1/2"
          aria-label="Clear"
          @click="searchQuery = ''"
        >
          <Icon name="hugeicons:cancel-01" class="size-4" />
        </Button>
      </div>
      <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
        Manual lookup for visitors without a scannable QR.
      </p>
    </div>

    <div v-if="searchPending" class="space-y-2">
      <Skeleton v-for="i in 3" :key="`s-${i}`" class="h-16 w-full rounded-xl" />
    </div>

    <div
      v-else-if="searchQuery.length >= 2 && searchResults.length === 0"
      class="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm tracking-tight"
    >
      No matching attendees found.
    </div>

    <ul v-else-if="searchResults.length" class="space-y-2">
      <li
        v-for="att in searchResults"
        :key="att.ulid"
        class="bg-card flex items-center gap-x-3 rounded-xl border p-3"
      >
        <div class="bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg">
          <Icon name="hugeicons:user" class="text-muted-foreground size-4" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium tracking-tight">{{ att.name || "-" }}</p>
          <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
            <span>{{ att.title || "Ticket" }}</span>
            <span v-if="att.tier"> · {{ att.tier }}</span>
          </p>
        </div>
        <Badge v-if="att.checked_in_at" variant="success" plain class="shrink-0">
          Checked in
        </Badge>
        <Button
          v-else
          size="sm"
          :disabled="manualBusy === att.ulid"
          @click="manualCheckIn(att)"
        >
          <Spinner v-if="manualBusy === att.ulid" />
          <span>Check in</span>
        </Button>
      </li>
    </ul>

    <div
      v-else
      class="text-muted-foreground flex flex-col items-center justify-center gap-y-2 py-12 text-center"
    >
      <Icon name="hugeicons:search-01" class="size-10 opacity-40" />
      <p class="max-w-xs text-sm tracking-tight">
        Search by name, email, phone, or order number to check a visitor in manually.
      </p>
    </div>
  </section>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { SCAN_SESSION } from "@/composables/scanSessionKey";

const session = inject(SCAN_SESSION);

const { searchQuery, searchResults, searchPending, manualBusy, manualCheckIn } = session;
</script>
