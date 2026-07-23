<template>
  <div>
    <Sidebar
      v-if="getRouteBaseName(route) === 'news-slug'"
      side="right"
      variant="sidebar"
      class="top-(--navbar-height-desktop) border-transparent"
    >
      <SidebarContent class="scroll-fade-y relative pb-10">
        <button
          v-if="isMobile"
          @click="setOpenMobile(false)"
          class="bg-muted text-foreground absolute top-4 right-4 flex size-8 items-center justify-center rounded-lg transition active:scale-98"
        >
          <Icon name="lucide:x" class="size-4" />
        </button>

        <div
          class="grid grid-cols-1 px-4 pb-8 *:py-4"
          :class="{
            'pt-2': isMobile,
          }"
        >
          <!-- Desktop only: below 1024px the sidebar turns into a sheet and the
               table of contents lives in the sticky ScrollSpyPopover instead. -->
          <ScrollSpy
            v-if="!isMobile"
            v-show="foundHeadings?.length > 0"
            :content-selector="`#${post.slug}`"
            @headings-found="onHeadingsFound"
          />

          <!-- <ClientOnly>
            <LazyBlogPostRelated variant="sidebar" title="Editor's Picks" />
          </ClientOnly> -->
        </div>
      </SidebarContent>
      <!-- <SidebarRail /> -->
    </Sidebar>

    <div v-if="pending" class="min-h-screen-offset grid place-items-center">
      <div class="flex items-center gap-2">
        <Spinner class="size-4 text-foreground" />
        <span class="tracking-tight">{{ $t('ui.loading') }}</span>
      </div>
    </div>

    <div v-else-if="post" class="pb-24">
      <!-- Mobile "On this page". `lg:hidden` matches the sidebar's own mobile
           breakpoint (max-width: 1024px), so exactly one of the two is visible. -->
      <ScrollSpyPopover
        class="lg:hidden"
        :content-selector="`#${post.slug}`"
        :title="post.title"
      />

      <div class="container-wider flex items-start justify-between gap-x-12">
        <main class="mx-auto w-full max-w-[38rem] py-4">
          <div class="flex items-center justify-between lg:-mx-3">
            <ButtonBack />
            <DialogShare :pageTitle="title" />
          </div>

          <div class="mt-4 flex flex-col items-center text-center xl:items-center xl:text-center">
            <span
              v-if="post.tags?.length > 0"
              class="text-foreground border-border mb-3 flex items-center justify-center rounded-full border px-3 py-2 text-xs font-semibold tracking-tighter capitalize sm:text-sm"
            >
              {{ post.tags[0] }}
            </span>

            <h1
              class="text-foreground text-[clamp(2rem,9vw,3rem)] !leading-[1.2] font-semibold tracking-tighter text-balance xl:-mx-12"
            >
              {{ post.title }}
            </h1>

            <div v-if="post.authors?.length" class="mt-4">
              <div class="flex items-center gap-x-2 text-left">
                <div class="flex shrink-0 -space-x-4">
                  <div
                    v-for="(author, index) in post.authors"
                    :key="index"
                    class="gradient-insta relative rounded-full bg-linear-to-tr p-0.5"
                    :style="`z-index: ${post.authors.length - index}`"
                  >
                    <div
                      class="border-background bg-muted flex size-10 items-center justify-center overflow-hidden rounded-full border-2"
                    >
                      <img
                        v-if="author.profile_image"
                        :src="
                          author.profile_image?.sm ||
                          author.profile_image?.original
                        "
                        class="size-full object-cover"
                        width="56"
                        height="56"
                        loading="lazy"
                      />
                    </div>
                  </div>
                </div>

                <div class="flex flex-col gap-y-1">
                  <div class="text-foreground line-clamp-1 font-medium tracking-tight">
                    <span v-for="(author, index) in post.authors" :key="index">
                      {{ author.name
                      }}<span v-if="index != Object.keys(post.authors).length - 1">, </span>
                    </span>
                  </div>

                  <!-- <div
                    class="text-muted-foreground line-clamp-1 text-xs tracking-tight"
                  >
                    <span
                      v-for="(author, index) in post.authors"
                      :key="index"
                    >
                      @{{ author.username
                      }}<span
                        v-if="index != Object.keys(post.authors).length - 1"
                        >,
                      </span>
                    </span>
                  </div> -->
                </div>
              </div>
            </div>

            <div
              class="text-muted-foreground mt-4 flex w-full items-center justify-between gap-x-3 text-xs tracking-tight sm:text-sm"
            >
              <span
                v-if="post.published_at"
                v-tippy="$dayjs(post.published_at).format('MMM D, YYYY h:mm A')"
              >
                {{ $t('ui.posted') }} {{ $dayjs(post.published_at).fromNow() }}
              </span>

              <span v-if="post.reading_time" class="flex items-center gap-x-1.5">
                <Icon name="lucide:clock-fading" class="size-4 shrink-0" />
                <span
                  >{{ $t('ui.readingTime', post.reading_time, { n: post.reading_time }) }}</span
                >
              </span>
            </div>

            <div
              v-if="post.excerpt"
              class="text-foreground mt-10 text-xl font-semibold tracking-tighter text-pretty sm:text-2xl"
            >
              {{ post.excerpt }}
            </div>
          </div>

          <button
            v-if="post.featured_image"
            type="button"
            class="mx-auto mt-10 block w-full cursor-zoom-in overflow-hidden rounded-xl"
            aria-label="View image"
            @click="openArticleLightbox(0)"
          >
            <BlurImage
              :src="
                post.featured_image?.lg?.url ||
                post.featured_image?.md?.url ||
                post.featured_image?.original
              "
              :lqip="post.featured_image?.lqip?.url"
              :alt="post.title"
              :width="post.featured_image?.lg?.width || post.featured_image?.width || 1200"
              :height="post.featured_image?.lg?.height || post.featured_image?.height || 800"
              loading="lazy"
              image-class="size-full rounded-xl object-cover"
              :image-style="{
                'view-transition-name': `post-feature-img-${post.slug}`,
              }"
            />
          </button>
          <p
            v-if="post.featured_image?.caption"
            class="text-muted-foreground mt-2 text-center text-sm"
          >
            {{ post.featured_image.caption }}
          </p>

          <div
            class="format-html prose-img:rounded-xl prose-img:cursor-zoom-in prose-headings:scroll-mt-[calc(var(--navbar-height-mobile)+var(--scroll-offset,2.5rem))] mx-auto mt-6 overflow-x-hidden [--scroll-offset:5rem] lg:mt-8 lg:[--scroll-offset:2.5rem]"
          >
            <article :id="post.slug" v-html="processedHtml"></article>

            <div v-if="post.tags?.length" class="mt-8 flex items-start gap-x-3 lg:mt-10">
              <Icon name="hugeicons:tag-01" class="mt-2.5 size-5 shrink-0" />
              <div class="flex flex-wrap gap-x-2 gap-y-3">
                <span
                  v-for="(tag, index) in post.tags"
                  :key="index"
                  class="border-border rounded-full border px-3 py-2 text-sm capitalize"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </div>

          <!-- Article lightbox: featured + content images, swipeable -->
          <Lightbox
            v-if="lightboxItems.length"
            v-model:open="lightboxOpen"
            v-model:index="lightboxIndex"
            :items="lightboxItems"
            full-key="lg"
            show-counter
            show-caption
            :show-thumbnails="lightboxItems.length > 1"
            :loop="lightboxItems.length > 1"
          >
            <template #trigger>
              <span class="hidden" aria-hidden="true" />
            </template>
          </Lightbox>

          <div class="mt-10 flex flex-col items-center gap-y-4">
            <span class="text-foreground text-center text-lg font-semibold tracking-tighter sm:text-xl"
              >{{ $t('ui.shareThisPost') }}</span
            >
            <SharePage
              model="post"
              :title="post.title"
              :url="`${useAppConfig().app.url}${localePath(`/news/${post.slug}`)}`"
            />
          </div>
        </main>
      </div>

      <LazyBlogPostRelated
        variant="grid"
        class="container-wider mt-10 transition-all duration-200 ease-linear"
        :class="{
          'pr-(--sidebar-width)': open && !isMobile,
        }"
      />
    </div>
  </div>
