<script setup lang="ts">
import { computed } from "vue";

type Speaker = {
  name: string;
  title?: string;
  organization?: string;
};

const props = withDefaults(
  defineProps<{
    modelValue: Speaker[] | null | undefined;
  }>(),
  {
    modelValue: () => [],
  }
);

const emit = defineEmits<{
  "update:modelValue": [value: Speaker[]];
}>();

const list = computed<Speaker[]>(() => props.modelValue ?? []);

function update(next: Speaker[]) {
  emit("update:modelValue", next);
}

function add() {
  update([...list.value, { name: "", title: "", organization: "" }]);
}

function remove(index: number) {
  const next = [...list.value];
  next.splice(index, 1);
  update(next);
}

function patch(index: number, key: keyof Speaker, value: string) {
  const next = list.value.map((s, i) => (i === index ? { ...s, [key]: value } : s));
  update(next);
}
</script>

<template>
  <div class="space-y-2.5">
    <div
      v-for="(speaker, index) in list"
      :key="index"
      class="border-border bg-muted/30 rounded-lg border p-3"
    >
      <div class="flex items-start gap-2">
        <div class="grid flex-1 gap-2 sm:grid-cols-3">
          <div class="space-y-1">
            <Label class="text-xs">Name <span class="text-destructive">*</span></Label>
            <Input
              :model-value="speaker.name"
              placeholder="e.g. Dr. John Doe"
              @update:model-value="(v) => patch(index, 'name', String(v))"
            />
          </div>
          <div class="space-y-1">
            <Label class="text-xs">Title</Label>
            <Input
              :model-value="speaker.title ?? ''"
              placeholder="e.g. CEO"
              @update:model-value="(v) => patch(index, 'title', String(v))"
            />
          </div>
          <div class="space-y-1">
            <Label class="text-xs">Organization</Label>
            <Input
              :model-value="speaker.organization ?? ''"
              placeholder="e.g. ABC Corp"
              @update:model-value="(v) => patch(index, 'organization', String(v))"
            />
          </div>
        </div>

        <button
          type="button"
          class="hover:bg-destructive/10 inline-flex size-8 shrink-0 items-center justify-center rounded-md"
          @click="remove(index)"
          v-tippy="'Remove speaker'"
        >
          <Icon name="lucide:x" class="text-destructive size-3.5" />
        </button>
      </div>
    </div>

    <div
      v-if="!list.length"
      class="border-border text-muted-foreground rounded-lg border border-dashed py-4 text-center text-sm tracking-tight"
    >
      No speakers added yet.
    </div>

    <button
      type="button"
      class="border-border hover:bg-muted flex w-full items-center justify-center gap-x-1.5 rounded-md border border-dashed py-2 text-sm tracking-tight active:scale-99"
      @click="add"
    >
      <Icon name="lucide:plus" class="size-4 shrink-0" />
      <span>Add Speaker</span>
    </button>
  </div>
</template>
