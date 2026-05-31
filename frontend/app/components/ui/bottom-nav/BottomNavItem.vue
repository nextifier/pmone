<script setup lang="ts">
import { cn } from "@/lib/utils";
import { NuxtLink } from "#components";
import { Primitive } from "reka-ui";
import { computed, inject, type HTMLAttributes } from "vue";
import {
  BOTTOM_NAV_CONTEXT,
  BOTTOM_NAV_DEFAULTS,
  bottomNavIconSizeClasses,
  bottomNavItemClasses,
  bottomNavItemPaddingClasses,
  bottomNavLabelSizeClasses,
} from "./context";

const props = withDefaults(
  defineProps<{
    to?: string;
    icon: string;
    activeIcon?: string;
    label?: string;
    badge?: number | boolean;
    exact?: boolean;
    active?: boolean;
    value?: string | number;
    as?: string;
    class?: HTMLAttributes["class"];
  }>(),
  {
    as: "button",
  },
);

const emit = defineEmits<{ select: [] }>();

const route = useRoute();
const ctx = inject(BOTTOM_NAV_CONTEXT, null);

const variant = computed(() => ctx?.variant.value ?? BOTTOM_NAV_DEFAULTS.variant);
const size = computed(() => ctx?.size.value ?? BOTTOM_NAV_DEFAULTS.size);
const indicator = computed(
  () => ctx?.indicator.value ?? BOTTOM_NAV_DEFAULTS.indicator,
);
const labelDisplay = computed(
  () => ctx?.labelDisplay.value ?? BOTTOM_NAV_DEFAULTS.labelDisplay,
);

const isExternal = computed(() => props.to?.startsWith("http"));

const isActive = computed(() => {
  if (props.active === true) {
    return true;
  }
  if (props.value !== undefined && ctx?.selectedValue.value !== undefined) {
    return ctx.selectedValue.value === props.value;
  }
  if (!props.to) {
    return false;
  }
  if (props.exact || props.to === "/") {
    return route.path === props.to || route.path === `${props.to}/`;
  }
  return route.path.startsWith(props.to);
});

const showDot = computed(() => indicator.value === "dot" && isActive.value);

const showLabel = computed(() => {
  if (!props.label || labelDisplay.value === "none") {
    return false;
  }
  return true;
});

const badgeIsCount = computed(() => typeof props.badge === "number");
const showBadge = computed(() =>
  badgeIsCount.value ? (props.badge as number) > 0 : props.badge === true,
);
const badgeText = computed(() => {
  const value = props.badge as number;
  return value > 99 ? "99+" : String(value);
});

const computedAriaLabel = computed(() => {
  if (showLabel.value || !props.label) {
    return undefined;
  }
  if (badgeIsCount.value && showBadge.value) {
    return `${props.label}, ${badgeText.value}`;
  }
  return props.label;
});

function handleSelect(): void {
  if (props.value !== undefined) {
    ctx?.select(props.value);
  }
  emit("select");
}
</script>

<template>
  <component
    :is="to ? NuxtLink : Primitive"
    :to="to || undefined"
    :as="to ? undefined : as"
    :type="!to && as === 'button' ? 'button' : undefined"
    :target="isExternal ? '_blank' : undefined"
    :rel="isExternal ? 'noopener noreferrer' : undefined"
    data-bottom-nav-item
    data-slot="bottom-nav-item"
    :data-state="isActive ? 'active' : 'inactive'"
    :aria-current="isActive ? 'page' : undefined"
    :aria-label="computedAriaLabel"
    :class="
      cn(
        'outline-none focus-visible:ring-ring focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
        bottomNavItemClasses[variant],
        bottomNavItemPaddingClasses[size],
        props.class,
      )
    "
    @click="handleSelect"
  >
    <span class="relative inline-flex">
      <span
        :class="cn('inline-grid place-items-center', bottomNavIconSizeClasses[size])"
      >
        <Icon
          :name="icon"
          aria-hidden="true"
          :class="
            cn(
              '[grid-area:1/1] size-full transition-[opacity,filter,transform] duration-200 ease-in-out motion-reduce:transition-none',
              activeIcon
                ? isActive
                  ? 'scale-[0.25] opacity-0 blur-[2px]'
                  : 'scale-100 opacity-100 blur-0'
                : 'scale-100 opacity-100 blur-0',
            )
          "
        />
        <Icon
          v-if="activeIcon"
          :name="activeIcon"
          aria-hidden="true"
          :class="
            cn(
              '[grid-area:1/1] size-full transition-[opacity,filter,transform] duration-200 ease-in-out motion-reduce:transition-none',
              isActive
                ? 'scale-100 opacity-100 blur-0'
                : 'scale-[0.25] opacity-0 blur-[2px]',
            )
          "
        />
      </span>

      <Transition
        enter-from-class="scale-0 opacity-0"
        enter-active-class="transition duration-300 ease-[cubic-bezier(0.34,1.36,0.64,1)] motion-reduce:transition-none"
        enter-to-class="scale-100 opacity-100"
        leave-from-class="scale-100 opacity-100"
        leave-active-class="transition duration-[180ms] ease-in motion-reduce:transition-none"
        leave-to-class="scale-0 opacity-0"
      >
        <span
          v-if="showBadge"
          :aria-hidden="badgeIsCount ? undefined : 'true'"
          :class="
            cn(
              'bg-destructive text-white pointer-events-none absolute origin-center',
              badgeIsCount
                ? '-top-1.5 -right-2 flex min-w-4 items-center justify-center rounded-full px-1 py-0.5 text-[0.625rem] leading-none font-medium tracking-tight'
                : '-top-0.5 -right-0.5 size-2 rounded-full ring-2 ring-background',
            )
          "
        >
          <template v-if="badgeIsCount">{{ badgeText }}</template>
        </span>
      </Transition>

      <span
        v-if="showDot"
        aria-hidden="true"
        class="bg-primary absolute -bottom-1 left-1/2 size-1 -translate-x-1/2 rounded-full transition-opacity duration-200 ease-out motion-reduce:transition-none"
      />
    </span>

    <span
      v-if="showLabel"
      :class="
        cn(
          'overflow-hidden font-medium tracking-tight',
          bottomNavLabelSizeClasses[size],
          labelDisplay === 'active'
            ? isActive
              ? 'max-w-20 opacity-100 transition-[max-width,opacity] duration-200 ease-out motion-reduce:transition-none'
              : 'max-w-0 opacity-0 transition-[max-width,opacity] duration-200 ease-out motion-reduce:transition-none'
            : 'max-w-20 opacity-100',
        )
      "
    >
      {{ label }}
    </span>
  </component>
</template>
