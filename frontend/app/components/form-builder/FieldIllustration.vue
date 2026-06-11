<template>
  <!-- Skeleton mockups that preview each field type, using the same visual
       language as the /ui illustration components (muted skeleton bars + accents). -->

  <!-- Short Text -->
  <div v-if="type === 'text'" class="w-full max-w-28 space-y-1.5">
    <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
    <div class="border-border bg-card h-5 rounded-md border" />
  </div>

  <!-- Long Text -->
  <div v-else-if="type === 'textarea'" class="w-full max-w-28 space-y-1.5">
    <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
    <div class="border-border bg-card flex h-8 flex-col gap-1 rounded-md border p-1.5">
      <div class="bg-muted-foreground/15 h-1 w-full rounded-full" />
      <div class="bg-muted-foreground/15 h-1 w-2/3 rounded-full" />
    </div>
  </div>

  <!-- Rich Text -->
  <div v-else-if="type === 'rich_text'" class="border-border bg-card w-full max-w-28 overflow-hidden rounded-md border">
    <div class="border-border flex items-center gap-1 border-b px-1.5 py-1">
      <div class="bg-muted-foreground/40 size-1.5 rounded-sm" />
      <div class="bg-muted-foreground/40 size-1.5 rounded-sm" />
      <div class="bg-muted-foreground/40 size-1.5 rounded-sm" />
    </div>
    <div class="space-y-1 p-1.5">
      <div class="bg-muted-foreground/15 h-1 w-full rounded-full" />
      <div class="bg-muted-foreground/15 h-1 w-1/2 rounded-full" />
    </div>
  </div>

  <!-- Email / Phone / Link (input with leading icon) -->
  <div
    v-else-if="['email', 'phone', 'url'].includes(type)"
    class="border-border bg-card flex w-full max-w-28 items-center gap-1.5 rounded-md border px-2 py-1.5"
  >
    <Icon :name="leadingIcon" class="text-muted-foreground/60 size-3.5 shrink-0" />
    <div class="bg-muted-foreground/20 h-1.5 flex-1 rounded-full" />
  </div>

  <!-- Dropdown -->
  <div
    v-else-if="type === 'select'"
    class="border-border bg-card flex w-full max-w-28 items-center gap-1.5 rounded-md border px-2 py-1.5"
  >
    <div class="bg-muted-foreground/20 h-1.5 flex-1 rounded-full" />
    <Icon name="lucide:chevrons-up-down" class="text-muted-foreground/60 size-3.5 shrink-0" />
  </div>

  <!-- Multi Select -->
  <div
    v-else-if="type === 'multi_select'"
    class="border-border bg-card flex w-full max-w-28 items-center gap-1 rounded-md border px-1.5 py-1.5"
  >
    <div class="bg-muted-foreground/15 h-3 w-6 rounded" />
    <div class="bg-muted-foreground/15 h-3 w-5 rounded" />
    <Icon name="lucide:chevrons-up-down" class="text-muted-foreground/60 ml-auto size-3.5 shrink-0" />
  </div>

  <!-- Radio -->
  <div v-else-if="type === 'radio'" class="w-full max-w-28 space-y-1.5">
    <div v-for="i in 3" :key="i" class="flex items-center gap-1.5">
      <div
        class="size-2.5 shrink-0 rounded-full border"
        :class="i === 1 ? 'border-primary border-[3px]' : 'border-muted-foreground/40'"
      />
      <div class="bg-muted-foreground/25 h-1.5 flex-1 rounded-full" />
    </div>
  </div>

  <!-- Checkbox (single inline) -->
  <div v-else-if="type === 'checkbox'" class="flex w-full max-w-28 items-center gap-1.5">
    <div class="bg-primary flex size-3 shrink-0 items-center justify-center rounded-sm">
      <Icon name="lucide:check" class="text-primary-foreground size-2" />
    </div>
    <div class="bg-muted-foreground/25 h-1.5 flex-1 rounded-full" />
  </div>

  <!-- Checkboxes (group) -->
  <div v-else-if="type === 'checkbox_group'" class="w-full max-w-28 space-y-1.5">
    <div v-for="i in 3" :key="i" class="flex items-center gap-1.5">
      <div
        class="flex size-2.5 shrink-0 items-center justify-center rounded-sm border"
        :class="i === 1 ? 'bg-primary border-primary' : 'border-muted-foreground/40'"
      >
        <Icon v-if="i === 1" name="lucide:check" class="text-primary-foreground size-1.5" />
      </div>
      <div class="bg-muted-foreground/25 h-1.5 flex-1 rounded-full" />
    </div>
  </div>

  <!-- Switch -->
  <div v-else-if="type === 'switch'" class="flex w-full max-w-28 items-center gap-2">
    <div class="bg-primary flex h-3 w-5 shrink-0 items-center rounded-full px-0.5">
      <div class="bg-background ml-auto size-2 rounded-full" />
    </div>
    <div class="bg-muted-foreground/25 h-1.5 flex-1 rounded-full" />
  </div>

  <!-- Tags -->
  <div
    v-else-if="type === 'tags'"
    class="border-border bg-card flex w-full max-w-28 flex-wrap items-center gap-1 rounded-md border p-1.5"
  >
    <div class="bg-muted-foreground/15 h-3 w-7 rounded" />
    <div class="bg-muted-foreground/15 h-3 w-5 rounded" />
    <div class="bg-muted-foreground/15 h-3 w-6 rounded" />
  </div>

  <!-- Country -->
  <div
    v-else-if="type === 'country'"
    class="border-border bg-card flex w-full max-w-28 items-center gap-1.5 rounded-md border px-2 py-1.5"
  >
    <Icon name="lucide:globe" class="text-muted-foreground/60 size-3.5 shrink-0" />
    <div class="bg-muted-foreground/20 h-1.5 flex-1 rounded-full" />
    <Icon name="lucide:chevrons-up-down" class="text-muted-foreground/60 size-3.5 shrink-0" />
  </div>

  <!-- Date / Time / Date & Time (input with trailing icon) -->
  <div
    v-else-if="['date', 'time', 'datetime'].includes(type)"
    class="border-border bg-card flex w-full max-w-28 items-center gap-1.5 rounded-md border px-2 py-1.5"
  >
    <div class="bg-muted-foreground/20 h-1.5 flex-1 rounded-full" />
    <Icon :name="trailingIcon" class="text-muted-foreground/60 size-3.5 shrink-0" />
  </div>

  <!-- Date Range -->
  <div v-else-if="type === 'date_range'" class="flex w-full max-w-28 items-center gap-1.5">
    <div class="border-border bg-card flex h-5 flex-1 items-center gap-1 rounded-md border px-1.5">
      <Icon name="lucide:calendar" class="text-muted-foreground/60 size-2.5 shrink-0" />
      <div class="bg-muted-foreground/20 h-1 flex-1 rounded-full" />
    </div>
    <Icon name="lucide:arrow-right" class="text-muted-foreground/50 size-3 shrink-0" />
    <div class="border-border bg-card flex h-5 flex-1 items-center gap-1 rounded-md border px-1.5">
      <Icon name="lucide:calendar" class="text-muted-foreground/60 size-2.5 shrink-0" />
      <div class="bg-muted-foreground/20 h-1 flex-1 rounded-full" />
    </div>
  </div>

  <!-- Number -->
  <div
    v-else-if="type === 'number'"
    class="border-border bg-card flex w-full max-w-28 items-center gap-1.5 rounded-md border px-2 py-1.5"
  >
    <Icon name="lucide:hash" class="text-muted-foreground/60 size-3.5 shrink-0" />
    <div class="bg-muted-foreground/20 h-1.5 w-8 rounded-full" />
  </div>

  <!-- Slider -->
  <div v-else-if="type === 'slider'" class="flex w-full max-w-28 items-center">
    <div class="bg-muted-foreground/20 relative h-1.5 w-full rounded-full">
      <div class="bg-primary absolute inset-y-0 left-0 w-3/5 rounded-full" />
      <div
        class="border-border bg-card absolute top-1/2 left-3/5 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border shadow-xs"
      />
    </div>
  </div>

  <!-- Rating -->
  <div v-else-if="type === 'rating'" class="flex max-w-28 items-center gap-0.5">
    <Icon
      v-for="i in 5"
      :key="i"
      name="lucide:star"
      class="size-4"
      :class="i <= 3 ? 'text-warning fill-warning' : 'text-muted-foreground/25'"
    />
  </div>

  <!-- Linear Scale -->
  <div v-else-if="type === 'linear_scale'" class="flex max-w-28 items-center gap-1">
    <div
      v-for="n in 5"
      :key="n"
      class="flex size-4 items-center justify-center rounded border"
      :class="n === 4 ? 'border-primary bg-primary' : 'border-border'"
    >
      <div class="h-0.5 w-1 rounded-full" :class="n === 4 ? 'bg-primary-foreground' : 'bg-muted-foreground/40'" />
    </div>
  </div>

  <!-- File Upload -->
  <div
    v-else-if="type === 'file'"
    class="border-muted-foreground/30 flex w-full max-w-28 flex-col items-center gap-1 rounded-md border border-dashed px-2 py-2.5"
  >
    <Icon name="lucide:paperclip" class="text-muted-foreground/60 size-4" />
    <div class="bg-muted-foreground/20 h-1 w-10 rounded-full" />
  </div>

  <!-- Color -->
  <div v-else-if="type === 'color'" class="flex w-full max-w-28 items-center gap-1.5">
    <div class="bg-primary border-border size-6 shrink-0 rounded-md border" />
    <div class="border-border bg-card flex h-6 flex-1 items-center rounded-md border px-2">
      <div class="bg-muted-foreground/20 h-1.5 w-10 rounded-full" />
    </div>
  </div>

  <!-- Section (layout heading) -->
  <div v-else-if="type === 'section'" class="w-full max-w-28 space-y-1.5">
    <div class="bg-muted-foreground/60 h-2 w-14 rounded-full" />
    <div class="border-border border-t" />
    <div class="bg-muted-foreground/20 h-1 w-full rounded-full" />
    <div class="bg-muted-foreground/20 h-1 w-2/3 rounded-full" />
  </div>

  <!-- Fallback: the type's icon -->
  <Icon v-else :name="fallbackIcon" class="text-muted-foreground/60 size-6" />
</template>

<script setup>
import { getTypeIcon } from "@/lib/formFieldTypes";

const props = defineProps({
  type: { type: String, required: true },
});

const leadingIcon = computed(
  () => ({ email: "lucide:at-sign", phone: "lucide:phone", url: "lucide:link" })[props.type] || "lucide:type"
);

const trailingIcon = computed(
  () =>
    ({ date: "lucide:calendar", time: "lucide:clock", datetime: "lucide:calendar-clock" })[props.type] ||
    "lucide:calendar"
);

const fallbackIcon = computed(() => getTypeIcon(props.type));
</script>
