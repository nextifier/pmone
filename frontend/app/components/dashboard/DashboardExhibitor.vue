<template>
  <div class="mx-auto flex flex-col gap-y-5 pt-2 pb-16 lg:max-w-4xl lg:pt-4 xl:max-w-5xl">
    <!-- Greeting -->
    <DashboardGreeting />

    <!-- Loading -->
    <div v-if="pending" class="space-y-4">
      <div class="h-24 animate-pulse rounded-xl bg-muted" />
      <div class="h-10 animate-pulse rounded-xl bg-muted" />
      <div class="h-16 animate-pulse rounded-xl bg-muted" />
      <div class="h-16 animate-pulse rounded-xl bg-muted" />
    </div>

    <template v-else-if="dashboard">
      <!-- Hero: What's Next -->
      <DashboardExhibitorHero
        :profile-complete="dashboard.profile_complete"
        :brand-events="dashboard.brand_events"
        @action="handleHeroAction"
      />

      <!-- No brand events empty state -->
      <div
        v-if="!dashboard.brand_events?.length"
        class="flex flex-col items-center gap-3 rounded-xl border border-border px-4 py-12"
      >
        <div class="flex size-12 items-center justify-center rounded-full bg-muted">
          <Icon name="hugeicons:calendar-03" class="size-6 text-muted-foreground" />
        </div>
        <p class="text-sm tracking-tight text-muted-foreground">No events found for your brands.</p>
      </div>

      <!-- Per Brand-Event -->
      <template v-for="(be, beIndex) in dashboard.brand_events" :key="be.brand_event_id">
        <!-- Single event: no collapsible wrapper -->
        <div v-if="!hasMultipleEvents" class="space-y-3">
          <DashboardExhibitorEventCard :be="be" />
          <DashboardExhibitorStepper
            :steps="getSteps(be)"
            @jump="(key) => handleJump(be, key)"
          />
          <div :ref="(el) => setSectionRef(be.brand_event_id, 'profile', el)" class="space-y-2.5">
          <!-- Section 1: Profile (only if incomplete) -->
          <DashboardExhibitorSection
            v-if="!dashboard.profile_complete"
            v-model:open="sectionStates[`${be.brand_event_id}_profile`]"
            title="Complete Your Profile"
            icon="hugeicons:user-edit-01"
            :summary="'Fill in all required fields to continue.'"
            badge-text="Required"
            badge-variant="destructive"
            :default-open="true"
            section-key="profile"
          >
            <form class="space-y-4" @submit.prevent="saveProfile">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                  <Label for="ex_name">Full Name</Label>
                  <Input id="ex_name" v-model="profileForm.name" placeholder="Your full name" />
                </div>
                <div class="space-y-2">
                  <Label for="ex_phone">Phone Number</Label>
                  <InputPhone id="ex_phone" v-model="profileForm.phone" />
                </div>
                <div class="space-y-2">
                  <Label for="ex_title">Job Title</Label>
                  <Input id="ex_title" v-model="profileForm.title" placeholder="e.g. Marketing Manager" />
                </div>
                <div class="space-y-2">
                  <Label for="ex_company">Company Name</Label>
                  <Input id="ex_company" v-model="profileForm.company_name" placeholder="Your company" />
                </div>
              </div>
              <Button type="submit" size="sm" :disabled="profileSaving">
                <Icon v-if="profileSaving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                Save Profile
              </Button>
            </form>
          </DashboardExhibitorSection>

          <!-- Section 2: Event Rules -->
          <div :ref="(el) => setSectionRef(be.brand_event_id, 'rules', el)">
            <DashboardExhibitorSection
              v-if="be.event_rules?.length"
              v-model:open="sectionStates[`${be.brand_event_id}_rules`]"
              title="Event Rules"
              icon="hugeicons:file-validation"
              :summary="be.event_rules_agreed ? 'All rules agreed.' : `${rulesAgreedCount(be)}/${be.event_rules.length} rules agreed.`"
              :completed="be.event_rules_agreed"
              :locked="!dashboard.profile_complete"
              :badge-text="!be.event_rules_agreed ? 'Required' : ''"
              badge-variant="destructive"
              :attention-count="rulesNeedingAttention(be)"
              section-key="rules"
            >
              <div class="space-y-4">
                <template v-for="rule in be.event_rules" :key="rule.document.id">
                  <DashboardExhibitorDocItem
                    :doc="rule.document"
                    mode="view"
                  />
                  <div class="flex items-start gap-x-2">
                    <Checkbox
                      :id="`rule_${be.brand_event_id}_${rule.document.id}`"
                      :checked="rule.agreed && !rule.needs_reagreement"
                      :disabled="agreeingId === rule.document.id"
                      @click="handleAgreeRule(be, rule)"
                    />
                    <div>
                      <Label
                        :for="`rule_${be.brand_event_id}_${rule.document.id}`"
                        class="text-sm font-normal leading-snug"
                      >
                        I have read and agree to "{{ rule.document.title }}"
                      </Label>
                      <p v-if="rule.agreed && rule.submission" class="mt-1 text-xs tracking-tight text-muted-foreground sm:text-sm">
                        Agreed on {{ formatDate(rule.submission.agreed_at) }}
                        <span v-if="rule.submission.submitter_name"> by {{ rule.submission.submitter_name }}</span>
                        (v{{ rule.submission.document_version }})
                      </p>
                      <p v-if="rule.needs_reagreement" class="mt-1 text-xs tracking-tight text-amber-600 dark:text-amber-400 sm:text-sm">
                        Rules updated. Please re-agree to the latest version.
                      </p>
                    </div>
                  </div>
                </template>
              </div>
            </DashboardExhibitorSection>
          </div>

          <!-- Section 3: Brand Profile -->
          <div :ref="(el) => setSectionRef(be.brand_event_id, 'brand', el)">
            <DashboardExhibitorSection
              v-model:open="sectionStates[`${be.brand_event_id}_brand`]"
              title="Brand Profile"
              icon="hugeicons:store-02"
              :summary="be.brand_complete ? `${be.brand.name} profile is complete.` : `Missing: ${be.brand.missing_fields.join(', ')}`"
              :completed="be.brand_complete"
              :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
              section-key="brand"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <img
                    v-if="be.brand.brand_logo?.sm"
                    :src="be.brand.brand_logo.sm"
                    :alt="be.brand.name"
                    class="size-10 rounded-lg object-cover"
                  />
                  <div
                    v-else
                    class="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                  >
                    <Icon name="hugeicons:store-02" class="size-5" />
                  </div>
                  <div>
                    <p class="text-sm font-medium">{{ be.brand.name }}</p>
                    <p v-if="!be.brand_complete" class="text-xs tracking-tight text-muted-foreground sm:text-sm">
                      {{ be.brand.missing_fields.length }} field{{ be.brand.missing_fields.length > 1 ? 's' : '' }} remaining
                    </p>
                  </div>
                </div>
                <NuxtLink
                  :to="`/brands/${be.brand.slug}/edit`"
                  class="inline-flex items-center gap-x-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
                >
                  <Icon :name="be.brand_complete ? 'hugeicons:view' : 'hugeicons:edit-02'" class="size-3.5" />
                  {{ be.brand_complete ? "View" : "Complete" }}
                </NuxtLink>
              </div>
            </DashboardExhibitorSection>
          </div>

          <!-- Section 4: Promotion Posts -->
          <div :ref="(el) => setSectionRef(be.brand_event_id, 'promo', el)">
            <DashboardExhibitorSection
              v-model:open="sectionStates[`${be.brand_event_id}_promo`]"
              title="Promotion Posts"
              icon="hugeicons:image-02"
              :summary="`${be.promotion_posts_count}/${be.promotion_post_limit} uploaded`"
              :completed="be.promotion_posts_count > 0"
              :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
              :deadline="formatDeadlineShort(be.promotion_post_deadline)"
              :deadline-urgent="isDeadlineUrgent(be.promotion_post_deadline)"
              section-key="promo"
            >
              <div class="flex items-center justify-between">
                <p class="text-sm tracking-tight text-muted-foreground">
                  {{ be.promotion_posts_count > 0
                    ? `You've uploaded ${be.promotion_posts_count} of ${be.promotion_post_limit} allowed posts.`
                    : `Upload up to ${be.promotion_post_limit} promotion post${be.promotion_post_limit > 1 ? 's' : ''} for this event.`
                  }}
                </p>
                <NuxtLink
                  :to="`/brands/${be.brand.slug}/promotion-posts/${be.brand_event_id}`"
                  class="inline-flex shrink-0 items-center gap-x-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
                >
                  <Icon :name="be.promotion_posts_count > 0 ? 'hugeicons:view' : 'hugeicons:upload-04'" class="size-3.5" />
                  {{ be.promotion_posts_count > 0 ? "Manage" : "Upload" }}
                </NuxtLink>
              </div>
            </DashboardExhibitorSection>
          </div>

          <!-- Section 5: Operational Documents -->
          <div :ref="(el) => setSectionRef(be.brand_event_id, 'docs', el)">
            <DashboardExhibitorSection
              v-if="be.documents?.length || showFasciaField(be) || showBadgeField(be)"
              v-model:open="sectionStates[`${be.brand_event_id}_docs`]"
              title="Operational Documents"
              icon="hugeicons:file-01"
              :summary="docsAndBoothSummary(be)"
              :completed="isDocsComplete(be)"
              :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
              :attention-count="docsNeedingAttention(be)"
              section-key="docs"
            >
              <!-- Document list -->
              <div v-if="be.documents?.length" class="space-y-3">
                <DashboardExhibitorDocItem
                  v-for="doc in be.documents"
                  :key="doc.document.id"
                  :doc="doc.document"
                  :status="doc.status"
                  mode="action"
                />
                <NuxtLink
                  :to="`/brands/${be.brand.slug}/documents/${be.brand_event_id}`"
                  class="mt-1 inline-flex items-center gap-x-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
                >
                  <Icon name="hugeicons:file-01" class="size-3.5" />
                  {{ be.documents_completed === be.documents_total ? "View All" : "Complete Documents" }}
                </NuxtLink>
              </div>

              <!-- Booth Fields (fascia & badge) -->
              <div
                v-if="showFasciaField(be) || showBadgeField(be)"
                :class="{ 'mt-5 border-t border-border pt-5': be.documents?.length }"
              >
                <p class="mb-3 text-sm font-medium">Booth Details</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div v-if="showFasciaField(be)" class="space-y-2">
                    <Label :for="`fascia_${be.brand_event_id}`">Fascia Name</Label>
                    <Input
                      :id="`fascia_${be.brand_event_id}`"
                      :model-value="boothFields[be.brand_event_id]?.fascia_name ?? be.fascia_name ?? ''"
                      placeholder="Name displayed on booth fascia"
                      @update:model-value="(v) => setBoothField(be.brand_event_id, 'fascia_name', v)"
                    />
                  </div>
                  <div v-if="showBadgeField(be)" class="space-y-2">
                    <Label :for="`badge_${be.brand_event_id}`">Badge Name</Label>
                    <Input
                      :id="`badge_${be.brand_event_id}`"
                      :model-value="boothFields[be.brand_event_id]?.badge_name ?? be.badge_name ?? ''"
                      placeholder="Name displayed on exhibitor badge"
                      @update:model-value="(v) => setBoothField(be.brand_event_id, 'badge_name', v)"
                    />
                  </div>
                  <div class="sm:col-span-2">
                    <Button
                      size="sm"
                      :disabled="savingBoothFields === be.brand_event_id"
                      @click="saveBoothFields(be)"
                    >
                      <Icon v-if="savingBoothFields === be.brand_event_id" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                      Save Booth Details
                    </Button>
                  </div>
                </div>
              </div>
            </DashboardExhibitorSection>
          </div>

          <!-- Section 6: Order Form -->
          <div :ref="(el) => setSectionRef(be.brand_event_id, 'order', el)">
            <DashboardExhibitorSection
              v-model:open="sectionStates[`${be.brand_event_id}_order`]"
              title="Order Form"
              icon="hugeicons:shopping-cart-01"
              :summary="be.orders_count > 0 ? `${be.orders_count} order(s) submitted` : 'Submit your order for this event.'"
              :completed="be.orders_count > 0"
              :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
              :deadline="formatDeadlineShort(be.order_form_deadline)"
              :deadline-urgent="isDeadlineUrgent(be.order_form_deadline)"
              section-key="order"
            >
              <div class="flex flex-wrap items-center gap-2">
                <NuxtLink
                  :to="`/brands/${be.brand.slug}/order-form/${be.brand_event_id}`"
                  class="inline-flex items-center gap-x-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium tracking-tight text-primary-foreground transition hover:bg-primary/90 sm:text-sm"
                >
                  <Icon name="hugeicons:shopping-cart-01" class="size-3.5" />
                  {{ be.orders_count > 0 ? "New Order" : "Open Order Form" }}
                </NuxtLink>
                <NuxtLink
                  v-if="be.orders_count > 0"
                  :to="`/brands/${be.brand.slug}/orders/${be.brand_event_id}`"
                  class="inline-flex items-center gap-x-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
                >
                  <Icon name="hugeicons:shopping-bag-01" class="size-3.5" />
                  View Orders
                </NuxtLink>
              </div>
            </DashboardExhibitorSection>
          </div>
        </div>
      </div>

        <!-- Multiple events: collapsible wrapper -->
        <Collapsible v-else v-model:open="eventCollapseStates[be.brand_event_id]">
          <CollapsibleTrigger as-child>
            <button class="flex w-full items-center gap-3 py-2 text-left">
              <img
                v-if="be.event.poster_image?.sm"
                :src="be.event.poster_image.sm"
                :alt="be.event.title"
                class="size-9 shrink-0 rounded-lg object-cover"
              />
              <div
                v-else
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
              >
                <Icon name="hugeicons:calendar-03" class="size-4" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="truncate text-sm font-medium tracking-tight">{{ be.event.title }}</h3>
                  <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ be.brand.name }}</span>
                </div>
                <div class="text-muted-foreground flex items-center gap-1.5 text-xs tracking-tight sm:text-sm">
                  <span v-if="be.event.date_label">{{ be.event.date_label }}</span>
                  <span v-if="be.booth_number">- Booth {{ be.booth_number }}</span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ getSteps(be).filter((s) => s.completed).length }}/{{ getSteps(be).length }}
                </span>
                <Icon
                  name="hugeicons:arrow-down-01"
                  :class="[
                    'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
                    eventCollapseStates[be.brand_event_id] && 'rotate-180',
                  ]"
                />
              </div>
            </button>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div class="mt-3 space-y-3">
              <DashboardExhibitorStepper
                :steps="getSteps(be)"
                @jump="(key) => handleJump(be, key)"
              />
              <div :ref="(el) => setSectionRef(be.brand_event_id, 'profile', el)" class="space-y-2.5">
                <!-- Profile section (only if incomplete) -->
                <DashboardExhibitorSection
                  v-if="!dashboard.profile_complete"
                  v-model:open="sectionStates[`${be.brand_event_id}_profile`]"
                  title="Complete Your Profile"
                  icon="hugeicons:user-edit-01"
                  :summary="'Fill in all required fields to continue.'"
                  badge-text="Required"
                  badge-variant="destructive"
                  :default-open="beIndex === 0"
                  section-key="profile"
                >
                  <form class="space-y-4" @submit.prevent="saveProfile">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                      <div class="space-y-2">
                        <Label :for="`ex_name_${be.brand_event_id}`">Full Name</Label>
                        <Input :id="`ex_name_${be.brand_event_id}`" v-model="profileForm.name" placeholder="Your full name" />
                      </div>
                      <div class="space-y-2">
                        <Label :for="`ex_phone_${be.brand_event_id}`">Phone Number</Label>
                        <InputPhone :id="`ex_phone_${be.brand_event_id}`" v-model="profileForm.phone" />
                      </div>
                      <div class="space-y-2">
                        <Label :for="`ex_title_${be.brand_event_id}`">Job Title</Label>
                        <Input :id="`ex_title_${be.brand_event_id}`" v-model="profileForm.title" placeholder="e.g. Marketing Manager" />
                      </div>
                      <div class="space-y-2">
                        <Label :for="`ex_company_${be.brand_event_id}`">Company Name</Label>
                        <Input :id="`ex_company_${be.brand_event_id}`" v-model="profileForm.company_name" placeholder="Your company" />
                      </div>
                    </div>
                    <Button type="submit" size="sm" :disabled="profileSaving">
                      <Icon v-if="profileSaving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                      Save Profile
                    </Button>
                  </form>
                </DashboardExhibitorSection>

                <!-- Event Rules -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'rules', el)">
                  <DashboardExhibitorSection
                    v-if="be.event_rules?.length"
                    v-model:open="sectionStates[`${be.brand_event_id}_rules`]"
                    title="Event Rules"
                    icon="hugeicons:file-validation"
                    :summary="be.event_rules_agreed ? 'All rules agreed.' : `${rulesAgreedCount(be)}/${be.event_rules.length} rules agreed.`"
                    :completed="be.event_rules_agreed"
                    :locked="!dashboard.profile_complete"
                    :badge-text="!be.event_rules_agreed ? 'Required' : ''"
                    badge-variant="destructive"
                    :attention-count="rulesNeedingAttention(be)"
                    section-key="rules"
                  >
                    <div class="space-y-4">
                      <template v-for="rule in be.event_rules" :key="rule.document.id">
                        <DashboardExhibitorDocItem
                          :doc="rule.document"
                          mode="view"
                        />
                        <div class="flex items-start gap-x-2">
                          <Checkbox
                            :id="`rule_${be.brand_event_id}_${rule.document.id}`"
                            :checked="rule.agreed && !rule.needs_reagreement"
                            :disabled="agreeingId === rule.document.id"
                            @click="handleAgreeRule(be, rule)"
                          />
                          <div>
                            <Label
                              :for="`rule_${be.brand_event_id}_${rule.document.id}`"
                              class="text-sm font-normal leading-snug"
                            >
                              I have read and agree to "{{ rule.document.title }}"
                            </Label>
                            <p v-if="rule.agreed && rule.submission" class="mt-1 text-xs tracking-tight text-muted-foreground sm:text-sm">
                              Agreed on {{ formatDate(rule.submission.agreed_at) }}
                              <span v-if="rule.submission.submitter_name"> by {{ rule.submission.submitter_name }}</span>
                              (v{{ rule.submission.document_version }})
                            </p>
                            <p v-if="rule.needs_reagreement" class="mt-1 text-xs tracking-tight text-amber-600 dark:text-amber-400 sm:text-sm">
                              Rules updated. Please re-agree to the latest version.
                            </p>
                          </div>
                        </div>
                      </template>
                    </div>
                  </DashboardExhibitorSection>
                </div>

                <!-- Brand Profile -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'brand', el)">
                  <DashboardExhibitorSection
                    v-model:open="sectionStates[`${be.brand_event_id}_brand`]"
                    title="Brand Profile"
                    icon="hugeicons:store-02"
                    :summary="be.brand_complete ? `${be.brand.name} profile is complete.` : `Missing: ${be.brand.missing_fields.join(', ')}`"
                    :completed="be.brand_complete"
                    :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
                    section-key="brand"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3">
                        <img v-if="be.brand.brand_logo?.sm" :src="be.brand.brand_logo.sm" :alt="be.brand.name" class="size-10 rounded-lg object-cover" />
                        <div v-else class="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                          <Icon name="hugeicons:store-02" class="size-5" />
                        </div>
                        <div>
                          <p class="text-sm font-medium">{{ be.brand.name }}</p>
                          <p v-if="!be.brand_complete" class="text-xs tracking-tight text-muted-foreground sm:text-sm">
                            {{ be.brand.missing_fields.length }} field{{ be.brand.missing_fields.length > 1 ? 's' : '' }} remaining
                          </p>
                        </div>
                      </div>
                      <NuxtLink
                        :to="`/brands/${be.brand.slug}/edit`"
                        class="inline-flex items-center gap-x-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
                      >
                        <Icon :name="be.brand_complete ? 'hugeicons:view' : 'hugeicons:edit-02'" class="size-3.5" />
                        {{ be.brand_complete ? "View" : "Complete" }}
                      </NuxtLink>
                    </div>
                  </DashboardExhibitorSection>
                </div>

                <!-- Promotion Posts -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'promo', el)">
                  <DashboardExhibitorSection
                    v-model:open="sectionStates[`${be.brand_event_id}_promo`]"
                    title="Promotion Posts"
                    icon="hugeicons:image-02"
                    :summary="`${be.promotion_posts_count}/${be.promotion_post_limit} uploaded`"
                    :completed="be.promotion_posts_count > 0"
                    :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
                    :deadline="formatDeadlineShort(be.promotion_post_deadline)"
                    :deadline-urgent="isDeadlineUrgent(be.promotion_post_deadline)"
                    section-key="promo"
                  >
                    <div class="flex items-center justify-between">
                      <p class="text-sm tracking-tight text-muted-foreground">
                        {{ be.promotion_posts_count > 0
                          ? `You've uploaded ${be.promotion_posts_count} of ${be.promotion_post_limit} allowed posts.`
                          : `Upload up to ${be.promotion_post_limit} promotion post${be.promotion_post_limit > 1 ? 's' : ''} for this event.`
                        }}
                      </p>
                      <NuxtLink
                        :to="`/brands/${be.brand.slug}/promotion-posts/${be.brand_event_id}`"
                        class="inline-flex shrink-0 items-center gap-x-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
                      >
                        <Icon :name="be.promotion_posts_count > 0 ? 'hugeicons:view' : 'hugeicons:upload-04'" class="size-3.5" />
                        {{ be.promotion_posts_count > 0 ? "Manage" : "Upload" }}
                      </NuxtLink>
                    </div>
                  </DashboardExhibitorSection>
                </div>

                <!-- Operational Documents -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'docs', el)">
                  <DashboardExhibitorSection
                    v-if="be.documents?.length || showFasciaField(be) || showBadgeField(be)"
                    v-model:open="sectionStates[`${be.brand_event_id}_docs`]"
                    title="Operational Documents"
                    icon="hugeicons:file-01"
                    :summary="docsAndBoothSummary(be)"
                    :completed="isDocsComplete(be)"
                    :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
                    :attention-count="docsNeedingAttention(be)"
                    section-key="docs"
                  >
                    <div v-if="be.documents?.length" class="space-y-3">
                      <DashboardExhibitorDocItem
                        v-for="doc in be.documents"
                        :key="doc.document.id"
                        :doc="doc.document"
                        :status="doc.status"
                        mode="action"
                      />
                      <NuxtLink
                        :to="`/brands/${be.brand.slug}/documents/${be.brand_event_id}`"
                        class="mt-1 inline-flex items-center gap-x-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
                      >
                        <Icon name="hugeicons:file-01" class="size-3.5" />
                        {{ be.documents_completed === be.documents_total ? "View All" : "Complete Documents" }}
                      </NuxtLink>
                    </div>
                    <div
                      v-if="showFasciaField(be) || showBadgeField(be)"
                      :class="{ 'mt-5 border-t border-border pt-5': be.documents?.length }"
                    >
                      <p class="mb-3 text-sm font-medium">Booth Details</p>
                      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div v-if="showFasciaField(be)" class="space-y-2">
                          <Label :for="`fascia_${be.brand_event_id}`">Fascia Name</Label>
                          <Input
                            :id="`fascia_${be.brand_event_id}`"
                            :model-value="boothFields[be.brand_event_id]?.fascia_name ?? be.fascia_name ?? ''"
                            placeholder="Name displayed on booth fascia"
                            @update:model-value="(v) => setBoothField(be.brand_event_id, 'fascia_name', v)"
                          />
                        </div>
                        <div v-if="showBadgeField(be)" class="space-y-2">
                          <Label :for="`badge_${be.brand_event_id}`">Badge Name</Label>
                          <Input
                            :id="`badge_${be.brand_event_id}`"
                            :model-value="boothFields[be.brand_event_id]?.badge_name ?? be.badge_name ?? ''"
                            placeholder="Name displayed on exhibitor badge"
                            @update:model-value="(v) => setBoothField(be.brand_event_id, 'badge_name', v)"
                          />
                        </div>
                        <div class="sm:col-span-2">
                          <Button
                            size="sm"
                            :disabled="savingBoothFields === be.brand_event_id"
                            @click="saveBoothFields(be)"
                          >
                            <Icon v-if="savingBoothFields === be.brand_event_id" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                            Save Booth Details
                          </Button>
                        </div>
                      </div>
                    </div>
                  </DashboardExhibitorSection>
                </div>

                <!-- Order Form -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'order', el)">
                  <DashboardExhibitorSection
                    v-model:open="sectionStates[`${be.brand_event_id}_order`]"
                    title="Order Form"
                    icon="hugeicons:shopping-cart-01"
                    :summary="be.orders_count > 0 ? `${be.orders_count} order(s) submitted` : 'Submit your order for this event.'"
                    :completed="be.orders_count > 0"
                    :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
                    :deadline="formatDeadlineShort(be.order_form_deadline)"
                    :deadline-urgent="isDeadlineUrgent(be.order_form_deadline)"
                    section-key="order"
                  >
                    <div class="flex flex-wrap items-center gap-2">
                      <NuxtLink
                        :to="`/brands/${be.brand.slug}/order-form/${be.brand_event_id}`"
                        class="inline-flex items-center gap-x-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium tracking-tight text-primary-foreground transition hover:bg-primary/90 sm:text-sm"
                      >
                        <Icon name="hugeicons:shopping-cart-01" class="size-3.5" />
                        {{ be.orders_count > 0 ? "New Order" : "Open Order Form" }}
                      </NuxtLink>
                      <NuxtLink
                        v-if="be.orders_count > 0"
                        :to="`/brands/${be.brand.slug}/orders/${be.brand_event_id}`"
                        class="inline-flex items-center gap-x-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
                      >
                        <Icon name="hugeicons:shopping-bag-01" class="size-3.5" />
                        View Orders
                      </NuxtLink>
                    </div>
                  </DashboardExhibitorSection>
                </div>
              </div>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </template>
    </template>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { toast } from "vue-sonner";

