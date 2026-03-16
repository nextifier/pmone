<template>
  <div class="mx-auto flex flex-col gap-y-5 pt-2 pb-16 lg:max-w-4xl lg:pt-4 xl:max-w-5xl">
    <!-- Greeting -->
    <!-- <DashboardGreetingTips /> -->

    <!-- Loading -->
    <div v-if="pending" class="space-y-4">
      <div class="bg-muted h-24 animate-pulse rounded-xl" />
      <div class="bg-muted h-10 animate-pulse rounded-xl" />
      <div class="bg-muted h-16 animate-pulse rounded-xl" />
      <div class="bg-muted h-16 animate-pulse rounded-xl" />
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
        class="border-border flex flex-col items-center gap-3 rounded-xl border px-4 py-12"
      >
        <div class="bg-muted flex size-12 items-center justify-center rounded-full">
          <Icon name="hugeicons:calendar-03" class="text-muted-foreground size-6" />
        </div>
        <p class="text-muted-foreground text-sm tracking-tight">{{ $t("ed.noEvents") }}</p>
      </div>

      <!-- Per Brand-Event -->
      <template v-for="(be, beIndex) in dashboard.brand_events" :key="be.brand_event_id">
        <!-- Single event: no collapsible wrapper -->
        <div v-if="!hasMultipleEvents" class="space-y-6">
          <DashboardExhibitorEventCard :be="be" />
          <DashboardExhibitorStepper :steps="getSteps(be)" @jump="(key) => handleJump(be, key)" />
          <div :ref="(el) => setSectionRef(be.brand_event_id, 'profile', el)" class="space-y-2.5">
            <!-- Section 1: Profile (only if incomplete) -->
            <DashboardExhibitorSection
              v-if="!dashboard.profile_complete"
              v-model:open="sectionStates[`${be.brand_event_id}_profile`]"
              :title="$t('ed.profile.title')"
              icon="hugeicons:user-edit-01"
              :summary="$t('ed.profile.summary')"
              :badge-text="$t('ed.profile.required')"
              badge-variant="destructive"
              :default-open="true"
              section-key="profile"
            >
              <form class="space-y-4" @submit.prevent="saveProfile">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="ex_name">{{ $t("ed.profile.fullName") }}</Label>
                    <Input id="ex_name" v-model="profileForm.name" :placeholder="$t('ed.profile.placeholderName')" />
                  </div>
                  <div class="space-y-2">
                    <Label for="ex_phone">{{ $t("ed.profile.phone") }}</Label>
                    <InputPhone id="ex_phone" v-model="profileForm.phone" />
                  </div>
                  <div class="space-y-2">
                    <Label for="ex_title">{{ $t("ed.profile.jobTitle") }}</Label>
                    <Input
                      id="ex_title"
                      v-model="profileForm.title"
                      :placeholder="$t('ed.profile.placeholderTitle')"
                    />
                  </div>
                  <div class="space-y-2">
                    <Label for="ex_company">{{ $t("ed.profile.company") }}</Label>
                    <Input
                      id="ex_company"
                      v-model="profileForm.company_name"
                      :placeholder="$t('ed.profile.placeholderCompany')"
                    />
                  </div>
                </div>
                <Button type="submit" size="sm" :disabled="profileSaving">
                  <Icon
                    v-if="profileSaving"
                    name="svg-spinners:ring-resize"
                    class="mr-1.5 size-4"
                  />
                  {{ $t("ed.profile.save") }}
                </Button>
              </form>
            </DashboardExhibitorSection>

            <!-- Section 2: Event Rules -->
            <div :ref="(el) => setSectionRef(be.brand_event_id, 'rules', el)">
              <DashboardExhibitorSection
                v-if="be.event_rules?.length"
                v-model:open="sectionStates[`${be.brand_event_id}_rules`]"
                :title="$t('ed.rules.title')"
                icon="hugeicons:file-validation"
                :summary="
                  be.event_rules_agreed
                    ? $t('ed.rules.allAgreed')
                    : $t('ed.rules.agreedCount', { agreed: rulesAgreedCount(be), total: be.event_rules.length })
                "
                :completed="be.event_rules_agreed"
                :locked="!dashboard.profile_complete"
                :badge-text="!be.event_rules_agreed ? $t('ed.profile.required') : ''"
                badge-variant="destructive"
                :attention-count="rulesNeedingAttention(be)"
                section-key="rules"
              >
                <div class="space-y-4">
                  <template v-for="rule in be.event_rules" :key="rule.document.id">
                    <DashboardExhibitorDocItem :doc="rule.document" mode="view" />
                    <!-- Already agreed: show agreement info only -->
                    <div
                      v-if="rule.agreed && !rule.needs_reagreement && rule.submission"
                      class="flex items-center gap-x-1.5"
                    >
                      <div
                        class="bg-primary text-primary-foreground flex size-5 shrink-0 items-center justify-center rounded-full"
                      >
                        <Icon name="lucide:check" class="size-3.5" />
                      </div>
                      <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                        {{ $t("ed.rules.agreedOn", { date: formatDate(rule.submission.agreed_at) }) }}
                        <span v-if="rule.submission.submitter_name">
                          {{ $t("ed.rules.agreedBy", { name: rule.submission.submitter_name }) }}</span
                        >
                        <span v-if="rule.submission.document_version > 1">
                          (v{{ rule.submission.document_version }})</span
                        >
                      </p>
                    </div>
                    <!-- Not yet agreed or needs re-agreement: show checkbox + submit -->
                    <div v-else>
                      <div class="flex items-start gap-x-2">
                        <Checkbox
                          :id="`rule_${be.brand_event_id}_${rule.document.id}`"
                          :model-value="!!checkedRules[`${be.brand_event_id}_${rule.document.id}`]"
                          @update:model-value="
                            (v) => (checkedRules[`${be.brand_event_id}_${rule.document.id}`] = v)
                          "
                        />
                        <div>
                          <Label
                            :for="`rule_${be.brand_event_id}_${rule.document.id}`"
                            class="text-sm leading-snug font-normal"
                          >
                            {{ $t("ed.rules.agreeLabel", { title: rule.document.title }) }}
                          </Label>
                          <p
                            v-if="rule.needs_reagreement"
                            class="mt-1 text-xs tracking-tight text-amber-600 sm:text-sm dark:text-amber-400"
                          >
                            {{ $t("ed.rules.reagreeWarning") }}
                          </p>
                        </div>
                      </div>
                      <Button
                        v-if="checkedRules[`${be.brand_event_id}_${rule.document.id}`]"
                        size="sm"
                        class="mt-2 ml-6"
                        :disabled="agreeingId === rule.document.id"
                        @click="handleAgreeRule(be, rule)"
                      >
                        <Icon
                          v-if="agreeingId === rule.document.id"
                          name="svg-spinners:ring-resize"
                          class="mr-1.5 size-4"
                        />
                        {{ $t("ed.rules.submit") }}
                      </Button>
                    </div>
                  </template>
                </div>
              </DashboardExhibitorSection>
            </div>

            <!-- Section 3: Brand Profile -->
            <div :ref="(el) => setSectionRef(be.brand_event_id, 'brand', el)">
              <DashboardExhibitorSection
                v-model:open="sectionStates[`${be.brand_event_id}_brand`]"
                :title="$t('ed.brand.title')"
                icon="hugeicons:store-02"
                :summary="
                  be.brand_complete
                    ? $t('ed.brand.complete', { name: be.brand.name })
                    : $t('ed.brand.missing', { fields: be.brand.missing_fields.join(', ') })
                "
                :completed="be.brand_complete"
                :locked="
                  !dashboard.profile_complete ||
                  (be.event_rules?.length > 0 && !be.event_rules_agreed)
                "
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
                      class="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-lg"
                    >
                      <Icon name="hugeicons:store-02" class="size-5" />
                    </div>
                    <div>
                      <p class="text-sm font-medium">{{ be.brand.name }}</p>
                      <p
                        v-if="!be.brand_complete"
                        class="text-muted-foreground text-xs tracking-tight sm:text-sm"
                      >
                        {{ $t("ed.brand.fieldsRemaining", be.brand.missing_fields.length, { count: be.brand.missing_fields.length }) }}
                      </p>
                    </div>
                  </div>
                  <NuxtLink
                    :to="`/brands/${be.brand.slug}/edit`"
                    class="border-border hover:bg-muted inline-flex items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
                  >
                    <Icon
                      :name="be.brand_complete ? 'hugeicons:view' : 'hugeicons:edit-02'"
                      class="size-3.5"
                    />
                    {{ be.brand_complete ? $t("ed.brand.view") : $t("ed.brand.completeCta") }}
                  </NuxtLink>
                </div>
              </DashboardExhibitorSection>
            </div>

            <!-- Section 4: Promotion Posts -->
            <div :ref="(el) => setSectionRef(be.brand_event_id, 'promo', el)">
              <DashboardExhibitorSection
                v-model:open="sectionStates[`${be.brand_event_id}_promo`]"
                :title="$t('ed.promo.title')"
                icon="hugeicons:image-02"
                :summary="$t('ed.promo.uploaded', { count: be.promotion_posts_count, limit: be.promotion_post_limit })"
                :completed="be.promotion_posts_count > 0"
                :locked="
                  !dashboard.profile_complete ||
                  (be.event_rules?.length > 0 && !be.event_rules_agreed)
                "
                :deadline="formatDeadlineShort(be.promotion_post_deadline)"
                :deadline-urgent="isDeadlineUrgent(be.promotion_post_deadline)"
                section-key="promo"
              >
                <div class="flex items-center justify-between">
                  <p class="text-muted-foreground text-sm tracking-tight">
                    {{
                      be.promotion_posts_count > 0
                        ? $t("ed.promo.uploadedDescription", { count: be.promotion_posts_count, limit: be.promotion_post_limit })
                        : $t("ed.promo.uploadDescription", be.promotion_post_limit, { limit: be.promotion_post_limit })
                    }}
                  </p>
                  <NuxtLink
                    :to="`/brands/${be.brand.slug}/promotion-posts/${be.brand_event_id}`"
                    class="border-border hover:bg-muted inline-flex shrink-0 items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
                  >
                    <Icon
                      :name="
                        be.promotion_posts_count > 0 ? 'hugeicons:view' : 'hugeicons:upload-04'
                      "
                      class="size-3.5"
                    />
                    {{ be.promotion_posts_count > 0 ? $t("ed.promo.manage") : $t("ed.promo.upload") }}
                  </NuxtLink>
                </div>
              </DashboardExhibitorSection>
            </div>

            <!-- Section 5: Operational Documents -->
            <div :ref="(el) => setSectionRef(be.brand_event_id, 'docs', el)">
              <DashboardExhibitorSection
                v-if="be.documents?.length || showFasciaField(be) || showBadgeField(be)"
                v-model:open="sectionStates[`${be.brand_event_id}_docs`]"
                :title="$t('ed.docs.title')"
                icon="hugeicons:file-01"
                :summary="docsAndBoothSummary(be)"
                :completed="isDocsComplete(be)"
                :locked="
                  !dashboard.profile_complete ||
                  (be.event_rules?.length > 0 && !be.event_rules_agreed)
                "
                :attention-count="docsNeedingAttention(be)"
                section-key="docs"
              >
                <!-- Document list -->
                <div v-if="be.documents?.length" class="divide-border -mx-4 divide-y sm:-mx-5">
                  <DashboardExhibitorDocItem
                    v-for="doc in be.documents"
                    :key="doc.document.id"
                    class="px-4 py-6 first:pt-0 last:pb-0 sm:px-5"
                    :doc="doc.document"
                    :submission="doc.submission"
                    :status="doc.status"
                    :api-base="`/api/exhibitor/brands/${be.brand.slug}/events/${be.brand_event_id}/documents`"
                    mode="action"
                    @submitted="refreshDashboard"
                  />
                </div>

                <!-- Booth Fields (fascia & badge) -->
                <div
                  v-if="showFasciaField(be) || showBadgeField(be)"
                  :class="{ 'border-border mt-5 border-t pt-5': be.documents?.length }"
                >
                  <p class="mb-3 text-sm font-medium">{{ $t("ed.docs.boothDetails") }}</p>
                  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-if="showFasciaField(be)" class="space-y-2">
                      <Label :for="`fascia_${be.brand_event_id}`">{{ $t("ed.docs.fasciaName") }}</Label>
                      <Input
                        :id="`fascia_${be.brand_event_id}`"
                        :model-value="
                          boothFields[be.brand_event_id]?.fascia_name ?? be.fascia_name ?? ''
                        "
                        :placeholder="$t('ed.docs.fasciaPlaceholder')"
                        @update:model-value="
                          (v) =>
                            setBoothField(be.brand_event_id, 'fascia_name', v.toUpperCase())
                        "
                        maxlength="24"
                      />
                      <p class="text-muted-foreground text-xs tracking-tight">
                        {{ $t("ed.docs.fasciaHint") }}
                      </p>
                    </div>
                    <div v-if="showBadgeField(be)" class="space-y-2">
                      <Label :for="`badge_${be.brand_event_id}`">{{ $t("ed.docs.badgeName") }}</Label>
                      <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                        {{ $t("ed.docs.badgeDescription") }}
                      </p>
                      <Input
                        :id="`badge_${be.brand_event_id}`"
                        :model-value="
                          boothFields[be.brand_event_id]?.badge_name ?? be.badge_name ?? ''
                        "
                        :placeholder="$t('ed.docs.badgePlaceholder')"
                        @update:model-value="
                          (v) => setBoothField(be.brand_event_id, 'badge_name', v)
                        "
                      />
                    </div>
                    <div class="sm:col-span-2">
                      <Button
                        size="sm"
                        :disabled="savingBoothFields === be.brand_event_id"
                        @click="saveBoothFields(be)"
                      >
                        <Icon
                          v-if="savingBoothFields === be.brand_event_id"
                          name="svg-spinners:ring-resize"
                          class="mr-1.5 size-4"
                        />
                        {{ $t("ed.docs.saveBoothDetails") }}
                      </Button>
                    </div>
                  </div>
                </div>
              </DashboardExhibitorSection>
            </div>

            <!-- Badge & VIP Information (read-only, shown only if content exists) -->
            <DashboardExhibitorSection
              v-if="be.event.badge_vip_info"
              v-model:open="sectionStates[`${be.brand_event_id}_badge_vip`]"
              :title="$t('ed.badgeVip.title')"
              icon="hugeicons:name-tag"
              :summary="$t('ed.badgeVip.summary')"
              :completed="true"
              :locked="
                !dashboard.profile_complete ||
                (be.event_rules?.length > 0 && !be.event_rules_agreed)
              "
              section-key="badge_vip"
            >
              <div class="format-html" v-html="be.event.badge_vip_info" />
            </DashboardExhibitorSection>

            <!-- Section 6: Order Form -->
            <div :ref="(el) => setSectionRef(be.brand_event_id, 'order', el)">
              <DashboardExhibitorSection
                v-model:open="sectionStates[`${be.brand_event_id}_order`]"
                :title="$t('ed.order.title')"
                icon="hugeicons:shopping-cart-01"
                :summary="orderSectionSummary(be)"
                :completed="be.orders_count > 0"
                :locked="
                  !dashboard.profile_complete ||
                  (be.event_rules?.length > 0 && !be.event_rules_agreed)
                "
                section-key="order"
              >
                <DashboardExhibitorOrderInfo :be="be" />
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
                class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
              >
                <Icon name="hugeicons:calendar-03" class="size-4" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="truncate text-sm font-medium tracking-tight">{{ be.event.title }}</h3>
                  <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{
                    be.brand.name
                  }}</span>
                </div>
                <div
                  class="text-muted-foreground flex items-center gap-1.5 text-xs tracking-tight sm:text-sm"
                >
                  <span v-if="be.event.date_label">{{ be.event.date_label }}</span>
                  <span v-if="be.booth_number">- {{ $t("ed.eventCard.booth") }} {{ be.booth_number }}</span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ getSteps(be).filter((s) => s.completed).length }}/{{ getSteps(be).length }}
                </span>
                <Icon
                  name="hugeicons:arrow-down-01"
                  :class="[
                    'text-muted-foreground size-4 shrink-0 transition-transform duration-200',
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
              <div
                :ref="(el) => setSectionRef(be.brand_event_id, 'profile', el)"
                class="space-y-2.5"
              >
                <!-- Profile section (only if incomplete) -->
                <DashboardExhibitorSection
                  v-if="!dashboard.profile_complete"
                  v-model:open="sectionStates[`${be.brand_event_id}_profile`]"
                  :title="$t('ed.profile.title')"
                  icon="hugeicons:user-edit-01"
                  :summary="$t('ed.profile.summary')"
                  :badge-text="$t('ed.profile.required')"
                  badge-variant="destructive"
                  :default-open="beIndex === 0"
                  section-key="profile"
                >
                  <form class="space-y-4" @submit.prevent="saveProfile">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                      <div class="space-y-2">
                        <Label :for="`ex_name_${be.brand_event_id}`">{{ $t("ed.profile.fullName") }}</Label>
                        <Input
                          :id="`ex_name_${be.brand_event_id}`"
                          v-model="profileForm.name"
                          :placeholder="$t('ed.profile.placeholderName')"
                        />
                      </div>
                      <div class="space-y-2">
                        <Label :for="`ex_phone_${be.brand_event_id}`">{{ $t("ed.profile.phone") }}</Label>
                        <InputPhone
                          :id="`ex_phone_${be.brand_event_id}`"
                          v-model="profileForm.phone"
                        />
                      </div>
                      <div class="space-y-2">
                        <Label :for="`ex_title_${be.brand_event_id}`">{{ $t("ed.profile.jobTitle") }}</Label>
                        <Input
                          :id="`ex_title_${be.brand_event_id}`"
                          v-model="profileForm.title"
                          :placeholder="$t('ed.profile.placeholderTitle')"
                        />
                      </div>
                      <div class="space-y-2">
                        <Label :for="`ex_company_${be.brand_event_id}`">{{ $t("ed.profile.company") }}</Label>
                        <Input
                          :id="`ex_company_${be.brand_event_id}`"
                          v-model="profileForm.company_name"
                          :placeholder="$t('ed.profile.placeholderCompany')"
                        />
                      </div>
                    </div>
                    <Button type="submit" size="sm" :disabled="profileSaving">
                      <Icon
                        v-if="profileSaving"
                        name="svg-spinners:ring-resize"
                        class="mr-1.5 size-4"
                      />
                      {{ $t("ed.profile.save") }}
                    </Button>
                  </form>
                </DashboardExhibitorSection>

                <!-- Event Rules -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'rules', el)">
                  <DashboardExhibitorSection
                    v-if="be.event_rules?.length"
                    v-model:open="sectionStates[`${be.brand_event_id}_rules`]"
                    :title="$t('ed.rules.title')"
                    icon="hugeicons:file-validation"
                    :summary="
                      be.event_rules_agreed
                        ? $t('ed.rules.allAgreed')
                        : $t('ed.rules.agreedCount', { agreed: rulesAgreedCount(be), total: be.event_rules.length })
                    "
                    :completed="be.event_rules_agreed"
                    :locked="!dashboard.profile_complete"
                    :badge-text="!be.event_rules_agreed ? $t('ed.profile.required') : ''"
                    badge-variant="destructive"
                    :attention-count="rulesNeedingAttention(be)"
                    section-key="rules"
                  >
                    <div class="space-y-4">
                      <template v-for="rule in be.event_rules" :key="rule.document.id">
                        <DashboardExhibitorDocItem :doc="rule.document" mode="view" />
                        <!-- Already agreed: show agreement info only -->
                        <div
                          v-if="rule.agreed && !rule.needs_reagreement && rule.submission"
                          class="flex items-center gap-x-2.5"
                        >
                          <div
                            class="bg-success flex size-8 shrink-0 items-center justify-center rounded-full text-white"
                          >
                            <Icon name="lucide:check" class="size-4" />
                          </div>
                          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                            {{ $t("ed.rules.agreedOn", { date: formatDate(rule.submission.agreed_at) }) }}
                            <span v-if="rule.submission.submitter_name">
                              {{ $t("ed.rules.agreedBy", { name: rule.submission.submitter_name }) }}</span
                            >
                            <span v-if="rule.submission.document_version > 1">
                              (v{{ rule.submission.document_version }})</span
                            >
                          </p>
                        </div>
                        <!-- Not yet agreed or needs re-agreement: show checkbox + submit -->
                        <div v-else>
                          <div class="flex items-start gap-x-2">
                            <Checkbox
                              :id="`rule_m_${be.brand_event_id}_${rule.document.id}`"
                              :model-value="
                                !!checkedRules[`${be.brand_event_id}_${rule.document.id}`]
                              "
                              @update:model-value="
                                (v) =>
                                  (checkedRules[`${be.brand_event_id}_${rule.document.id}`] = v)
                              "
                            />
                            <div>
                              <Label
                                :for="`rule_m_${be.brand_event_id}_${rule.document.id}`"
                                class="text-sm leading-snug font-normal"
                              >
                                {{ $t("ed.rules.agreeLabel", { title: rule.document.title }) }}
                              </Label>
                              <p
                                v-if="rule.needs_reagreement"
                                class="mt-1 text-xs tracking-tight text-amber-600 sm:text-sm dark:text-amber-400"
                              >
                                {{ $t("ed.rules.reagreeWarning") }}
                              </p>
                            </div>
                          </div>
                          <Button
                            v-if="checkedRules[`${be.brand_event_id}_${rule.document.id}`]"
                            size="sm"
                            class="mt-2 ml-6"
                            :disabled="agreeingId === rule.document.id"
                            @click="handleAgreeRule(be, rule)"
                          >
                            <Icon
                              v-if="agreeingId === rule.document.id"
                              name="svg-spinners:ring-resize"
                              class="mr-1.5 size-4"
                            />
                            {{ $t("ed.rules.submit") }}
                          </Button>
                        </div>
                      </template>
                    </div>
                  </DashboardExhibitorSection>
                </div>

                <!-- Brand Profile -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'brand', el)">
                  <DashboardExhibitorSection
                    v-model:open="sectionStates[`${be.brand_event_id}_brand`]"
                    :title="$t('ed.brand.title')"
                    icon="hugeicons:store-02"
                    :summary="
                      be.brand_complete
                        ? $t('ed.brand.complete', { name: be.brand.name })
                        : $t('ed.brand.missing', { fields: be.brand.missing_fields.join(', ') })
                    "
                    :completed="be.brand_complete"
                    :locked="
                      !dashboard.profile_complete ||
                      (be.event_rules?.length > 0 && !be.event_rules_agreed)
                    "
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
                          class="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-lg"
                        >
                          <Icon name="hugeicons:store-02" class="size-5" />
                        </div>
                        <div>
                          <p class="text-sm font-medium">{{ be.brand.name }}</p>
                          <p
                            v-if="!be.brand_complete"
                            class="text-muted-foreground text-xs tracking-tight sm:text-sm"
                          >
                            {{ $t("ed.brand.fieldsRemaining", be.brand.missing_fields.length, { count: be.brand.missing_fields.length }) }}
                          </p>
                        </div>
                      </div>
                      <NuxtLink
                        :to="`/brands/${be.brand.slug}/edit`"
                        class="border-border hover:bg-muted inline-flex items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
                      >
                        <Icon
                          :name="be.brand_complete ? 'hugeicons:view' : 'hugeicons:edit-02'"
                          class="size-3.5"
                        />
                        {{ be.brand_complete ? $t("ed.brand.view") : $t("ed.brand.completeCta") }}
                      </NuxtLink>
                    </div>
                  </DashboardExhibitorSection>
                </div>

                <!-- Promotion Posts -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'promo', el)">
                  <DashboardExhibitorSection
                    v-model:open="sectionStates[`${be.brand_event_id}_promo`]"
                    :title="$t('ed.promo.title')"
                    icon="hugeicons:image-02"
                    :summary="$t('ed.promo.uploaded', { count: be.promotion_posts_count, limit: be.promotion_post_limit })"
                    :completed="be.promotion_posts_count > 0"
                    :locked="
                      !dashboard.profile_complete ||
                      (be.event_rules?.length > 0 && !be.event_rules_agreed)
                    "
                    :deadline="formatDeadlineShort(be.promotion_post_deadline)"
                    :deadline-urgent="isDeadlineUrgent(be.promotion_post_deadline)"
                    section-key="promo"
                  >
                    <div class="flex items-center justify-between">
                      <p class="text-muted-foreground text-sm tracking-tight">
                        {{
                          be.promotion_posts_count > 0
                            ? $t("ed.promo.uploadedDescription", { count: be.promotion_posts_count, limit: be.promotion_post_limit })
                            : $t("ed.promo.uploadDescription", be.promotion_post_limit, { limit: be.promotion_post_limit })
                        }}
                      </p>
                      <NuxtLink
                        :to="`/brands/${be.brand.slug}/promotion-posts/${be.brand_event_id}`"
                        class="border-border hover:bg-muted inline-flex shrink-0 items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
                      >
                        <Icon
                          :name="
                            be.promotion_posts_count > 0 ? 'hugeicons:view' : 'hugeicons:upload-04'
                          "
                          class="size-3.5"
                        />
                        {{ be.promotion_posts_count > 0 ? $t("ed.promo.manage") : $t("ed.promo.upload") }}
                      </NuxtLink>
                    </div>
                  </DashboardExhibitorSection>
                </div>

                <!-- Operational Documents -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'docs', el)">
                  <DashboardExhibitorSection
                    v-if="be.documents?.length || showFasciaField(be) || showBadgeField(be)"
                    v-model:open="sectionStates[`${be.brand_event_id}_docs`]"
                    :title="$t('ed.docs.title')"
                    icon="hugeicons:file-01"
                    :summary="docsAndBoothSummary(be)"
                    :completed="isDocsComplete(be)"
                    :locked="
                      !dashboard.profile_complete ||
                      (be.event_rules?.length > 0 && !be.event_rules_agreed)
                    "
                    :attention-count="docsNeedingAttention(be)"
                    section-key="docs"
                  >
                    <div v-if="be.documents?.length" class="divide-border -mx-4 divide-y sm:-mx-5">
                      <DashboardExhibitorDocItem
                        v-for="doc in be.documents"
                        :key="doc.document.id"
                        class="px-4 py-6 first:pt-0 last:pb-0 sm:px-5"
                        :doc="doc.document"
                        :submission="doc.submission"
                        :status="doc.status"
                        :api-base="`/api/exhibitor/brands/${be.brand.slug}/events/${be.brand_event_id}/documents`"
                        mode="action"
                        @submitted="refreshDashboard"
                      />
                    </div>
                    <div
                      v-if="showFasciaField(be) || showBadgeField(be)"
                      :class="{ 'border-border mt-5 border-t pt-5': be.documents?.length }"
                    >
                      <p class="mb-3 text-sm font-medium">{{ $t("ed.docs.boothDetails") }}</p>
                      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div v-if="showFasciaField(be)" class="space-y-2">
                          <Label :for="`fascia_${be.brand_event_id}`">{{ $t("ed.docs.fasciaName") }}</Label>
                          <Input
                            :id="`fascia_${be.brand_event_id}`"
                            :model-value="
                              boothFields[be.brand_event_id]?.fascia_name ?? be.fascia_name ?? ''
                            "
                            :placeholder="$t('ed.docs.fasciaPlaceholder')"
                            @update:model-value="
                              (v) =>
                                setBoothField(be.brand_event_id, 'fascia_name', v.toUpperCase())
                            "
                            maxlength="24"
                          />
                          <p class="text-muted-foreground text-xs tracking-tight">
                            {{ $t("ed.docs.fasciaHint") }}
                          </p>
                        </div>
                        <div v-if="showBadgeField(be)" class="space-y-2">
                          <Label :for="`badge_${be.brand_event_id}`">{{ $t("ed.docs.badgeName") }}</Label>
                          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                            {{ $t("ed.docs.badgeDescription") }}
                          </p>
                          <Input
                            :id="`badge_${be.brand_event_id}`"
                            :model-value="
                              boothFields[be.brand_event_id]?.badge_name ?? be.badge_name ?? ''
                            "
                            :placeholder="$t('ed.docs.badgePlaceholder')"
                            @update:model-value="
                              (v) => setBoothField(be.brand_event_id, 'badge_name', v)
                            "
                          />
                        </div>
                        <div class="sm:col-span-2">
                          <Button
                            size="sm"
                            :disabled="savingBoothFields === be.brand_event_id"
                            @click="saveBoothFields(be)"
                          >
                            <Icon
                              v-if="savingBoothFields === be.brand_event_id"
                              name="svg-spinners:ring-resize"
                              class="mr-1.5 size-4"
                            />
                            {{ $t("ed.docs.saveBoothDetails") }}
                          </Button>
                        </div>
                      </div>
                    </div>
                  </DashboardExhibitorSection>
                </div>

                <!-- Badge & VIP Information (read-only, shown only if content exists) -->
                <DashboardExhibitorSection
                  v-if="be.event.badge_vip_info"
                  v-model:open="sectionStates[`${be.brand_event_id}_badge_vip`]"
                  :title="$t('ed.badgeVip.titleShort')"
                  icon="hugeicons:name-tag"
                  :summary="$t('ed.badgeVip.summary')"
                  :completed="true"
                  :locked="
                    !dashboard.profile_complete ||
                    (be.event_rules?.length > 0 && !be.event_rules_agreed)
                  "
                  section-key="badge_vip"
                >
                  <div
                    class="prose prose-sm max-w-none tracking-tight"
                    v-html="be.event.badge_vip_info"
                  />
                </DashboardExhibitorSection>

                <!-- Order Form -->
                <div :ref="(el) => setSectionRef(be.brand_event_id, 'order', el)">
                  <DashboardExhibitorSection
                    v-model:open="sectionStates[`${be.brand_event_id}_order`]"
                    :title="$t('ed.order.title')"
                    icon="hugeicons:shopping-cart-01"
                    :summary="orderSectionSummary(be)"
                    :completed="be.orders_count > 0"
                    :locked="
                      !dashboard.profile_complete ||
                      (be.event_rules?.length > 0 && !be.event_rules_agreed)
                    "
                    section-key="order"
                  >
                    <DashboardExhibitorOrderInfo :be="be" />
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
import { toast } from "vue-sonner";

