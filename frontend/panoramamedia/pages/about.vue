<template>
  <div class="space-y-16 lg:space-y-24">
    <ComingSoon title="About" />
    <!-- <AboutHero /> -->

    <!-- <div class="container-wider">
      <div
        class="bg-border *:bg-background grid grid-cols-1 gap-px p-px *:relative *:px-4 *:py-8 sm:*:px-6 sm:*:py-12 lg:grid-cols-2 xl:*:px-8 xl:*:py-16"
      >
        <section id="our-story" class="flex flex-col">
          <Cross position="top-left" />

          <h2 class="text-primary tracking-tighter">Our Story</h2>
          <span
            class="text-primary mt-2 text-4xl !leading-[1.2] font-bold tracking-tighter text-balance sm:text-5xl"
            >The Shift to Purposeful Outings</span
          >
          <div
            class="text-primary/70 format-html space-y-4 text-xl font-medium tracking-tighter sm:text-2xl"
          >
            <p>
              We've seen it all, the fun but forgettable day trips, the team
              building that feels a bit forced, and the outings that don't quite
              hit the mark. We knew there had to be a better way. We saw a gap
              between what companies hoped to achieve with their outings
              (stronger collaboration, higher morale, a thriving culture) and
              what they often received.
            </p>

            <p>So, we evolved.</p>

            <p>
              We channeled our two decades of event expertise into a singular,
              passionate focus: Corporate Outings that are strategically
              designed for deep engagement. We expertly blend the thrill of new
              adventures with the science of team dynamics, ensuring every
              journey is not just fun, but fundamentally transformational for
              your team.
            </p>
          </div>
        </section>

        <section id="our-philosophy" class="flex flex-col">
          <Cross position="top-right" />
          <Cross position="bottom-left" />

          <h2 class="text-primary tracking-tighter">Our Philosophy</h2>
          <span
            class="text-primary mt-2 text-4xl !leading-[1.2] font-bold tracking-tighter text-balance sm:text-5xl"
            >The HR-Driven Approach</span
          >
          <div
            class="text-primary/70 format-html space-y-4 text-xl font-medium tracking-tighter sm:text-2xl"
          >
            <p>
              "HR-Driven" isn't just a buzzword for us. It's the very foundation
              of our planning process. It means we view your corporate outing as
              a strategic investment in your most valuable asset: your people.
            </p>

            <span>Here’s what our philosophy means for you and your team:</span>
            <ul>
              <li>
                We Listen First, Plan Second: We start by deeply understanding
                your company culture, your current challenges, and your specific
                HR objectives. Your goals become our blueprint.
              </li>
              <li>
                We Design with Intention: Every activity, every session, and
                every experience is carefully chosen to support those goals,
                creating a custom journey that encourages the specific
                connections and behaviors you want to foster.
              </li>
              <li>
                We Focus on People: We create an inclusive and positive
                environment where every team member feels valued, heard, and can
                genuinely connect with their colleagues on a human level.
              </li>
              <li>
                We Aim for Lasting Impact: Our ultimate goal is that the energy,
                the bonds, and the key lessons learned during the outing travel
                back with your team to the office, creating a positive ripple
                effect that endures.
              </li>
            </ul>
          </div>
        </section>

        <section id="vision" class="flex flex-col">
          <Cross position="top-left" />

          <h2 class="text-primary tracking-tighter">Vision</h2>

          <div
            class="text-primary/70 mt-4 space-y-4 text-xl font-medium tracking-tighter sm:text-2xl"
          >
            <p>
              To be the leading event organizer, renowned for crafting unique
              experiences with deep cultural impacts and sustainable practices.
              Making every event not just an occasion, but a landmark moment in
              the world of event organization.
            </p>
          </div>
        </section>

        <section id="mission" class="flex flex-col">
          <Cross position="bottom-right" />

          <h2 class="text-primary tracking-tighter">Mission</h2>

          <div
            class="text-primary/70 mt-4 space-y-4 text-xl font-medium tracking-tighter sm:text-2xl"
          >
            <p>
              To deliver exceptional, culturally rich, and environmentally
              sustainable events that exceed our clients’ expectations. We are
              committed to innovation, constantly exploring new ideas and
              technologies to create unique and memorable experiences for every
              attendee.
            </p>
          </div>
        </section>

        <section id="values" class="flex flex-col">
          <Cross position="top-left" />

          <h2 class="text-primary tracking-tighter">Values</h2>

          <div
            class="text-primary/70 mt-4 space-y-4 text-xl font-medium tracking-tighter sm:text-2xl"
          >
            <p>
              Our core values stemmed from our SPIRIT, with each letter
              representing a strong identity of ourselves and what is important
              to us.
            </p>
          </div>

          <div class="mt-6 grid grid-cols-1 gap-y-6">
            <div
              v-for="(item, index) in values"
              :key="index"
              class="flex items-start gap-x-4"
            >
              <span
                class="border-border flex size-10 shrink-0 items-center justify-center rounded-lg border"
              >
                <Icon
                  v-if="item.iconName"
                  :name="item.iconName"
                  class="text-primary size-6"
                />
              </span>

              <div class="flex flex-col items-start gap-y-0.5">
                <span
                  class="text-primary text-base font-semibold tracking-tighter text-balance sm:text-lg"
                  >{{ item.title }}</span
                >
                <span class="text-primary/70 tracking-tighter text-balance">{{
                  item.description
                }}</span>
              </div>
            </div>
          </div>

          <div class="mt-12 flex flex-col items-start">
            <span
              class="text-primary text-base font-semibold tracking-tighter sm:text-lg"
              >We embrace
              <span class="text-green-600 uppercase dark:text-green-500"
                >Green Initiatives</span
              ></span
            >

            <div class="mt-4 flex flex-col gap-y-4">
              <div class="flex gap-x-3">
                <Icon
                  name="hugeicons:leaf-03"
                  class="size-6 shrink-0 text-green-600 dark:text-green-500"
                />
                <p class="text-primary/70 text-sm tracking-tight">
                  Eco-friendly venues to reduce carbon footprint, along with
                  local and sustainable sourcing to minimize waste.
                </p>
              </div>

              <div class="flex gap-x-3">
                <Icon
                  name="hugeicons:agreement-01"
                  class="size-6 shrink-0 text-green-600 dark:text-green-500"
                />
                <p class="text-primary/70 text-sm tracking-tight">
                  Green Team: Engage in team-building activities focused on
                  environmental sustainability.
                </p>
              </div>
            </div>
          </div>
        </section>

        <section id="service-soul" class="flex flex-col">
          <Cross position="bottom-right" />

          <h2 class="text-primary tracking-tighter">Service Soul</h2>

          <div
            class="text-primary/70 mt-4 space-y-4 text-xl font-medium tracking-tighter sm:text-2xl"
          >
            <p>
              We are a service company and serving people is deeply rooted in
              our beliefs and actions. We believe that there should be a high
              standard in how everyone, especially us, should serve others.
            </p>
          </div>

          <div class="mt-6 grid grid-cols-1 gap-y-6">
            <div
              v-for="(item, index) in serviceSoul"
              :key="index"
              class="flex items-start gap-x-4"
            >
              <span
                class="border-border flex size-10 shrink-0 items-center justify-center rounded-lg border"
              >
                <Icon
                  v-if="item.iconName"
                  :name="item.iconName"
                  class="text-primary size-6"
                />
              </span>

              <div class="flex flex-col items-start gap-y-0.5">
                <span
                  class="text-primary text-base font-semibold tracking-tighter text-balance sm:text-lg"
                  >{{ item.title }}</span
                >
                <span class="text-primary/70 tracking-tighter text-balance">{{
                  item.description
                }}</span>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div> -->

    <!-- <WhyChooseUs /> -->
  </div>