const client = useSanctumClient();

const data = ref(null);
const pending = ref(true);
const dashboard = computed(() => data.value?.data);
const agreeingId = ref(null);
const boothFields = ref({});
const savingBoothFields = ref(null);
const sectionStates = reactive({});
const eventCollapseStates = reactive({});
const sectionRefs = {};

const hasMultipleEvents = computed(() => (dashboard.value?.brand_events?.length || 0) > 1);

// Profile form
const profileForm = reactive({
  name: "",
  phone: "",
  title: "",
  company_name: "",
});
const profileSaving = ref(false);

function initProfileForm() {
  const user = dashboard.value?.user;
  if (user) {
    profileForm.name = user.name || "";
    profileForm.phone = user.phone || "";
    profileForm.title = user.title || "";
    profileForm.company_name = user.company_name || "";
  }
}

async function fetchData() {
  try {
    data.value = await client("/api/exhibitor/dashboard");
    initProfileForm();
    // Initialize event collapse states: first open, rest closed
    const bes = data.value?.data?.brand_events || [];
    bes.forEach((be, i) => {
      if (!(be.brand_event_id in eventCollapseStates)) {
        eventCollapseStates[be.brand_event_id] = i === 0;
      }
    });
  } catch (e) {
    console.error("Failed to fetch exhibitor dashboard:", e);
  }
  pending.value = false;
}

