<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { computed } from "vue";
import { cn } from "@/lib/utils";

/**
 * Field-level error list. Accepts plain strings (Laravel-style validation) or
 * reka/vee-validate `{ message }` objects, and renders them as a list. This is
 * the single canonical error renderer (it supersedes the old bespoke
 * `InputErrorMessage` and renders pixel-identically to it).
 */
const props = defineProps<{
  class?: HTMLAttributes["class"];
  errors?: Array<string | { message?: string } | undefined | null>;
}>();

const messages = computed(() =>
  (props.errors ?? [])
    .map((e) => (typeof e === "string" ? e : e?.message))
    .filter((m): m is string => !!m),
);
</script>

<template>
  <ul
    v-if="$slots.default || messages.length"
    role="alert"
    data-slot="field-error"
    :class="cn('cn-field-error flex flex-col gap-y-1 tracking-tight', props.class)"
  >
    <slot>
      <li v-for="(message, index) in messages" :key="index">
        {{ message }}
      </li>
    </slot>
  </ul>
</template>
