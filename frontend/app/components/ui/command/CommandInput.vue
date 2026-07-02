<script setup lang="ts">
import { InputGroup, InputGroupAddon } from "@/components/ui/input-group";
import { cn } from "@/lib/utils";
import { Search } from "lucide-vue-next";
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
  <div class="cn-command-input-wrapper" cmdk-input-wrapper data-slot="command-input-wrapper">
    <InputGroup class="cn-command-input-group">
      <InputGroupAddon>
        <Search class="cn-command-input-icon" />
      </InputGroupAddon>
      <ComboboxInput
        v-bind="{ ...forwardedProps, ...$attrs }"
        data-slot="command-input"
        auto-focus
        :class="
          cn(
            'cn-command-input outline-hidden disabled:cursor-not-allowed disabled:opacity-50',
            props.class,
          )
        "
      />
    </InputGroup>
  </div>
</template>
