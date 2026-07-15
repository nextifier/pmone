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
        <div v-if="be.booth_type_label" class="mt-1 flex flex-wrap items-center gap-2">
          <span class="text-muted-foreground text-sm tracking-tight">
            {{ be.booth_type_label }}
          </span>
        </div>
      </div>

      <div v-if="be.booth_number || collapsible" class="flex items-center gap-3">
        <div v-if="be.booth_number" class="text-right leading-tight">
          <div class="text-muted-foreground text-xs tracking-tight">Booth</div>
          <div class="text-foreground text-lg font-semibold tracking-tighter sm:text-xl">
            {{ be.booth_number }}
          </div>
        </div>
        <Icon
          v-if="collapsible"
          name="hugeicons:arrow-down-01"
          :class="['text-muted-foreground size-5 shrink-0 transition-transform', open && 'rotate-180']"
        />
      </div>
    </component>

    <!-- Body -->
    <div v-if="!collapsible || open" class="border-border border-t">
      <div class="px-4 py-5 sm:px-5">
        <DashboardExhibitorStepper :steps="steps" @jump="handleJump" />
      </div>
      <div class="border-border border-t">
        <DashboardExhibitorSections
          ref="sectionsRef"
          :be="be"
          :dashboard="dashboard"
          :default-profile-open="defaultOpen"
          @refresh="$emit('refresh')"
        />
      </div>
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
