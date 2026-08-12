<template>
  <div
    :data-item-id="field.id"
    class="bg-card flex items-center gap-x-3 rounded-xl border px-3 py-3"
  >
    <Icon
      v-if="draggable"
      name="hugeicons:drag-drop-vertical"
      class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
    />

    <div
      class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
    >
      <Icon :name="icon" class="size-4" />
    </div>

    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span class="truncate text-sm font-medium tracking-tight">{{ label }}</span>
        <slot name="badges" />
      </div>
      <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
        {{ typeLabel }}
        <template v-if="detail"> · {{ detail }}</template>
      </p>
    </div>

    <div class="flex shrink-0 items-center gap-1">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
/**
 * One row in a custom-field list.
 *
 * Brand fields, both event contexts, ops documents and the predefined-field
 * library each carried their own copy of this shell - same wrapper classes,
 * same 36px icon tile, same label-over-subtitle stack. Only the badges and the
 * trailing controls ever differed, so those are slots.
 *
 * The form builder deliberately does not use this: its row is a different
 * design (grid, the label is a button, a required switch, an actions menu, and
 * section indentation). See `form-builder/FieldCard.vue`.
 */
defineProps({
  field: { type: Object, required: true },
  /** Resolved label - hosts differ on translation fallback, so they pass it in. */
  label: { type: String, default: "" },
  icon: { type: String, required: true },
  typeLabel: { type: String, default: "" },
  /** Second line after the type, e.g. "3 options". */
  detail: { type: String, default: "" },
  draggable: { type: Boolean, default: true },
});
</script>
