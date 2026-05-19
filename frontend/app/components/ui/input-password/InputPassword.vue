<script setup lang="ts">
import { cn } from "@/lib/utils";
import { useVModel } from "@vueuse/core";
import { ref, useTemplateRef, type HTMLAttributes } from "vue";
import { Input } from "@/components/ui/input";

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes["class"];
    showLabel?: string;
    hideLabel?: string;
  }>(),
  {
    showLabel: "Show password",
    hideLabel: "Hide password",
  }
);

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void;
}>();

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
});

const showPassword = ref(false);
const inputRef = useTemplateRef<InstanceType<typeof Input>>("inputRef");

const toggleShowPassword = () => {
  showPassword.value = !showPassword.value;

  const el = (inputRef.value as any)?.$el as HTMLInputElement | undefined;
  if (!el) return;
  el.focus();
  setTimeout(() => {
    if (typeof el.selectionStart === "number") {
      el.selectionStart = el.selectionEnd = el.value.length;
    }
  }, 0);
};
</script>

<template>
  <div class="relative">
    <Input
      ref="inputRef"
      v-bind="$attrs"
      v-model="modelValue"
      :type="showPassword ? 'text' : 'password'"
      :class="cn('!pr-12', props.class)"
      data-slot="input-password"
    />
    <div class="absolute top-1/2 right-2 -translate-y-1/2">
      <button
        type="button"
        tabindex="-1"
        class="hover:bg-muted flex size-7 items-center justify-center rounded-full transition active:scale-95"
        @click="toggleShowPassword"
      >
        <Icon
          v-if="!showPassword"
          name="lucide:eye"
          class="size-4 shrink-0"
          v-tippy="showLabel"
        />
        <Icon
          v-else
          name="lucide:eye-off"
          class="size-4 shrink-0"
          v-tippy="hideLabel"
        />
      </button>
    </div>
  </div>
</template>