async function saveProfile() {
  profileSaving.value = true;
  try {
    await client("/api/user/profile", { method: "PUT", body: profileForm });
    toast.success("Profile updated");
    await fetchData();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to update profile");
  } finally {
    profileSaving.value = false;
  }
}

// --- Formatting helpers ---
function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function formatDeadlineShort(dateStr) {
  if (!dateStr) return "";
  const deadline = new Date(dateStr);
  if (deadline < new Date()) return "";
  const daysLeft = Math.ceil((deadline - new Date()) / (1000 * 60 * 60 * 24));
  if (daysLeft <= 0) return "";
  if (daysLeft <= 7) return `${daysLeft}d left`;
  return formatDate(dateStr);
}

function isDeadlineUrgent(dateStr) {
  if (!dateStr) return false;
  const deadline = new Date(dateStr);
  const daysLeft = Math.ceil((deadline - new Date()) / (1000 * 60 * 60 * 24));
  return daysLeft > 0 && daysLeft <= 7;
}

function getMediaUrl(media) {
  if (!media) return "";
  if (typeof media === "string") return media;
  return media.url || media.original || "";
}

// --- Step computation for stepper ---
function getSteps(be) {
  const profileLocked = !dashboard.value?.profile_complete;
  const rulesLocked = profileLocked || (be.event_rules?.length > 0 && !be.event_rules_agreed);
  const steps = [];

  steps.push({
    key: "profile",
    label: "Profile",
    completed: dashboard.value?.profile_complete,
    current: !dashboard.value?.profile_complete,
    locked: false,
  });

  if (be.event_rules?.length) {
    steps.push({
      key: "rules",
      label: "Rules",
      completed: be.event_rules_agreed,
      current: dashboard.value?.profile_complete && !be.event_rules_agreed,
      locked: profileLocked,
    });
  }

  steps.push({
    key: "brand",
    label: "Brand",
    completed: be.brand_complete,
    current: !rulesLocked && !be.brand_complete,
    locked: rulesLocked,
  });

  steps.push({
    key: "promo",
    label: "Promo",
    completed: be.promotion_posts_count > 0,
    current: !rulesLocked && be.brand_complete && be.promotion_posts_count === 0,
    locked: rulesLocked,
  });

  if (be.documents?.length || showFasciaField(be) || showBadgeField(be)) {
    steps.push({
      key: "docs",
      label: "Docs",
      completed: isDocsComplete(be),
      current: !rulesLocked && !isDocsComplete(be),
      locked: rulesLocked,
    });
  }

  steps.push({
    key: "order",
    label: "Order",
    completed: be.orders_count > 0,
    current: !rulesLocked && be.orders_count === 0,
    locked: rulesLocked,
  });

  return steps;
}

