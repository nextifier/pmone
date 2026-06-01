<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { SliderRootEmits, SliderRootProps } from "reka-ui";
import { SliderRange, SliderRoot, SliderThumb, SliderTrack, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<
  SliderRootProps & {
    class?: HTMLAttributes["class"];
    showTooltip?: boolean;
    tooltipContent?: (value: number) => string;
  }
>();
const emits = defineEmits<SliderRootEmits>();

const delegatedProps = reactiveOmit(props, "class", "showTooltip", "tooltipContent");

const forwarded = useForwardPropsEmits(delegatedProps, emits);

const thumbClass =
  "border-primary ring-ring/50 block size-4 shrink-0 rounded-full border bg-white shadow-sm transition-[color,box-shadow] hover:ring-4 focus-visible:ring-4 focus-visible:outline-hidden disabled:pointer-events-none disabled:opacity-50";
</script>

<template>
  <SliderRoot
    v-slot="{ modelValue }"
    data-slot="slider"
    :class="
      cn(
        'relative flex w-full touch-none items-center select-none data-[disabled]:opacity-50 data-[orientation=vertical]:h-full data-[orientation=vertical]:min-h-44 data-[orientation=vertical]:w-auto data-[orientation=vertical]:flex-col',
        props.class
      )
    "
    v-bind="forwarded"
  >
    <SliderTrack
      data-slot="slider-track"
      class="bg-muted relative grow overflow-hidden rounded-full data-[orientation=horizontal]:h-1.5 data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-1.5"
    >
      <SliderRange
        data-slot="slider-range"
        class="bg-primary absolute data-[orientation=horizontal]:h-full data-[orientation=vertical]:w-full"
      />
    </SliderTrack>

    <template v-for="(_, key) in modelValue" :key="key">
      <TooltipProvider v-if="showTooltip" :delay-duration="0">
        <Tooltip>
          <TooltipTrigger as-child>
            <SliderThumb data-slot="slider-thumb" :class="thumbClass" />
          </TooltipTrigger>
          <TooltipContent>
            {{ tooltipContent ? tooltipContent(modelValue[key]) : modelValue[key] }}
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
      <SliderThumb v-else data-slot="slider-thumb" :class="thumbClass" />
    </template>
  </SliderRoot>
</template>