const { t, locale } = useI18n();
const client = useSanctumClient();

const data = ref(null);
const pending = ref(true);
const dashboard = computed(() => data.value?.data);
const agreeingId = ref(null);
const checkedRules = reactive({});
const boothFields = ref({});
const savingBoothFields = ref(null);
const sectionStates = reactive({});
const eventCollapseStates = reactive({});
const sectionRefs = {};

const hasMultipleEvents = computed(() => (dashboard.value?.brand_events?.length || 0) > 1);

const dateLocale = computed(() => (locale.value === "zh" ? "zh-CN" : "en-US"));

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

async function refreshDashboard() {
  try {
    const data = await client("/api/exhibitor/dashboard");
    dashboard.value = data.data;
  } catch (e) {
    console.error("Failed to refresh dashboard:", e);
  }
}

async function saveProfile() {
  profileSaving.value = true;
  try {
    await client("/api/user/profile", { method: "PUT", body: profileForm });
    toast.success(t("ed.profile.updated"));
    await fetchData();
  } catch (e) {
    toast.error(e?.data?.message || t("ed.profile.failedToUpdate"));
  } finally {
    profileSaving.value = false;
  }
}

// --- Formatting helpers ---
function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString(dateLocale.value, {
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
  if (daysLeft <= 7) return t("ed.daysLeft", { days: daysLeft });
  return formatDate(dateStr);
}

function isDeadlineUrgent(dateStr) {
  if (!dateStr) return false;
  const deadline = new Date(dateStr);
  const daysLeft = Math.ceil((deadline - new Date()) / (1000 * 60 * 60 * 24));
  return daysLeft > 0 && daysLeft <= 7;
}

function orderSectionSummary(be) {
  if (be.orders_count > 0) return t("ed.order.ordersSubmitted", be.orders_count, { count: be.orders_count });
  const period = getCurrentOrderPeriod(be);
  if (period === "normal") return t("ed.order.normalOpen");
  if (period === "onsite") return t("ed.order.onsiteOpen", { rate: be.onsite_penalty_rate });
  // No period is active
  if (!be.normal_order_opens_at && !be.onsite_order_opens_at) return t("ed.order.submitOrder");
  const now = new Date();
  if (be.normal_order_opens_at && now < new Date(be.normal_order_opens_at)) return t("ed.order.notYetOpen");
  if (be.onsite_order_opens_at && now < new Date(be.onsite_order_opens_at)) return t("ed.order.normalClosed");
  return t("ed.order.allClosed");
}

function getCurrentOrderPeriod(be) {
  const now = new Date();
  if (be.normal_order_opens_at && be.normal_order_closes_at) {
    if (now >= new Date(be.normal_order_opens_at) && now <= new Date(be.normal_order_closes_at)) return "normal";
  }
  if (be.onsite_order_opens_at && be.onsite_order_closes_at) {
    if (now >= new Date(be.onsite_order_opens_at) && now <= new Date(be.onsite_order_closes_at)) return "onsite";
  }
  if (!be.normal_order_opens_at && !be.onsite_order_opens_at) return "normal"; // legacy: no periods configured
  return null;
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
    label: t("ed.stepper.profile"),
    completed: dashboard.value?.profile_complete,
    current: !dashboard.value?.profile_complete,
    locked: false,
  });

  if (be.event_rules?.length) {
    steps.push({
      key: "rules",
      label: t("ed.stepper.rules"),
      completed: be.event_rules_agreed,
      current: dashboard.value?.profile_complete && !be.event_rules_agreed,
      locked: profileLocked,
    });
  }

  steps.push({
    key: "brand",
    label: t("ed.stepper.brand"),
    completed: be.brand_complete,
    current: !rulesLocked && !be.brand_complete,
    locked: rulesLocked,
  });

  steps.push({
    key: "promo",
    label: t("ed.stepper.promo"),
    completed: be.promotion_posts_count > 0,
    current: !rulesLocked && be.brand_complete && be.promotion_posts_count === 0,
    locked: rulesLocked,
  });

  if (be.documents?.length || showFasciaField(be) || showBadgeField(be)) {
    steps.push({
      key: "docs",
      label: t("ed.stepper.docs"),
      completed: isDocsComplete(be),
      current: !rulesLocked && be.brand_complete && !isDocsComplete(be),
      locked: rulesLocked,
    });
  }

  steps.push({
    key: "order",
    label: t("ed.stepper.order"),
    completed: be.orders_count > 0,
    current: !rulesLocked && be.brand_complete && be.orders_count === 0,
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
  agreeingId.value = rule.document.id;
  try {
    await client(
      `/api/exhibitor/brands/${be.brand.slug}/events/${be.brand_event_id}/documents/${rule.document.ulid}`,
      { method: "POST", body: {} }
    );
    delete checkedRules[`${be.brand_event_id}_${rule.document.id}`];
    toast.success(t("ed.rules.recorded"));
    await fetchData();
  } catch (err) {
    toast.error(err?.data?.message || t("ed.rules.failedToSubmit"));
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
  if (status === "completed") return "text-success-foreground";
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
    parts.push(t("ed.docs.documents", { completed: be.documents_completed, total: be.documents_total }));
  }
  if (showFasciaField(be) || showBadgeField(be)) {
    const boothDone =
      (showFasciaField(be) ? (be.fascia_name ? 1 : 0) : 0) +
      (showBadgeField(be) ? (be.badge_name ? 1 : 0) : 0);
    const boothTotal = (showFasciaField(be) ? 1 : 0) + (showBadgeField(be) ? 1 : 0);
    parts.push(t("ed.docs.boothFields", { completed: boothDone, total: boothTotal }));
  }
  return parts.join(", ") || t("ed.docs.noDocsRequired");
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
      }
    );
    toast.success(t("ed.docs.saved"));
    await fetchData();
  } catch (err) {
    toast.error(err?.data?.message || t("ed.docs.failedToSave"));
  } finally {
    savingBoothFields.value = null;
  }
}

onMounted(fetchData);
</script>
