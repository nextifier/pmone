<template>
  <Collapsible v-model:open="isOpen" :disabled="locked">
    <div :class="['border-border overflow-hidden rounded-xl border', locked ? 'opacity-60' : '']">
      <CollapsibleTrigger as-child>
        <button
          class="flex w-full items-center gap-3 px-4 py-3 text-left sm:px-5 sm:py-4"
          :class="locked ? 'cursor-not-allowed' : 'hover:bg-muted/50'"
          :disabled="locked"
        >
          <!-- Step indicator -->
          <div
            :class="[
              'flex size-9 shrink-0 items-center justify-center rounded-full',
              locked
                ? 'bg-muted text-muted-foreground'
                : completed
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground',
            ]"
          >
            <Icon v-if="locked" name="hugeicons:lock-01" class="size-4" />
            <Icon v-else-if="completed" name="lucide:check" class="size-4" />
            <Icon v-else :name="icon" class="size-4" />
          </div>

          <!-- Title & summary -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <h3 class="page-title !text-foreground !text-base sm:!text-base">{{ title }}</h3>
              <Badge v-if="badgeText" :variant="badgeVariant" class="text-xs tracking-tight">
                {{ badgeText }}
              </Badge>
            </div>
            <p class="page-description">{{ summary }}</p>
          </div>

          <!-- Attention count -->
          <span
            v-if="attentionCount > 0 && !locked"
            class="flex size-5 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-medium tracking-tight text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
          >
            {{ attentionCount }}
          </span>

          <!-- Deadline -->
          <span
            v-if="deadline && !locked"
            :class="[
              'hidden text-xs font-medium tracking-tight sm:inline sm:text-sm',
              deadlineUrgent ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground',
            ]"
          >
            {{ deadline }}
          </span>

          <!-- Chevron -->
          <Icon
            v-if="!locked"
            name="hugeicons:arrow-down-01"
            :class="[
              'text-muted-foreground size-4 shrink-0 transition-transform duration-200',
              isOpen && 'rotate-180',
            ]"
          />
        </button>
      </CollapsibleTrigger>

      <CollapsibleContent>
        <div class="border-border border-t px-4 py-4 sm:px-5 sm:py-5">
          <slot />
        </div>
      </CollapsibleContent>
    </div>
  </Collapsible>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";

const props = defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: "hugeicons:circle" },
  summary: { type: String, default: "" },
  completed: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
  defaultOpen: { type: Boolean, default: false },
  badgeText: { type: String, default: "" },
  badgeVariant: { type: String, default: "outline" },
  attentionCount: { type: Number, default: 0 },
  deadline: { type: String, default: "" },
  deadlineUrgent: { type: Boolean, default: false },
  sectionKey: { type: String, default: "" },
});

const isOpen = defineModel("open", { type: Boolean, default: false });

onMounted(() => {
  if (props.defaultOpen) {
    isOpen.value = true;
  }
});
</script>