</template>

<script setup>
usePageMeta("about");

const values = [
  {
    title: "Synergy",
    description:
      "Complementing and taking advantage of our various strengths to achieve greater results.",
    iconName: "hugeicons:installing-updates-02",
  },
  {
    title: "Pursuite of Excellence",
    description: "Striving continuously to be the best in everything we do.",
    iconName: "hugeicons:star-circle",
  },
  {
    title: "Integrity",
    description:
      "Fairness in business dealings and consistently respects the highest standard of business ethics.",
    iconName: "hugeicons:ungroup-layers",
  },
  {
    title: "Reliability",
    description: "Going the extra-mile to consistently deliver as promised.",
    iconName: "hugeicons:security-validation",
  },
  {
    title: "Innovative & Proactive",
    description:
      "Continuously seek for new ways to deliver unique services and values.",
    iconName: "hugeicons:ai-idea",
  },
  {
    title: "Truly Care",
    description: "Being compassionate and bringing smiles to millions.",
    iconName: "hugeicons:favourite-circle",
  },
];

const serviceSoul = [
  {
    title: "Reliability",
    description: "Delivering as promised, consistently.",
    iconName: "hugeicons:security-check",
  },
  {
    title: "Convenience",
    description: "Making things easy for customers.",
    iconName: "hugeicons:thumbs-up",
  },
  {
    title: "Comfort",
    description: "Making customers feel good.",
    iconName: "hugeicons:wedding",
  },
  {
    title: "Fair Value",
    description: "Providing best-possible benefits.",
    iconName: "hugeicons:discount-tag-02",
  },
  {
    title: "Unique Experience",
    description: "Creating “one-of-a-kind” moments.",
    iconName: "hugeicons:ski-dice-faces-01",
  },
  {
    title: "Flexible Solutions",
    description: "Smartly addressing customers needs & expectations.",
    iconName: "hugeicons:sine-02",
  },
  {
    title: "Recognition",
    description: "Valuing customers by meaningful appreciation & respect.",
    iconName: "hugeicons:hand-prayer",
  },
];
</script>
