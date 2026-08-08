<script setup lang="ts">
import { InputGroup, InputGroupAddon } from "@/components/ui/input-group";
import { cn } from "@/lib/utils";
import { Search } from "@lucide/vue";
import {
  ComboboxInput,
  type ComboboxInputProps,
  useForwardProps,
} from "reka-ui";
import { computed, type HTMLAttributes } from "vue";

defineOptions({
  inheritAttrs: false,
});

const props = defineProps<
  ComboboxInputProps & {
    class?: HTMLAttributes["class"];
  }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <div class="cn-command-input-wrapper" data-slot="command-input-wrapper">
    <InputGroup class="cn-command-input-group">
      <ComboboxInput
        v-bind="{ ...forwardedProps, ...$attrs }"
        data-slot="command-input"
        :class="
          cn(
            'cn-command-input outline-hidden disabled:cursor-not-allowed disabled:opacity-50',
            props.class,
          )
        "
      />
      <InputGroupAddon>
        <Search class="cn-command-input-icon" />
      </InputGroupAddon>
    </InputGroup>
  </div>
</template>
