<template>
  <div class="container flex flex-col items-center lg:max-w-(--breakpoint-sm)">
    <h1 class="section-title text-center font-semibold">{{ content.title }}</h1>

    <div v-if="faq?.list?.length" class="mt-6 flex w-full flex-col gap-y-3">
      <Accordion type="single" collapsible>
        <AccordionItem
          v-for="(item, index) in faq.list"
          :key="index"
          :value="`faq-item-${index}`"
        >
          <AccordionTrigger>
            {{ item.q }}
          </AccordionTrigger>
          <AccordionContent>
            <div class="format-html" v-html="item.a"></div>
          </AccordionContent>
        </AccordionItem>
      </Accordion>
    </div>

    <div v-else class="mt-3 text-center text-base tracking-tight sm:text-lg">
      {{ content.emptyStateDescription }}
    </div>

    <div class="mt-10 lg:mt-16">
      <h6
        class="text-center text-3xl font-semibold tracking-tight text-balance text-black sm:text-4xl dark:text-white"
      >
        {{ content.contactTitle }}
      </h6>
      <div class="mt-4 flex items-center justify-center lg:mt-6">
        <nuxt-link
          to="/contact"
          class="bg-muted text-primary hover:bg-primary hover:text-primary-foreground flex items-center gap-1.5 rounded-full p-4 font-semibold tracking-tighter transition duration-200 active:scale-98"
        >
          <span>Contact us</span>
        </nuxt-link>
      </div>
    </div>
  </div>
</template>

<script setup>
const content = useContentStore().components.faq;
const faq = new useFAQStore();
</script>
