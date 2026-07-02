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
  "cn-slider-thumb block shrink-0 select-none disabled:pointer-events-none disabled:opacity-50";
</script>

<template>
  <SliderRoot
    v-slot="{ modelValue }"
    data-slot="slider"
    :data-vertical="props.orientation === 'vertical' ? '' : undefined"
    :class="
      cn(
        'cn-slider relative flex w-full touch-none items-center select-none data-disabled:opacity-50 data-vertical:h-full data-vertical:w-auto data-vertical:flex-col',
        props.class
      )
    "
    v-bind="forwarded"
  >
    <SliderTrack
      data-slot="slider-track"
      :data-horizontal="props.orientation !== 'vertical' ? '' : undefined"
      :data-vertical="props.orientation === 'vertical' ? '' : undefined"
      class="cn-slider-track relative grow overflow-hidden data-horizontal:w-full data-vertical:h-full"
    >
      <SliderRange
        data-slot="slider-range"
        :data-horizontal="props.orientation !== 'vertical' ? '' : undefined"
        :data-vertical="props.orientation === 'vertical' ? '' : undefined"
        class="cn-slider-range absolute select-none data-horizontal:h-full data-vertical:w-full"
      />
    </SliderTrack>

    <template v-for="(_, key) in modelValue" :key="key">
      <TooltipProvider v-if="showTooltip" :delay-duration="0">
        <Tooltip>
          <TooltipTrigger as-child>
            <SliderThumb
              data-slot="slider-thumb"
              :data-vertical="props.orientation === 'vertical' ? '' : undefined"
              :class="thumbClass"
            />
          </TooltipTrigger>
          <TooltipContent>
            {{ tooltipContent ? tooltipContent(modelValue[key]) : modelValue[key] }}
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
      <SliderThumb
        v-else
        data-slot="slider-thumb"
        :data-vertical="props.orientation === 'vertical' ? '' : undefined"
        :class="thumbClass"
      />
    </template>
  </SliderRoot>
</template>
