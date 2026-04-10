<template>
  <DropdownMenu>
    <DropdownMenuTrigger asChild>
      <button
        class="data-[state=open]:bg-muted hover:bg-muted inline-flex shrink-0 items-center gap-x-1.5 rounded-md px-2 py-1 text-sm tracking-tight whitespace-nowrap active:scale-98"
      >
        <template v-if="disabled">
          <Spinner class="size-4" />
        </template>
        <template v-else>
          <span :class="config.dot" class="size-2 rounded-full" />
          {{ config.label }}
          <Icon name="lucide:chevron-down" class="size-3 opacity-60" />
        </template>
      </button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-40">
      <DropdownMenuItem
        v-for="s in statuses"
        :key="s.value"
        :disabled="disabled || status === s.value"
        class="gap-x-2"
        @click="$emit('update', s.value)"
      >
        <span :class="statusConfigs[s.value].dot" class="size-2 rounded-full" />
        {{ s.label }}
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup>
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const props = defineProps({
  status: { type: String, required: true },
  disabled: { type: Boolean, default: false },
});

defineEmits(["update"]);

const statuses = [
  { value: "active", label: "Active" },
  { value: "draft", label: "Draft" },
  { value: "cancelled", label: "Cancelled" },
];

const statusConfigs = {
  active: { label: "Active", dot: "bg-success" },
  draft: { label: "Draft", dot: "bg-warning" },
  cancelled: { label: "Cancelled", dot: "bg-destructive" },
};

const config = computed(() => statusConfigs[props.status] || statusConfigs.active);
</script>
