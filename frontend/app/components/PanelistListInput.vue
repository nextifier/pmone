<script setup lang="ts">
import { computed } from "vue";

type Panelist = {
  name: string;
  title?: string;
};

const props = withDefaults(
  defineProps<{
    modelValue: Panelist[] | null | undefined;
  }>(),
  {
    modelValue: () => [],
  }
);

const emit = defineEmits<{
  "update:modelValue": [value: Panelist[]];
}>();

const list = computed<Panelist[]>(() => props.modelValue ?? []);

function update(next: Panelist[]) {
  emit("update:modelValue", next);
}

function add() {
  update([...list.value, { name: "", title: "" }]);
}

function remove(index: number) {
  const next = [...list.value];
  next.splice(index, 1);
  update(next);
}

function patch(index: number, key: keyof Panelist, value: string) {
  const next = list.value.map((p, i) => (i === index ? { ...p, [key]: value } : p));
  update(next);
}
</script>

<template>
  <div class="space-y-2">
    <div
      v-for="(panelist, index) in list"
      :key="index"
      class="flex items-center gap-2"
    >
      <Input
        :model-value="panelist.name"
        placeholder="Name"
        class="flex-1"
        @update:model-value="(v) => patch(index, 'name', String(v))"
      />
      <Input
        :model-value="panelist.title ?? ''"
        placeholder="Title (optional)"
        class="flex-1"
        @update:model-value="(v) => patch(index, 'title', String(v))"
      />
      <button
        type="button"
        class="hover:bg-destructive/10 inline-flex size-8 shrink-0 items-center justify-center rounded-md"
        @click="remove(index)"
        v-tippy="'Remove panelist'"
      >
        <Icon name="lucide:x" class="text-destructive size-3.5" />
      </button>
    </div>

    <button
      type="button"
      class="border-border hover:bg-muted flex w-full items-center justify-center gap-x-1.5 rounded-md border border-dashed py-2 text-sm tracking-tight active:scale-99"
      @click="add"
    >
      <Icon name="lucide:plus" class="size-4 shrink-0" />
      <span>Add Panelist</span>
    </button>
  </div>
</template>
