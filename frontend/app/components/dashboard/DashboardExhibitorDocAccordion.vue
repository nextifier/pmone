<template>
  <Collapsible v-model:open="isOpen">
    <CollapsibleTrigger as-child>
      <button
        type="button"
        class="hover:bg-muted/50 flex w-full items-center gap-3 px-4 py-4 text-left sm:px-5"
      >
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <h4 class="text-base font-medium tracking-tight">{{ doc.title }}</h4>
            <!-- One badge, most urgent wins, so a collapsed row stays readable -->
            <Badge
              v-if="status === 'completed'"
              variant="success"
              icon="hugeicons:checkmark-circle-02"
              class="text-sm font-normal tracking-tight"
            >
              {{ $t("ed.docs.completed") }}
            </Badge>
            <Badge
              v-else-if="status === 'needs_reagreement'"
              variant="warning"
              class="text-sm font-normal tracking-tight"
            >
              {{ $t("ed.docs.updated") }}
            </Badge>
            <Badge
              v-else-if="isOverdue"
              variant="destructive"
              class="text-sm font-normal tracking-tight"
            >
              {{ $t("ed.docs.overdue") }}
            </Badge>
            <Badge
              v-else-if="doc.is_required"
              variant="outline"
              class="text-sm font-normal tracking-tight"
            >
              {{ $t("ed.docs.required") }}
            </Badge>
          </div>
        </div>

        <Icon
          name="hugeicons:arrow-down-01"
          :class="[
            'text-muted-foreground size-4 shrink-0 transition-transform duration-200',
            isOpen && 'rotate-180',
          ]"
        />
      </button>
    </CollapsibleTrigger>

    <CollapsibleContent class="motion-reduce:animate-none">
      <div class="px-4 pb-6 sm:px-5">
        <slot />
      </div>
    </CollapsibleContent>
  </Collapsible>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";

const props = defineProps({
  doc: { type: Object, required: true },
  status: { type: String, default: "pending" },
});

const isOpen = defineModel("open", { type: Boolean, default: false });

// Same rule as the item body: trust the server clock, fall back to the browser
// only for an API that predates `is_overdue`.
const isOverdue = computed(() => {
  if (typeof props.doc.is_overdue === "boolean") return props.doc.is_overdue;
  if (!props.doc.submission_deadline) return false;
  return new Date() > new Date(props.doc.submission_deadline);
});
</script>