// --- Section refs for scroll-to ---
function setSectionRef(beId, key, el) {
  if (!sectionRefs[beId]) sectionRefs[beId] = {};
  sectionRefs[beId][key] = el;
}

function handleJump(be, key) {
  const stateKey = `${be.brand_event_id}_${key}`;
  sectionStates[stateKey] = true;
  nextTick(() => {
    const el = sectionRefs[be.brand_event_id]?.[key];
    if (el) {
      el.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  });
}

function handleHeroAction(actionKey) {
  if (actionKey === "profile") {
    const firstBe = dashboard.value?.brand_events?.[0];
    if (firstBe) {
      sectionStates[`${firstBe.brand_event_id}_profile`] = true;
    }
    return;
  }

  const [type, beId] = actionKey.split(":");
  if (!beId) return;

  const keyMap = {
    rules: "rules",
    docs: "docs",
    order: "order",
  };

  const sectionKey = keyMap[type];
  if (sectionKey) {
    // Open the event collapsible if multiple events
    eventCollapseStates[beId] = true;
    sectionStates[`${beId}_${sectionKey}`] = true;
    nextTick(() => {
      const el = sectionRefs[beId]?.[sectionKey];
      if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    });
  }
}

// --- Event Rules ---
function rulesAgreedCount(be) {
  return be.event_rules?.filter((r) => r.agreed && !r.needs_reagreement).length || 0;
}

function rulesNeedingAttention(be) {
  return be.event_rules?.filter((r) => r.needs_reagreement || !r.agreed).length || 0;
}

async function handleAgreeRule(be, rule) {
  if (rule.agreed && !rule.needs_reagreement) return;
  agreeingId.value = rule.document.id;
  try {
    await client(
      `/api/exhibitor/brands/${be.brand.slug}/events/${be.brand_event_id}/documents/${rule.document.ulid}`,
      { method: "POST", body: {} },
    );
    toast.success("Agreement recorded");
    await fetchData();
  } catch (err) {
    toast.error(err?.data?.message || "Failed to submit");
  } finally {
    agreeingId.value = null;
  }
}

// --- Document helpers ---
function docStatusIcon(status) {
  if (status === "completed") return "hugeicons:checkmark-circle-02";
  if (status === "needs_reagreement") return "hugeicons:alert-02";
  return "hugeicons:circle";
}

function docStatusColor(status) {
  if (status === "completed") return "text-green-500";
  if (status === "needs_reagreement") return "text-amber-500";
  return "text-muted-foreground";
}

function isDocsComplete(be) {
  const docsComplete = !be.documents?.length || be.documents_completed === be.documents_total;
  const fasciaOk = !showFasciaField(be) || !!be.fascia_name;
  const badgeOk = !showBadgeField(be) || !!be.badge_name;
  return docsComplete && fasciaOk && badgeOk;
}

function docsAndBoothSummary(be) {
  const parts = [];
  if (be.documents?.length) {
    parts.push(`${be.documents_completed}/${be.documents_total} documents`);
  }
  if (showFasciaField(be) || showBadgeField(be)) {
    const boothDone = (showFasciaField(be) ? (be.fascia_name ? 1 : 0) : 0) + (showBadgeField(be) ? (be.badge_name ? 1 : 0) : 0);
    const boothTotal = (showFasciaField(be) ? 1 : 0) + (showBadgeField(be) ? 1 : 0);
    parts.push(`${boothDone}/${boothTotal} booth fields`);
  }
  return parts.join(", ") || "No documents required.";
}

function docsNeedingAttention(be) {
  return be.documents?.filter((d) => d.status === "needs_reagreement").length || 0;
}

// --- Booth fields ---
function showFasciaField(be) {
  return be.booth_type === "standard_shell_scheme" || be.booth_type === "enhanced_shell_scheme";
}

function showBadgeField(be) {
  return !!be.booth_type;
}

function setBoothField(beId, field, value) {
  if (!boothFields.value[beId]) {
    boothFields.value[beId] = {};
  }
  boothFields.value[beId][field] = value;
}

async function saveBoothFields(be) {
  const fields = boothFields.value[be.brand_event_id] || {};
  savingBoothFields.value = be.brand_event_id;
  try {
    await client(
      `/api/exhibitor/brands/${be.brand.slug}/events/${be.brand_event_id}/booth-fields`,
      {
        method: "PUT",
        body: {
          fascia_name: fields.fascia_name ?? be.fascia_name,
          badge_name: fields.badge_name ?? be.badge_name,
        },
      },
    );
    toast.success("Booth details saved");
    await fetchData();
  } catch (err) {
    toast.error(err?.data?.message || "Failed to save booth details");
  } finally {
    savingBoothFields.value = null;
  }
}

onMounted(fetchData);
</script>
