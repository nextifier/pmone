<template>
  <DropdownMenu>
    <DropdownMenuTrigger asChild>
      <button
        :class="[
          'inline-flex shrink-0 items-center gap-x-1.5 rounded-md text-sm font-medium whitespace-nowrap tracking-tight transition active:scale-98',
          bordered
            ? 'border-border text-foreground hover:bg-muted data-[state=open]:bg-muted border px-2.5 py-1'
            : 'text-muted-foreground hover:text-foreground data-[state=open]:bg-muted hover:bg-muted px-2 py-1',
        ]"
      >
        <template v-if="disabled">
          <Spinner class="size-4" />
        </template>
        <template v-else>
          <span :class="currentConfig.dot" class="size-2 rounded-full" />
          {{ currentConfig.label }}
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
        <span :class="s.dot" class="size-2 rounded-full" />
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
  statuses: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  bordered: { type: Boolean, default: false },
});

defineEmits(["update"]);

const currentConfig = computed(() => {
  return props.statuses.find((s) => s.value === props.status) || { label: props.status, dot: "bg-gray-400" };
});
</script>
