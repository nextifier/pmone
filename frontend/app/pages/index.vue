<template>
  <div class="relative overflow-hidden">
    <!-- Hero Section -->
    <HomeHero />

    <!-- Trusted By Section -->
    <HomeTrustedBy />

    <!-- Problem Section -->
    <HomeProblem />

    <!-- Feature: Projects Management -->
    <HomeFeatureSection
      badge="Projects"
      icon="lucide:folder-kanban"
      icon-bg-class="bg-orange-500"
      hover-color-class="group-hover:text-orange-500"
      title="Keep every project organized.<br /><span class='text-muted-foreground'>Without the headache.</span>"
      description="Remember that time you lost an important file in a sea of folders? Or couldn't find who was responsible for what? Yeah, those days are over."
      link="/projects"
      link-text="Explore Projects"
      :features="projectFeatures"
      background
    />

    <!-- Testimonial 1 -->
    <HomeTestimonial
      quote="Finally, a platform that doesn't make me want to pull my hair out. Everything's in one place, and my team actually enjoys using it."
      author="Sarah Chen"
      role="Event Director at TechSummit"
      avatar="https://api.dicebear.com/7.x/avataaars/svg?seed=sarah"
    />

    <!-- Feature: Links & Analytics -->
    <HomeFeatureSection
      badge="Links & Analytics"
      icon="lucide:link"
      icon-bg-class="bg-green-500"
      hover-color-class="group-hover:text-green-500"
      title="Short links that actually tell you something."
      description="Ever shared a link and wondered if anyone clicked it? Or where your traffic actually came from? Stop guessing. Start knowing."
      link="/links"
      link-text="Explore Links"
      :features="linkFeatures"
      background
    />

    <!-- Feature: Content Publishing -->
    <HomeFeatureSection
      badge="Content Publishing"
      icon="lucide:file-text"
      icon-bg-class="bg-purple-500"
      hover-color-class="group-hover:text-purple-500"
      title="Write. Schedule. Publish.<br /><span class='text-muted-foreground'>Without the WordPress drama.</span>"
      description="Your blog shouldn't need a PhD to manage. Create beautiful content with a clean editor, schedule posts when your audience is awake, and watch the engagement roll in."
      link="/posts"
      link-text="Explore Content"
      :features="contentFeatures"
    />

    <!-- Testimonial 2 -->
    <HomeTestimonial
      quote="The QR codes and link analytics helped us understand our offline marketing for the first time. Game changer for our event promotions."
      author="Marcus Rodriguez"
      role="Marketing Lead at StartupXYZ"
      avatar="https://api.dicebear.com/7.x/avataaars/svg?seed=marcus"
      background
    />

    <!-- Feature: API & Integrations -->
    <HomeFeatureSection
      badge="API & Integrations"
      icon="lucide:code"
      icon-bg-class="bg-blue-500"
      hover-color-class="group-hover:text-blue-500"
      title="Plays nice with your existing tools."
      description="Already have a tech stack you love? Keep it. Our API lets you connect PM One to anything. Google Analytics, custom apps, you name it."
      link="/api-consumers"
      link-text="Explore API"
      :features="apiFeatures"
    />

    <!-- Stats Section -->
    <HomeStats />

    <!-- All Features Grid -->
    <HomeAllFeatures />

    <!-- CTA Section -->
    <HomeCTA />

    <!-- Footer -->
    <HomeFooter />
  </div>
</template>

<script setup>
definePageMeta({
  layout: "default",
});

usePageMeta("home");

// Project management features
const projectFeatures = [
  {
    title: "Multi-Project Management",
    description:
      "Run unlimited projects with their own settings, teams, and visibility controls. No more mixing things up.",
    icon: "lucide:layers",
  },
  {
    title: "Team Collaboration",
    description:
      "Bring your crew in with role-based access. Everyone sees what they need to see, nothing more.",
    icon: "lucide:users",
  },
  {
    title: "Project Analytics",
    description: "Know exactly how each project is doing. Real numbers, not guesses.",
    icon: "lucide:activity",
  },
];

// Link features
const linkFeatures = [
  {
    title: "Custom Short Links",
    description: "Create memorable links with your own slugs. No more random string soup.",
    icon: "lucide:link-2",
  },
  {
    title: "QR Code Generator",
    description: "Every link gets a QR code automatically. Print it, share it, stick it on things.",
    icon: "lucide:qr-code",
  },
  {
    title: "Click Analytics",
    description: "See who's clicking, from where, on what device. The full picture.",
    icon: "lucide:mouse-pointer-click",
  },
];

// Content features
const contentFeatures = [
  {
    title: "Rich Text Editor",
    description: "A beautiful editor that just works. Format text, add images, embed stuff. Easy.",
    icon: "lucide:edit-3",
  },
  {
    title: "Scheduled Publishing",
    description: "Write now, publish later. Set it and forget it until it goes live.",
    icon: "lucide:calendar-clock",
  },
  {
    title: "Categories & Tags",
    description: "Keep your content organized so your readers can find what they're looking for.",
    icon: "lucide:tags",
  },
];

// API features
const apiFeatures = [
  {
    title: "RESTful API",
    description: "Well-documented endpoints that actually make sense. Your devs will thank you.",
    icon: "lucide:code",
  },
  {
    title: "Google Analytics",
    description: "Already using GA? Connect it and get even deeper insights.",
    icon: "lucide:line-chart",
  },
  {
    title: "API Key Management",
    description: "Create keys, set limits, whitelist origins. Full control over who accesses what.",
    icon: "lucide:key",
  },
];
</script>
