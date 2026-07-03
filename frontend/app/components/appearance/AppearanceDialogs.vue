<script setup lang="ts">
// The Reset + Open-Preset dialogs, mounted ONCE (from AppearancePickerButton,
// which is present wherever the customizer can be triggered) so they never
// duplicate across the settings-page and header-popover customizer instances.
// All state/handlers come from the shared useAppearanceActions composable.
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import AppearanceOpenPresetDialog from "@/components/appearance/AppearanceOpenPresetDialog.vue";

const { resetDialogOpen, openPresetDialogOpen, confirmReset, applyPreset } = useAppearanceActions();
</script>

<template>
  <AppearanceOpenPresetDialog v-model:open="openPresetDialogOpen" :on-apply="applyPreset" />
  <ConfirmDialog
    v-model:open="resetDialogOpen"
    title="Reset to defaults?"
    description="This will reset all customization options to their default values."
    confirm-label="Reset"
    variant="destructive"
    @confirm="confirmReset"
  />
</template>
