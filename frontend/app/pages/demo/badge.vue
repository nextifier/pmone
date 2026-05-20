<script setup lang="ts">
import { Badge } from "@/components/ui/badge";

definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Badge" });

type Variant =
  | "default"
  | "info"
  | "success"
  | "warning"
  | "destructive"
  | "muted"
  | "outline";

const variants: { variant: Variant; label: string }[] = [
  { variant: "default", label: "Default" },
  { variant: "info", label: "Info" },
  { variant: "success", label: "Success" },
  { variant: "warning", label: "Warning" },
  { variant: "destructive", label: "Destructive" },
  { variant: "muted", label: "Muted" },
  { variant: "outline", label: "Outline" },
];
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Badge</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Badge gaya pill outline tanpa background. Tiap variant punya dot indicator kecil di
        kiri label yang warnanya mengikuti variant. Dot bisa diganti dengan icon lewat prop
        <code class="bg-muted rounded px-1 py-0.5 text-sm">icon</code>.
      </p>
    </div>

    <section class="mb-10">
      <h2 class="mb-2 text-xl font-medium tracking-tighter">Variant dengan dot indicator</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Delapan variant. Variant
        <code class="bg-muted rounded px-1 py-0.5 text-sm">outline</code>
        tidak menampilkan dot.
      </p>
      <div class="bg-background flex flex-wrap items-center gap-3 rounded-2xl border p-4 sm:p-8">
        <Badge v-for="v in variants" :key="v.variant" :variant="v.variant">
          {{ v.label }}
        </Badge>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-2 text-xl font-medium tracking-tighter">Variant dengan icon</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Prop
        <code class="bg-muted rounded px-1 py-0.5 text-sm">withIcon</code>
        mengganti dot dengan icon bawaan tiap variant. Untuk icon kustom, isi prop
        <code class="bg-muted rounded px-1 py-0.5 text-sm">icon</code>.
      </p>
      <div class="bg-background flex flex-wrap items-center gap-3 rounded-2xl border p-4 sm:p-8">
        <Badge v-for="v in variants" :key="v.variant" :variant="v.variant" with-icon>
          {{ v.label }}
        </Badge>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-2 text-xl font-medium tracking-tighter">Plain (tanpa chrome)</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Prop
        <code class="bg-muted rounded px-1 py-0.5 text-sm">plain</code>
        menghapus border, padding, dan border-radius — menyisakan dot/icon + label. Cocok untuk
        indikator di dalam tabel atau baris teks.
      </p>
      <div class="bg-background flex flex-col gap-4 rounded-2xl border p-4 sm:p-8">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
          <Badge v-for="v in variants" :key="v.variant" :variant="v.variant" plain>
            {{ v.label }}
          </Badge>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
          <Badge v-for="v in variants" :key="v.variant" :variant="v.variant" plain with-icon>
            {{ v.label }}
          </Badge>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-2 text-xl font-medium tracking-tighter">Dalam konteks</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Badge memakai
        <code class="bg-muted rounded px-1 py-0.5 text-sm">inline-flex</code>
        +
        <code class="bg-muted rounded px-1 py-0.5 text-sm">w-fit</code>
        sehingga tetap inline dan lebarnya menyesuaikan konten.
      </p>
      <div class="bg-background flex flex-col gap-4 rounded-2xl border p-4 sm:p-8">
        <div class="flex items-center gap-x-2">
          <h3 class="text-lg font-medium tracking-tight">Megabuild Indonesia</h3>
          <Badge variant="success" icon="hugeicons:checkmark-circle-02">Published</Badge>
        </div>
        <p class="text-sm tracking-tight">
          Status reservasi saat ini adalah
          <Badge variant="warning">Pending payment</Badge>
          dan menunggu konfirmasi dari tim.
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <Badge variant="info" icon="hugeicons:information-circle">Draft</Badge>
          <Badge variant="destructive" icon="hugeicons:cancel-circle">Cancelled</Badge>
          <Badge variant="muted">Archived</Badge>
        </div>
      </div>
    </section>
  </div>
</template>
