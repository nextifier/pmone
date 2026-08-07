<template>
  <ResponsiveDialog dialog-max-width="60%" :overflow-content="true">
    <template #trigger="{ open }">
      <Button variant="outline" size="sm" @click="open()">
        <Icon name="hugeicons:raw-01" class="size-4 shrink-0" />
        <span>View Raw</span>
      </Button>
    </template>

    <template #default>
      <div class="px-4 pt-5 pb-8 md:px-6 md:py-5">
        <vue-json-pretty
          :data="data"
          :showLength="true"
          :showLine="true"
          :showLineNumber="true"
          :showIcon="true"
          class="h-[90vh]"
        />
      </div>
    </template>
  </ResponsiveDialog>
</template>

<script setup>
const props = defineProps({
  data: Object,
});
</script>
