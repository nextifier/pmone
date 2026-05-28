<script setup>
import { TableData } from "@/components/ui/table-data";

const columns = [
  { accessorKey: "id", header: "Invoice" },
  { accessorKey: "customer", header: "Customer" },
  { accessorKey: "status", header: "Status" },
  { accessorKey: "amount", header: "Amount" },
];

const data = [
  { id: "INV-001", customer: "Olivia Martin", status: "Paid", amount: "$250.00" },
  { id: "INV-002", customer: "Jackson Lee", status: "Pending", amount: "$150.00" },
  { id: "INV-003", customer: "Isabella Nguyen", status: "Unpaid", amount: "$350.00" },
  { id: "INV-004", customer: "Sofia Davis", status: "Paid", amount: "$450.00" },
  { id: "INV-005", customer: "Liam Brown", status: "Paid", amount: "$120.00" },
];

const meta = {
  current_page: 1,
  per_page: 5,
  total: data.length,
  last_page: 1,
  from: 1,
  to: data.length,
};
</script>

<template>
  <TableData
    :columns="columns"
    :data="data"
    :meta="meta"
    model="invoice"
    :initial-sorting="[{ id: 'id', desc: false }]"
    class="w-full"
  />
</template>