</template>

<script setup>
const route = useRoute();
const localePath = useLocalePath();
const getRouteBaseName = useRouteBaseName();

const { $dayjs } = useNuxtApp();

import { useSidebar } from "@/components/ui/sidebar/utils";
const { open, isMobile, setOpenMobile } = useSidebar();

// Set OG image state before async boundary so DevTools can detect it during Suspense
if (import.meta.dev) {
  useState(`og-image:ssr-exists:${route.path}`, () => false).value = true;
}

// Call local Nuxt server API (which proxies to PM One API)
// API key is kept secure on the server, not exposed to browser
const { data, pending, error } = await useFetch(`/api/blog/posts/${route.params.slug}`);

const post = computed(() => data?.value?.data);

if (!post.value) {
  throw createError({
    statusCode: 404,
    statusMessage: "Page not found",
  });
}

function generatePostExcerpt(postBody) {
  // Match the first paragraph with formatting tags
  const regex = /<p[^>]*>(.*?)<\/p>/i;
  const match = regex.exec(postBody);

  if (match && match[1]) {
    // Remove HTML tags from the matched content
    const textWithoutTags = match[1].replace(/<[^>]+>/g, "");
    // Trim any leading or trailing whitespace
    const trimmedText = textWithoutTags.trim();
    return trimmedText;
  } else {
    // If no match found, return an empty string
    return "";
  }
}

