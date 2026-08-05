<script setup>
// The one OG card template. Keep this file byte-identical across levenium,
// pmone, and pmone-events — only its LOCATION differs, because the three repos
// are laid out differently:
//
//   levenium      layers/ui/app/components/OgImage/     (layers/ui is the only
//                                                        common ancestor: it is
//                                                        extended by layers/base
//                                                        AND directly by apps/ui,
//                                                        which skips base)
//   pmone-events  layers/base/app/components/OgImage/   (no layers/ui exists here;
//                                                        every app extends base)
//   pmone         app/components/OgImage/               (single app, no layers)
//
// Keep it the ONLY OgImage/Page.takumi.vue in its repo. A second copy in a
// nearer layer shadows this one by component name and silently forks the card —
// that is exactly how pmone-events/apps/campx ended up rendering a stale design.
//
// The `.takumi` suffix picks the renderer. `ogImage.defaults.renderer` was
// removed in nuxt-og-image v6; the filename is the only way to choose.
//
// Tailwind classes are safe here even when the build-time CSS transform fails:
// takumi resolves `class` itself via its own Tailwind implementation, and the
// transform is only an augmentation layer that additionally resolves project
// tokens. So stick to literal colors and arbitrary values — avoid token-based
// utilities (`bg-input`, `border-border`, …), which takumi cannot resolve alone.
defineOptions({ inheritAttrs: false });

defineProps({
  pageTitle: {
    type: String,
    default: "",
  },
  pageDescription: {
    type: String,
    default: "",
  },
});

const appConfig = useAppConfig();
const isDarkMode = ref(appConfig.settings.ogImage.isDarkMode);

// Configurable so this file stays identical in pmone, which is multi-brand and
// serves each brand's icon from /brands/<brandId>/icons/. Single-brand apps
// leave it unset and get the app-level icon.
const iconSrc =
  appConfig.settings.ogImage.icon || "/icons/icon-192x192.png";
</script>

<template>
  <div
    class="relative flex h-full w-full flex-col px-[100px] py-[100px] antialiased"
    :class="isDarkMode ? 'bg-black text-white' : 'bg-white text-black'"
  >
    <div
      class="absolute inset-y-0 left-[100px] border-l-2 border-solid"
      :class="isDarkMode ? 'border-white/25' : 'border-black/15'"
    ></div>

    <div
      class="absolute inset-y-0 right-[100px] border-r-2 border-solid"
      :class="isDarkMode ? 'border-white/25' : 'border-black/15'"
    ></div>

    <div
      class="absolute inset-x-0 top-[100px] border-t-2 border-solid"
      :class="isDarkMode ? 'border-white/25' : 'border-black/15'"
    ></div>

    <div
      class="absolute inset-x-0 bottom-[100px] border-b-2 border-solid"
      :class="isDarkMode ? 'border-white/25' : 'border-black/15'"
    ></div>

    <div class="flex h-full flex-col text-left">
      <h1
        v-if="pageTitle"
        class="mt-2 shrink-0 text-[72px] leading-[1.1] font-normal tracking-[-0.06em]"
        style="line-clamp: 2; text-overflow: ellipsis; overflow: hidden"
      >
        {{ pageTitle }}
      </h1>
      <p
        v-if="pageDescription"
        class="mt-3 shrink-0 text-[32px] leading-[1.6] font-normal tracking-tighter text-pretty"
        style="line-clamp: 2; text-overflow: ellipsis; overflow: hidden"
      >
        {{ pageDescription }}
      </p>

      <div
        class="mt-auto flex flex-row items-center"
        style="transform: translateY(50%)"
      >
        <div
          class="outline-inside flex size-20 items-center justify-center overflow-hidden rounded-2xl"
        >
          <img
            :src="iconSrc"
            class="size-20 object-contain"
            alt=""
            width="80"
            height="80"
          />
        </div>

        <div class="ml-4 flex flex-col items-start gap-y-2">
          <span
            v-if="appConfig.app.name"
            class="text-3xl font-normal tracking-tighter"
            >{{ appConfig.app.name }}</span
          >
          <span v-if="appConfig.app.url" class="text-2xl tracking-tight">{{
            appConfig.app.url
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
