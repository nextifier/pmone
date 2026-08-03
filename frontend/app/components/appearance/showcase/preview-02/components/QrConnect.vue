<script setup lang="ts">
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { QRCode } from "@/components/ui/qr-code"
</script>

<template>
  <Card>
    <CardContent class="flex justify-center pt-6">
      <!-- No wrapper: QRCode is aspect-square w-full, so the width cap goes on the
           component itself. The default `margin` (2 modules) stays so the quiet zone
           lives inside the SVG now that there is no padding around it. Colours come
           from var(--foreground) on a transparent ground, and omitting `variant` is
           what keeps click-to-cycle enabled. -->
      <QRCode url="https://shadcn-vue.com" class="w-48" />
    </CardContent>
    <CardHeader class="text-center">
      <CardTitle>Scan to connect your mobile device</CardTitle>
      <CardDescription>
        Open the Ledger mobile app and scan this code to link your device.
      </CardDescription>
    </CardHeader>
    <CardFooter>
      <Button variant="secondary" class="w-full">
        Got it
      </Button>
    </CardFooter>
  </Card>
</template>
