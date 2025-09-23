<template>
  <div v-if="user" class="flex items-center gap-x-1.5 tracking-tight">
    <AuthUserAvatar :user="user" class="size-8" />
    <div class="grid flex-1 text-left text-sm leading-tight">
      <span
        class="text-foreground inline-flex items-center gap-x-[3px] truncate align-middle font-medium"
      >
        <span class="shrink truncate">{{ user?.name }}</span>
        <Icon
          v-if="user.email_verified_at"
          name="material-symbols:verified"
          class="text-info size-3.5 shrink-0"
        />
        <Tippy>
          <Icon v-if="getRoleIcon" :name="getRoleIcon" class="text-foreground size-3.5 shrink-0" />

          <template #content>
            <span class="tracking-tight capitalize">
              {{ user.roles.join(', ') }}
            </span>
          </template>
        </Tippy>
      </span>
      <span class="truncate text-xs">{{ user.email }}</span>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  user: Object
})

const getRoleIcon = computed(() => {
  if (!props.user?.roles?.length) return null

  // Priority order: master > admin > staff > writer > user
  if (props.user.roles.includes('master')) {
    return 'material-symbols:chess-king-2'
  }
  if (props.user.roles.includes('admin')) {
    return 'material-symbols:chess-queen'
  }
  if (props.user.roles.includes('staff')) {
    return 'material-symbols:chess'
  }
  if (props.user.roles.includes('writer')) {
    return 'material-symbols:chess-knight'
  }
  if (props.user.roles.includes('user')) {
    return 'material-symbols:chess-pawn'
  }

  return null
})
</script>
