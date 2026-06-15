<template>
  <div class="grid grid-cols-1 gap-y-10">
    <div class="flex flex-col gap-y-4">
      <div class="flex flex-col gap-y-1.5">
        <Skeleton class="h-6 w-44" />
        <Skeleton class="h-4 w-64 max-w-full" />
      </div>

      <div class="frame">
        <div class="frame-header">
          <div class="flex items-center justify-between gap-2">
            <div class="flex flex-col gap-y-1.5">
              <Skeleton class="h-5 w-32" />
              <Skeleton class="xs:block hidden h-3.5 w-52" />
            </div>
            <Skeleton class="h-8 w-36 shrink-0 rounded-md" />
          </div>
        </div>

        <div class="bg-background -m-px overflow-hidden rounded-xl border p-3">
          <Skeleton class="h-52 w-full rounded-lg" />
        </div>
      </div>

      <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-x-2.5 gap-y-4">
        <div
          v-for="i in 8"
          :key="i"
          class="border-border bg-card flex flex-col rounded-xl border"
        >
          <div class="flex flex-col p-5">
            <div class="flex items-center justify-between">
              <Skeleton class="h-4 w-24" />
              <Skeleton class="size-8 shrink-0 translate-x-1 rounded-lg" />
            </div>
            <Skeleton class="mt-3.5 h-8 w-20" />
            <Skeleton class="mt-2.5 h-3 w-32 max-w-full" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Skeleton } from "@/components/ui/skeleton";
</script>