const title = computed(() => post.value?.meta_title || post.value?.title || "");
const description = computed(
  () =>
    post.value?.meta_description ||
    post.value?.excerpt ||
    generatePostExcerpt(post.value?.content) ||
    ""
);

usePageMeta(null, {
  title: title,
  description: description,
});

const rawHtml = computed(() => post.value?.content || "");
const { processedHtml } = useProcessedContent(rawHtml);

// Apply LQIP blur effect to content images
const articleSelector = computed(() => (post.value?.slug ? `#${post.value.slug}` : null));
useContentImageBlur(articleSelector);

// --- Article lightbox: featured image + every content image in one
// swipeable gallery. Content images live inside v-html, so they are
// collected from the DOM after mount and opened via event delegation.
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const contentLightboxItems = ref([]);
let contentImageNodes = [];
let lightboxContainer = null;

const featuredLightboxItem = computed(() => {
  const featured = post.value?.featured_image;
  if (!featured) return null;

  return {
    url: featured.original || featured.lg?.url || featured.md?.url,
    lg: featured.lg?.url,
    alt: post.value?.title,
    caption: featured.caption || undefined,
  };
});

const lightboxItems = computed(() => [
  ...(featuredLightboxItem.value ? [featuredLightboxItem.value] : []),
  ...contentLightboxItems.value,
]);

const featuredOffset = computed(() => (featuredLightboxItem.value ? 1 : 0));

function openArticleLightbox(index) {
  lightboxIndex.value = index;
  lightboxOpen.value = true;
}

function collectContentImages() {
  const container = post.value?.slug ? document.getElementById(post.value.slug) : null;
  if (!container) return;

  // Exclude the aria-hidden LQIP <img> that useContentImageBlur adds
  // alongside each real image inside its .blur-image-content wrapper.
  contentImageNodes = Array.from(container.querySelectorAll("img:not([aria-hidden='true'])"));
  contentLightboxItems.value = contentImageNodes.map((img) => ({
    url: img.currentSrc || img.src,
    alt: img.alt || post.value?.title,
    caption: img.dataset.caption || undefined,
  }));
}

// One delegated listener survives v-html re-renders and clicks on the
// LQIP/blur wrapper that useContentImageBlur adds around each image.
function handleArticleClick(event) {
  const hit = event.target.closest?.("img, .blur-image-content");
  if (!hit) return;

  const img =
    hit.tagName === "IMG" && hit.getAttribute("aria-hidden") !== "true"
      ? hit
      : hit.querySelector("img:not([aria-hidden='true'])");
  if (!img) return;

  const index = contentImageNodes.indexOf(img);
  if (index === -1) return;

  openArticleLightbox(featuredOffset.value + index);
}

onMounted(async () => {
  await nextTick();
  collectContentImages();

  lightboxContainer = post.value?.slug ? document.getElementById(post.value.slug) : null;
  lightboxContainer?.addEventListener("click", handleArticleClick);
});

onBeforeUnmount(() => {
  lightboxContainer?.removeEventListener("click", handleArticleClick);
});

// Locale switches swap the v-html content; re-collect the fresh nodes
watch(processedHtml, async () => {
  await nextTick();
  collectContentImages();
});

const foundHeadings = ref([]);

const onHeadingsFound = (headings) => {
  foundHeadings.value = headings;
};

onMounted(async () => {
  // Tunggu DOM di-hydrate sepenuhnya
  await nextTick();

  // Cek apakah URL memiliki hash
  if (route.hash) {
    // PERBAIKAN: "Bersihkan" hash sebelum digunakan sebagai selector
    let selector = route.hash;
    if (selector.startsWith("#") && /\d/.test(selector.charAt(1))) {
      selector = `[id="${selector.substring(1)}"]`;
    }

    // Retry mechanism untuk memastikan element sudah ada di DOM
    const scrollToElement = (retries = 5) => {
      const element = document.querySelector(selector);
      if (element) {
        element.scrollIntoView({ behavior: "smooth", block: "start" });
      } else if (retries > 0) {
        setTimeout(() => scrollToElement(retries - 1), 100);
      }
    };

    scrollToElement();
  }
});
</script>
