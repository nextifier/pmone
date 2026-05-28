<script setup>
import { ButtonCopy } from "@/components/ui/button-copy";
import { Input } from "@/components/ui/input";

const url = "https://example.com/invite/abc123";
</script>

<template>
  <div class="flex w-full max-w-sm items-center gap-2">
    <Input :model-value="url" readonly />
    <ButtonCopy :text="url" />
  </div>
</template>
