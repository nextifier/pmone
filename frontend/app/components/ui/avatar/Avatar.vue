<template>
  <div
    v-if="model"
    v-tippy="tippyContent"
    class="@container relative shrink-0"
    :class="[
      gradientFrame
        ? `${effectiveRounded} before:gradient-insta before:absolute before:-inset-[max(0.25rem,4cqw)] before:rounded-[calc(var(--avatar-r)+max(0.25rem,4cqw))] before:bg-linear-to-tr before:content-[''] before:[corner-shape:inherit]`
        : '',
    ]"
    :style="gradientFrame ? { '--avatar-r': radiusValue } : undefined"
  >
    <div
      :style="
        !model?.profile_image && effectiveColorful && !gradientFrame ? meshGradientStyle : undefined
      "
      :class="[
        'outline-inside @container relative flex aspect-square shrink-0 items-center justify-center text-center',
        !model?.profile_image && (!effectiveColorful || gradientFrame) ? 'bg-muted' : '',
        gradientFrame ? 'ring-background bg-background z-10 ring-2' : '',
        effectiveRounded,
      ]"
    >
      <img
        v-if="model?.profile_image"
        :src="model.profile_image[size] || model.profile_image.sm"
        :alt="model?.name"
        :class="['size-full object-contain select-none', effectiveRounded]"
        width="100"
        height="100"
        loading="lazy"
        referrerPolicy="no-referrer"
      />
      <span
        v-else
        :class="[
          'initial text-[45cqw] tracking-tight select-none',
          effectiveColorful && !gradientFrame
            ? 'font-medium text-white'
            : 'text-muted-foreground font-light',
        ]"
      >
        {{
          (() => {
            const names = model?.name?.split(" ") || [];
            const first = names[0]?.[0]?.toUpperCase() || "";
            const last =
              names.length === 1
                ? names[0]?.[1]?.toUpperCase() || ""
                : names[names.length - 1]?.[0]?.toUpperCase() || "";
            return first + last;
          })()
        }}
      </span>

      <span
        v-if="indicator"
        :class="[
          'ring-background absolute -right-0.5 -bottom-0.5 size-2 rounded-full ring-2',
          indicatorClass,
        ]"
      ></span>
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from "vue";

const props = defineProps({
  model: Object,
  indicator: {
    type: String,
    default: null,
    validator: (v) =>
      v === null || ["success", "info", "warning", "destructive", "primary"].includes(v),
  },
  size: {
    type: String,
    default: "sm",
  },
  rounded: {
    type: String,
    default: "rounded-lg",
  },
  colorful: {
    type: Boolean,
    default: true,
  },
  gradientFrame: {
    type: Boolean,
    default: false,
  },
  circle: {
    type: Boolean,
    default: false,
  },
  noTooltip: {
    type: Boolean,
    default: false,
  },
});

const avatarGroupContext = inject("avatarGroupContext", null);

const indicatorClass = computed(() => {
  const map = {
    primary: "bg-primary",
    info: "bg-info",
    success: "bg-success",
    warning: "bg-warning",
    destructive: "bg-destructive",
  };
  return map[props.indicator] || "";
});

const effectiveColorful = computed(() =>
  avatarGroupContext && avatarGroupContext.colorful !== undefined
    ? avatarGroupContext.colorful
    : props.colorful
);

const effectiveRounded = computed(() => {
  if (props.circle || avatarGroupContext?.circle) return "rounded-full";
  return props.rounded;
});

const tippyContent = computed(() => {
  if (props.noTooltip) return "";
  if (avatarGroupContext?.showTooltip && props.model?.name) {
    return props.model.name;
  }
  return "";
});

const radiusValue = computed(() => {
  const value = effectiveRounded.value;
  if (value === "squircle" || value === "rounded-full") return "9999px";
  const suffix = value.replace("rounded", "");
  return `var(--radius${suffix})`;
});

const hue = computed(() => {
  const name = props.model?.name || "";
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return Math.abs(hash) % 360;
});

const meshGradientStyle = computed(() => {
  const h = hue.value;
  return {
    background: [
      `radial-gradient(at 15% 15%, oklch(0.78 0.26 ${h}) 0%, transparent 50%)`,
      `radial-gradient(at 85% 80%, oklch(0.52 0.28 ${(h + 30) % 360}) 0%, transparent 50%)`,
      `radial-gradient(at 60% 40%, oklch(0.65 0.3 ${(h + 12) % 360}) 0%, transparent 55%)`,
      `oklch(0.45 0.2 ${(h + 18) % 360})`,
    ].join(", "),
  };
});
</script>
