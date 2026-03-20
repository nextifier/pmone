<script setup lang="ts">
import type { ChatStatus } from 'ai'
import type { HTMLAttributes } from 'vue'
import { InputGroupButton } from '@/components/ui/input-group'
import { cn } from '@/lib/utils'
import { ArrowUpIcon, Loader2Icon, SquareIcon, XIcon } from 'lucide-vue-next'
import { computed } from 'vue'
import { usePromptInput } from './context'

type InputGroupButtonProps = InstanceType<typeof InputGroupButton>['$props']

interface Props extends /* @vue-ignore */ InputGroupButtonProps {
  class?: HTMLAttributes['class']
  status?: ChatStatus
  variant?: InputGroupButtonProps['variant']
  size?: InputGroupButtonProps['size']
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  size: 'icon-sm',
})

const { textInput } = usePromptInput()

const isVisible = computed(() => {
  if (props.status === 'streaming' || props.status === 'submitted') return true
  return textInput.value.trim().length > 0
})

const icon = computed(() => {
  if (props.status === 'submitted') return Loader2Icon
  if (props.status === 'streaming') return SquareIcon
  if (props.status === 'error') return XIcon
  return ArrowUpIcon
})

const iconClass = computed(() => {
  if (props.status === 'submitted') return 'size-4 animate-spin'
  return 'size-4'
})

const { status, size, variant, class: _, ...restProps } = props
</script>

<template>
  <InputGroupButton
    aria-label="Submit"
    :class="cn(
      'rounded-full! transition-all duration-200',
      isVisible ? 'scale-100 opacity-100' : 'scale-75 opacity-0 pointer-events-none',
      props.class,
    )"
    :size="size"
    :variant="variant"
    type="submit"
    :tabindex="isVisible ? 0 : -1"
    v-bind="restProps"
  >
    <slot>
      <component :is="icon" :class="iconClass" />
    </slot>
  </InputGroupButton>
</template>
