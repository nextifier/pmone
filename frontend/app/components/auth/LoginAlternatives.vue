<template>
    <div class="grid grid-cols-1 gap-y-3">
        <NuxtLink
              :to="`${config.public.apiUrl}/auth/google/redirect`"
              class="border-border bg-muted hover:bg-muted/80 text-primary flex items-center justify-center gap-3 rounded-lg border px-6 py-3 text-sm font-semibold tracking-tight transition active:scale-98"
            >
              <Icon name="devicon:google" class="size-4" />
              <span>Continue with Google</span>
            </NuxtLink>

            <NuxtLink
              :to="`/magic-link`"
              class="border-border bg-muted hover:bg-muted/80 text-primary flex items-center justify-center gap-3 rounded-lg border px-6 py-3 text-sm font-semibold tracking-tight transition active:scale-98"
            >
              <Icon name="hugeicons:mail-lock-02" class="size-5 shrink-0" />
              <span>Continue without password</span>
            </NuxtLink>
    </div>
</template>

<script setup>
const config = useRuntimeConfig();
</script>
