<script setup>
import { ButtonGroup, ButtonGroupSeparator } from "@/components/ui/button-group";
import { Button } from "@/components/ui/button";
</script>

<template>
  <ButtonGroup>
    <Button variant="outline">
      <Icon name="lucide:align-left" />
    </Button>
    <Button variant="outline">
      <Icon name="lucide:align-center" />
    </Button>
    <Button variant="outline">
      <Icon name="lucide:align-right" />
    </Button>
    <ButtonGroupSeparator />
    <Button variant="outline">
      <Icon name="lucide:bold" />
    </Button>
    <Button variant="outline">
      <Icon name="lucide:italic" />
    </Button>
  </ButtonGroup>
</template>
