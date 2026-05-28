<script setup>
import { Button } from "@/components/ui/button";
</script>

<template>
  <Button>
    <Icon name="hugeicons:download-04" />
    Download
  </Button>
  <Button variant="outline">
    Continue
    <Icon name="hugeicons:arrow-right-02" />
  </Button>
  <Button variant="secondary">
    <Icon name="hugeicons:upload-04" />
    Upload
  </Button>
</template>
