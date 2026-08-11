<template>
  <div
    class="grid grid-cols-[auto_1fr_auto] items-center gap-x-2.5 rounded-lg border px-3 py-2.5"
    :class="isSection ? 'bg-muted border-dashed' : 'bg-muted/50'"
    :style="indentStyle"
  >
    <Icon
      name="hugeicons:drag-drop-vertical"
      class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
    />

    <!--
      The label is the edit control. It is the thing the row is about, so
      clicking it should open it - and a button gives that a focus ring and
      Enter/Space for free.

      Spans rather than `<p>`/`<div>` inside: a button only accepts phrasing
      content, and a block child there is invalid HTML that browsers recover
      from inconsistently.
    -->
    <button
      type="button"
      class="group/label min-w-0 cursor-pointer text-left"
      :aria-label="`Edit field: ${field.label}`"
      @click="$emit('edit', field)"
    >
      <span class="block text-sm font-medium tracking-tight">
        <!-- A section has no type name below to carry its glyph, so it keeps one
             inline here. Inline rather than in its own column: a column would
             be blank on every field row below. -->
        <Icon
          v-if="isSection"
          :name="getTypeIcon(field.type)"
          class="text-muted-foreground mr-1.5 inline size-4 align-[-0.2em]"
        /><span
          class="decoration-muted-foreground/50 decoration-dotted decoration-2 underline-offset-4 group-hover/label:underline"
          >{{ field.label }}</span
        ><span
          v-if="isRequired"
          class="text-destructive-foreground relative top-[0.15em] ms-1"
          aria-hidden="true"
          >*</span
        >
      </span>

      <!-- The type glyph rides with the type name instead of holding a column
           of its own: the column was 26px of every row spent on one icon, and
           it left this line reading as a separate, unrelated thing.

           A section is a group header, not a field - the dashed frame says so -
           and its type name would have made every one of these rows two lines. -->
      <span
        v-if="!isSection || detail"
        class="text-muted-foreground mt-1 flex min-w-0 items-center gap-x-1.5 text-xs tracking-tight"
      >
        <!-- No pill background: its `px` would inset the glyph a few pixels and
             leave this line visibly off the label's left edge above it. -->
        <span v-if="!isSection" class="inline-flex shrink-0 items-center gap-x-1">
          <Icon :name="getTypeIcon(field.type)" class="size-3.5 shrink-0" />
          {{ getTypeLabel(field.type) }}
        </span>
        <template v-if="detail">
          <span v-if="!isSection" class="bg-muted-foreground/40 size-0.5 shrink-0 rounded-full" />
          <span class="truncate">{{ detail }}</span>
        </template>
      </span>
    </button>

    <div class="flex shrink-0 items-center gap-x-1">
      <!-- Fixed slot whether or not the toggle renders, so the menu button
           lands in the same column on section rows as on field rows. -->
      <span class="flex w-9 shrink-0 justify-center">
        <span v-if="!isSection" v-tippy="isRequired ? 'Required' : 'Optional'" class="flex">
          <Switch
            :model-value="isRequired"
            aria-label="Required"
            @update:model-value="$emit('toggle-required', field, $event)"
          />
        </span>
      </span>

      <!--
        A menu, not a row of icon buttons. A bare copy glyph reads as "copy to
        clipboard" and this one duplicated the field instead, which is the kind
        of mismatch no tooltip fixes. Words remove the guess, and three buttons
        collapse into one - the pane is narrow enough to care.
      -->
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="ghost" size="iconSm" aria-label="Field actions" class="rounded-full">
            <Icon name="lucide:ellipsis" class="size-5" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
          <DropdownMenuItem @select="$emit('edit', field)">
            <Icon name="hugeicons:edit-02" class="size-4 shrink-0" />
            <span>Edit</span>
          </DropdownMenuItem>
          <DropdownMenuItem @select="$emit('duplicate', field)">
            <Icon name="hugeicons:copy-01" class="size-4 shrink-0" />
            <span>Duplicate</span>
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem variant="destructive" @select="$emit('delete', field)">
            <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
            <span>Delete</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Switch } from "@/components/ui/switch";
import { getTypeIcon, getTypeLabel, hasOptions } from "@/lib/formFieldTypes";

const props = defineProps({
  field: { type: Object, required: true },
  /**
   * 1 for a field that belongs to the section above it. The list stays flat so
   * drag-and-drop keeps working on a single sortable; the indent is the only
   * thing that shows which section a question sits under.
   */
  depth: { type: Number, default: 0 },
});

defineEmits(["edit", "delete", "duplicate", "toggle-required"]);

const isSection = computed(() => props.field.type === "section");
const isRequired = computed(() => !!props.field.validation?.required);

const indentStyle = computed(() =>
  props.depth > 0 ? { marginInlineStart: `${props.depth * 1}rem` } : undefined
);

/** Options win over help text: they say more about what the field actually is. */
const detail = computed(() => {
  if (hasOptions(props.field.type) && props.field.options?.length) {
    return props.field.options.map((o) => o.label || o).join(", ");
  }
  return props.field.help_text || "";
});
</script>
