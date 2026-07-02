<script setup lang="ts">
import type { DateValue } from "reka-ui"
import type { Ref } from "vue"
import { CalendarDate } from "@internationalized/date"
import { ref } from "vue"
import { Badge } from "@/components/appearance/showcase/_ui/badge"
import { Calendar } from "@/components/ui/calendar"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Item,
  ItemContent,
  ItemDescription,
  ItemGroup,
  ItemTitle,
} from "@/components/ui/item"

const today = new Date()
const date: Ref<DateValue> = ref(new CalendarDate(today.getFullYear(), today.getMonth() + 1, today.getDate()))
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Upcoming Payments</CardTitle>
      <CardDescription>
        Select a date to view scheduled payments.
      </CardDescription>
    </CardHeader>
    <CardContent class="flex flex-col gap-4">
      <Item variant="outline" class="justify-center">
        <Calendar
          :model-value="date"
          :layout="null"
          weekday-format="narrow"
          :week-starts-on="0"
          class="w-full [--cell-size:--spacing(8)] md:[--cell-size:--spacing(10)]"
          @update:model-value="v => v && (date = v)"
        />
      </Item>
      <ItemGroup class="w-full">
        <Item variant="muted">
          <ItemContent>
            <ItemTitle>Netflix Subscription</ItemTitle>
            <ItemDescription>Apr 15, 2024</ItemDescription>
          </ItemContent>
          <Badge variant="secondary">
            $19.99
          </Badge>
        </Item>
        <Item variant="muted">
          <ItemContent>
            <ItemTitle>Rent Payment</ItemTitle>
            <ItemDescription>Apr 1, 2024</ItemDescription>
          </ItemContent>
          <Badge variant="secondary">
            $2,400.00
          </Badge>
        </Item>
        <Item variant="muted">
          <ItemContent>
            <ItemTitle>Auto Insurance</ItemTitle>
            <ItemDescription>Apr 22, 2024</ItemDescription>
          </ItemContent>
          <Badge variant="secondary">
            $186.00
          </Badge>
        </Item>
      </ItemGroup>
    </CardContent>
  </Card>
</template>
