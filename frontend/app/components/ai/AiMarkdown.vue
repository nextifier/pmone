<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import MarkdownRender from "markstream-vue";
import "markstream-vue/index.css";
import { cn } from "@/lib/utils";

/**
 * Renders an assistant reply as streaming Markdown (via markstream-vue).
 * Replaces the old ai-elements MessageResponse.
 * Kept OUT of components/ui on purpose: the markdown engine is pmone-only and
 * must not ship to the events/levenium public sites.
 */
const props = withDefaults(
  defineProps<{
    content: string;
    streaming?: boolean;
    class?: HTMLAttributes["class"];
  }>(),
  { streaming: false },
);
</script>

<template>
  <ClientOnly>
    <MarkdownRender
      mode="chat"
      :content="content"
      :final="!streaming"
      smooth-streaming="auto"
      :fade="false"
      :class="cn('[&>*:first-child]:mt-0! [&>*:last-child]:mb-0!', props.class)"
    />
    <template #fallback>
      <p class="whitespace-pre-wrap">{{ content }}</p>
    </template>
  </ClientOnly>
</template>
