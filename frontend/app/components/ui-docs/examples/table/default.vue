<script setup>
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

const invoices = [
  { id: "INV-001", method: "Credit Card", status: "Paid", amount: "$250.00" },
  { id: "INV-002", method: "PayPal", status: "Pending", amount: "$150.00" },
  { id: "INV-003", method: "Bank Transfer", status: "Unpaid", amount: "$350.00" },
  { id: "INV-004", method: "Credit Card", status: "Paid", amount: "$450.00" },
];
</script>

<template>
  <Table>
    <TableHeader>
      <TableRow>
        <TableHead>Invoice</TableHead>
        <TableHead>Method</TableHead>
        <TableHead>Status</TableHead>
        <TableHead class="text-right">Amount</TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <TableRow v-for="invoice in invoices" :key="invoice.id">
        <TableCell class="font-medium">{{ invoice.id }}</TableCell>
        <TableCell>{{ invoice.method }}</TableCell>
        <TableCell>{{ invoice.status }}</TableCell>
        <TableCell class="text-right">{{ invoice.amount }}</TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
