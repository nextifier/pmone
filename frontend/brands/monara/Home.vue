<!-- Placeholder landing for the Monara brand. TODO(owner): redesign this page
     once the Monara visual identity and positioning copy are ready. -->
<template>
  <div class="flex min-h-screen-offset flex-col">
    <section class="flex flex-1 items-center">
      <div class="container">
        <div class="mx-auto flex max-w-2xl flex-col items-center py-24 text-center lg:py-32">
          <Logo class="text-foreground h-7" />

          <h1
            class="text-foreground mt-8 text-4xl leading-[1.15]! font-medium tracking-tighter text-balance sm:text-6xl"
          >
            Manage events, exhibitors, and content from one dashboard.
          </h1>

          <p
            class="text-body mt-4 max-w-xl text-base leading-relaxed tracking-tight text-pretty sm:text-lg"
          >
            Projects, brands, orders, short links, blog posts, analytics, forms, and tasks.
            Connected and searchable under one login.
          </p>

          <div class="mt-8 flex gap-2">
            <Button to="/login" size="lg" variant="default" v-ripple> Get Started </Button>
          </div>
        </div>
      </div>
    </section>

    <footer class="pb-10">
      <div class="container">
        <div class="text-muted-foreground flex items-center justify-center text-center text-xs sm:text-sm">
          <span>Copyright &copy; {{ new Date().getFullYear() }} Monara. All rights reserved.</span>
        </div>
      </div>
    </footer>
  </div>
</template>
