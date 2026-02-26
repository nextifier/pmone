<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h4 class="font-semibold tracking-tight">Brand Profile</h4>
      <p class="text-muted-foreground text-sm tracking-tight">
        View and edit brand information.
      </p>
    </div>

    <BrandFormBrandProfile
      :brand="brandEvent.brand"
      :api-url="`/api/projects/${$route.params.username}/events/${$route.params.eventSlug}/brands/${$route.params.brandSlug}/profile`"
      :show-logo="true"
      :show-status="true"
      :show-categories="true"
      :custom-field-definitions="customFieldDefinitions"
      :custom-field-initial-values="customFieldValues"
      submit-label="Save Profile"
      @saved="$emit('refresh')"
    />
  </div>
</template>

<script setup>
defineProps({
  brandEvent: Object,
  customFieldDefinitions: { type: Array, default: () => [] },
  customFieldValues: { type: Object, default: () => ({}) },
});
defineEmits(["refresh"]);
</script>
