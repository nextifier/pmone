<template>
  <div class="flex flex-col gap-y-1 overflow-hidden">
    <div class="flex items-center gap-x-2">
      <NuxtLink
        :to="`/inbox/${submission.ulid}`"
        class="text-foreground text-sm font-medium tracking-tight hover:underline"
      >
        {{ submission.subject || "No Subject" }}
        <span v-if="submission.project?.name"> - {{ submission.project.name }} </span>
      </NuxtLink>
    </div>

    <div class="text-muted-foreground flex items-center gap-x-2 text-sm tracking-tight">
      <FlagComponent
        v-if="countryCode"
        v-tippy="countryName"
        :country="countryCode"
        class="h-3! shrink-0 rounded-xs! shadow-sm"
      />
      <div class="inline truncate">
        <span v-if="submission.form_data_preview?.name">
          {{ submission.form_data_preview.name }}
        </span>
        <span v-if="submission.form_data_preview?.brand_name">
          - Brand: {{ submission.form_data_preview.brand_name }}
        </span>
        <!-- <span v-if="submission.form_data_preview?.email" class="text-muted-foreground">
          - {{ submission.form_data_preview.email }}
        </span> -->
      </div>
    </div>
  </div>
</template>

<script setup>
import FlagComponent from "@/components/FlagComponent.vue";
import { computed } from "vue";

const props = defineProps({
  submission: {
    type: Object,
    required: true,
  },
});

const { getCountryFromPhone } = usePhoneCountry();

const countryInfo = computed(() => getCountryFromPhone(props.submission.form_data_preview?.phone));
const countryCode = computed(() => countryInfo.value?.code || null);
const countryName = computed(() => countryInfo.value?.name || null);
</script>
