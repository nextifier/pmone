<script setup lang="ts">
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from "@/components/ui/empty"
</script>

<template>
  <Card>
    <CardContent>
      <Empty class="h-48">
        <EmptyHeader>
          <EmptyTitle>Get alerted for anomalies</EmptyTitle>
          <EmptyDescription>
            Automatically monitor your projects for anomalies and get notified.
          </EmptyDescription>
        </EmptyHeader>
        <EmptyContent>
          <Button>Upgrade to Observability Plus</Button>
        </EmptyContent>
      </Empty>
    </CardContent>
  </Card>
</template>
