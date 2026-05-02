<template>
  <!--
    Gunakan scoped slot untuk "mengirim" fungsi goBack ke parent.
    Ini memberikan fleksibilitas penuh pada parent untuk mendesain UI-nya.
  -->
  <slot :goBack="goBack">
    <!--
      TEMPLATE DEFAULT:
      Ini akan ditampilkan jika parent tidak menyediakan slot kustom.
    -->
    <button
      @click="goBack"
      :class="[
        'flex items-center justify-center gap-x-1 text-sm tracking-tight transition active:scale-98',
        variantClass,
      ]"
      v-ripple
    >
      <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
      <span v-if="showLabel && variant !== 'semiTransparent'">Back</span>
      <KbdGroup
        v-if="showLabel && variant !== 'semiTransparent' && shortcut && shortcutEnabled"
      >
        <Kbd>B</Kbd>
      </KbdGroup>
    </button>
  </slot>
</template>

<script setup>
// Prop 'destination' sekarang bersifat opsional.
const props = defineProps({
  destination: {
    type: String,
    default: null,
  },
  showLabel: {
    type: Boolean,
    default: true,
  },
  forceDestination: {
    type: Boolean,
    default: false,
  },
  variant: {
    type: String,
    default: "default",
    validator: (v) => ["default", "bordered", "semiTransparent"].includes(v),
  },
  shortcut: {
    type: Boolean,
    default: true,
  },
});

const router = useRouter();
const route = useRoute();

const shortcutEnabled = computed(() => {
  return !/\/posts\/(create|[^/]+\/edit)/.test(route.path);
});

const variantClass = computed(() => {
  switch (props.variant) {
    case "bordered":
      return "text-primary lg:hover:bg-muted bg-background border-border rounded-full border p-3 lg:border-0";
    case "semiTransparent":
      return "text-primary bg-background/70 border border-white/10 shadow-lg backdrop-blur-sm rounded-full p-3";
    default:
      return "text-primary/80 hover:text-primary";
  }
});

defineShortcuts({
  b: {
    handler: () => goBack(),
    whenever: [() => props.shortcut && shortcutEnabled.value],
  },
});

// Buat computed property untuk menentukan tujuan fallback secara dinamis.
const fallbackDestination = computed(() => {
  // Prioritas 1: Gunakan prop 'destination' jika disediakan secara manual.
  if (props.destination) {
    return props.destination;
  }

  // Prioritas 2: Jika tidak, hitung path induk dari URL saat ini.
  // Contoh: '/news/some-article' menjadi '/news'
  const pathSegments = route.path.split("/").filter((p) => p); // Pecah path dan hapus string kosong

  // Jika path hanya memiliki satu segmen (misal: '/news') atau kurang, kembali ke halaman utama.
  if (pathSegments.length <= 1) {
    return "/";
  }

  pathSegments.pop(); // Hapus segmen terakhir
  return "/" + pathSegments.join("/"); // Gabungkan kembali
});

const goBack = () => {
  // Jika forceDestination aktif, langsung arahkan ke fallbackDestination
  if (props.forceDestination) {
    router.push(fallbackDestination.value);
    return;
  }

  // Cek apakah ada histori navigasi di dalam sesi browser saat ini.
  if (window?.history?.length > 2) {
    // Jika ada, kembali ke halaman sebelumnya
    router.back();
  } else {
    // Jika tidak ada, arahkan ke tujuan fallback yang sudah kita tentukan.
    router.push(fallbackDestination.value);
  }
};
</script>
