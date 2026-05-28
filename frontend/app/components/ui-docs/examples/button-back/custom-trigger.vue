<script setup>
import { ButtonBack } from "@/components/ui/button-back";
import { Button } from "@/components/ui/button";
</script>

<template>
  <ButtonBack>
    <template #default="{ goBack }">
      <Button variant="ghost" @click="goBack">
        <Icon name="lucide:chevron-left" />
        Back to results
      </Button>
    </template>
  </ButtonBack>
</template>
