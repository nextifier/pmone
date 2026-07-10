<template>
  <div class="border-border bg-card rounded-xl border">
    <!-- Brand identity header -->
    <component
      :is="collapsible ? 'button' : 'div'"
      :type="collapsible ? 'button' : undefined"
      class="flex w-full items-center gap-3 px-4 py-4 text-left sm:px-5"
      @click="collapsible ? (open = !open) : null"
    >
      <Avatar
        :model="{ name: be.brand.name, profile_image: be.brand.profile_image }"
        class="size-14 shrink-0 sm:size-16"
        rounded="rounded-full"
      />
      <div class="min-w-0 flex-1">
        <h3 class="truncate text-lg font-semibold tracking-tight sm:text-xl">
          {{ be.brand.name }}
        </h3>
        <div v-if="be.booth_number || be.booth_type_label" class="mt-1 flex flex-wrap items-center gap-2">
          <span
            v-if="be.booth_number"
            class="bg-muted inline-flex items-baseline gap-1.5 rounded-lg px-2.5 py-1"
          >
            <span class="text-muted-foreground text-sm tracking-tight">Booth</span>
            <span class="text-foreground font-semibold tracking-tight">{{ be.booth_number }}</span>
          </span>
          <span v-if="be.booth_type_label" class="text-muted-foreground text-sm tracking-tight">
            {{ be.booth_type_label }}
          </span>
        </div>
      </div>

      <div v-if="collapsible" class="flex items-center gap-2">
        <span class="text-muted-foreground text-sm tracking-tight">{{ progressLabel }}</span>
        <Icon
          name="hugeicons:arrow-down-01"
          :class="['text-muted-foreground size-5 shrink-0 transition-transform', open && 'rotate-180']"
        />
      </div>
    </component>

    <!-- Body -->
    <div v-if="!collapsible || open" class="border-border space-y-6 border-t px-4 py-5 sm:px-5">
      <DashboardExhibitorStepper :steps="steps" @jump="handleJump" />
      <DashboardExhibitorSections
        ref="sectionsRef"
        :be="be"
        :dashboard="dashboard"
        :default-profile-open="defaultOpen"
        @refresh="$emit('refresh')"
      />
    </div>
  </div>
</template>

<script setup>
import { Avatar } from "@/components/ui/avatar";
import { getExhibitorSteps } from "@/utils/exhibitorDashboard";

const { t } = useI18n();

const props = defineProps({
  be: { type: Object, required: true },
  dashboard: { type: Object, required: true },
  collapsible: { type: Boolean, default: false },
  defaultOpen: { type: Boolean, default: true },
});

defineEmits(["refresh"]);

const open = ref(props.defaultOpen);
const sectionsRef = ref(null);

const steps = computed(() => getExhibitorSteps(props.be, props.dashboard?.profile_complete, t));

const progressLabel = computed(() => {
  const done = steps.value.filter((s) => s.completed).length;
  return `${done}/${steps.value.length}`;
});

function handleJump(key) {
  if (props.collapsible) open.value = true;
  nextTick(() => sectionsRef.value?.openAndScroll(key));
}

function openAndScroll(key) {
  if (props.collapsible) open.value = true;
  nextTick(() => sectionsRef.value?.openAndScroll(key));
}

defineExpose({ openAndScroll });
</script>
