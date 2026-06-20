<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl xl:max-w-5xl">
    <div class="space-y-2">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:agreement-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Business Matching</h1>
      </div>
      <p class="page-description">
        Define the custom intake fields shown to visitors who opt into Business Matching. Requires
        ticketing to be enabled.
      </p>
    </div>

    <!-- Enable toggle -->
    <div class="frame">
      <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
        <Icon name="hugeicons:agreement-02" class="mt-0.5 size-5 shrink-0" />
        <div class="min-w-0 flex-1 space-y-1">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold tracking-tight">Business Matching program</h3>
            <Skeleton v-if="loading" class="h-7 w-20 rounded-full" />
            <Badge v-else-if="enabled" variant="success" icon="hugeicons:checkmark-circle-02">Active</Badge>
            <Badge v-else variant="muted">Disabled</Badge>
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">
            Not every event runs Business Matching. Turn this on only when this event has the program.
          </p>
        </div>
      </div>

      <div class="frame-panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex-1 space-y-1 text-sm tracking-tight">
            <p class="font-medium">Enable Business Matching for this event</p>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              {{
                enabled
                  ? "The intake questions below are shown to buyers during ticket checkout."
                  : "Buyers won't see any Business Matching questions at checkout while this is off."
              }}
            </p>
          </div>
          <Switch :model-value="enabled" :disabled="loading || toggling" @update:model-value="onToggle" />
        </div>
      </div>
    </div>

    <CustomFieldsPanel :event="event" />
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import CustomFieldsPanel from "@/components/ticket/CustomFieldsPanel.vue";
import { computed, onMounted, ref } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["tickets.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const client = useSanctumClient();
const settingsUrl = computed(() => `/api/events/${props.event?.id}/ticket-settings`);

const enabled = ref(false);
const loading = ref(true);
const toggling = ref(false);

onMounted(async () => {
  try {
    const res = await client(settingsUrl.value);
    enabled.value = !!res?.data?.business_matching_enabled;
  } catch {
    enabled.value = false;
  } finally {
    loading.value = false;
  }
});

async function onToggle(next) {
  const previous = enabled.value;
  enabled.value = next;
  toggling.value = true;
  try {
    await client(settingsUrl.value, { method: "PUT", body: { business_matching_enabled: next } });
    toast.success(`Business Matching ${next ? "enabled" : "disabled"}`);
  } catch {
    enabled.value = previous;
    toast.error("Failed to update Business Matching");
  } finally {
    toggling.value = false;
  }
}

usePageMeta(null, {
  title: computed(() => `Business Matching · ${props.event?.title || "Event"}`),
});
</script>
