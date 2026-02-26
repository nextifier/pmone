<template>
  <div
    class="min-h-screen-offset mx-auto flex max-w-md flex-col items-center justify-center px-4 py-10 text-center"
  >
    <div
      class="*:bg-card *:squircle text-muted-foreground relative isolate flex items-center -space-x-2 *:rounded-lg *:border *:p-4 [&_svg]:size-6"
    >
      <div class="z-0 translate-y-1.5 -rotate-6">
        <Icon name="hugeicons:message-question" />
      </div>
      <div class="z-1">
        <Icon name="hugeicons:file-02" />
      </div>
      <div class="z-2 translate-y-1.5 rotate-6">
        <Icon name="hugeicons:bulb" />
      </div>
    </div>

    <h2 class="page-title mt-8">
      {{ $t("dashboard.userDashboardTitle") }}
    </h2>
    <p class="page-description mt-2 text-base">
      {{ $t("dashboard.userDashboardDescription") }}
    </p>
    <Button as-child variant="outline" size="sm" class="mt-6">
      <NuxtLink to="/settings/profile">
        <Icon name="hugeicons:user-edit-01" class="mr-1.5 size-4" />
        {{ $t("dashboard.userDashboardSettings") }}
      </NuxtLink>
    </Button>
  </div>
</template>
