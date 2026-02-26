<template>
  <NuxtLink
    v-if="linked"
    :to="`/${user.username}`"
    class="block transition-opacity hover:opacity-80"
  >
    <UserProfile :user="user" :showVerifyIcon="true" :showRoleIcon="false" />
  </NuxtLink>
  <UserProfile v-else :user="user" />
</template>

<script setup>
import UserProfile from "@/components/user/Profile.vue";

defineProps({
  user: { type: Object, required: true },
  linked: { type: Boolean, default: true },
});
</script>
