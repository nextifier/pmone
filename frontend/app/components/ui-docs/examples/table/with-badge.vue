<script setup>
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";

const orders = [
  { id: "ORD-1042", customer: "Olivia Martin", status: "success", label: "Fulfilled" },
  { id: "ORD-1041", customer: "Jackson Lee", status: "warning", label: "Processing" },
  { id: "ORD-1040", customer: "Isabella Nguyen", status: "destructive", label: "Refunded" },
];
</script>

<template>
  <Table>
    <TableHeader>
      <TableRow>
        <TableHead>Order</TableHead>
        <TableHead>Customer</TableHead>
        <TableHead class="text-right">Status</TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <TableRow v-for="row in orders" :key="row.id">
        <TableCell class="font-medium">{{ row.id }}</TableCell>
        <TableCell>{{ row.customer }}</TableCell>
        <TableCell class="text-right">
          <Badge :variant="row.status" plain>{{ row.label }}</Badge>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
