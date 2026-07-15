<template>
  <div v-if="isScannerOnly" />
  <template v-else>
    <DashboardBasicUser v-if="isDefaultUser" />
    <DashboardExhibitor v-else-if="isExhibitor" />
    <DashboardWriter v-else-if="isWriter" :tip-definitions="TIP_DEFINITIONS" />
    <DashboardStaff v-else :tip-definitions="TIP_DEFINITIONS" />
  </template>
</template>

<script setup>
const { hasRole, isStaffOrAbove } = usePermission();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
  scrollToTop: true,
});

usePageMeta(null, { title: "Dashboard" });

// Role detection
const isDefaultUser = computed(
  () => hasRole("user") && !isStaffOrAbove.value && !hasRole("exhibitor") && !hasRole("writer")
);
const isExhibitor = computed(() => hasRole("exhibitor") && !isStaffOrAbove.value);
const isWriter = computed(() => hasRole("writer") && !isStaffOrAbove.value);

// A scanner-only operator has no use for the admin dashboard - send them
// straight to the distraction-free check-in scanner.
const isScannerOnly = computed(() => hasRole("scanner") && !isStaffOrAbove.value);

onMounted(() => {
  if (isScannerOnly.value) {
    navigateTo("/scan", { replace: true });
  }
});

// Shared tip definitions used by Writer and Staff dashboards
const TIP_DEFINITIONS = [
  {
    key: "has_password",
    icon: "hugeicons:square-lock-add-01",
    text: "You can add a password to log in faster instead of waiting for a magic link.",
    action: "Set password",
    href: "/settings/password",
  },
  {
    key: "has_profile_photo",
    icon: "hugeicons:camera-01",
    text: "Add a profile photo so your team can recognize you.",
    action: "Upload photo",
    href: "/settings/profile",
  },
  {
    key: "has_phone",
    icon: "hugeicons:smart-phone-01",
    text: "Add your phone number so others can reach you when needed.",
    action: "Add number",
    href: "/settings/profile",
  },
];
</script>
