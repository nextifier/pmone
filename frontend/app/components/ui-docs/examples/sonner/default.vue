<script setup>
import { toast } from "vue-sonner";
import { Button } from "@/components/ui/button";

function fire() {
  toast("Event scheduled", {
    description: "Friday, March 14 at 10:00 AM",
    action: { label: "Undo", onClick: () => {} },
  });
}
</script>

<template>
  <Button variant="outline" @click="fire">Show toast</Button>
</template>
