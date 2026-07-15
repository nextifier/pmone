// Shared logic for the exhibitor dashboard, used by both DashboardExhibitor
// (stepper steps + collapsible header counts) and DashboardExhibitorSections
// (section rendering). Keeping it here prevents the single/multi-event render
// paths from drifting apart. Auto-imported.

// Booth fascia field only applies to shell-scheme booth types.
export function exhibitorShowFascia(be) {
  return be.booth_type === "standard_shell_scheme" || be.booth_type === "enhanced_shell_scheme";
}

// Badge name applies to any booth that has a type.
export function exhibitorShowBadge(be) {
  return !!be.booth_type;
}

// A brand-event's operational docs are "complete" when every required document
// is submitted and any applicable booth fields are filled.
export function exhibitorDocsComplete(be) {
  const docsComplete = !be.documents?.length || be.documents_completed === be.documents_total;
  const fasciaOk = !exhibitorShowFascia(be) || !!be.fascia_name;
  const badgeOk = !exhibitorShowBadge(be) || !!be.badge_name;
  return docsComplete && fasciaOk && badgeOk;
}

// Build the ordered onboarding steps for a brand-event. `t` is the i18n
// translate function so labels stay localized.
export function getExhibitorSteps(be, profileComplete, t) {
  const profileLocked = !profileComplete;
  const rulesLocked = profileLocked || (be.event_rules?.length > 0 && !be.event_rules_agreed);
  const steps = [];

  steps.push({
    key: "profile",
    label: t("ed.stepper.profile"),
    completed: profileComplete,
    current: !profileComplete,
    locked: false,
  });

  if (be.event_rules?.length) {
    steps.push({
      key: "rules",
      label: t("ed.stepper.rules"),
      completed: be.event_rules_agreed,
      current: profileComplete && !be.event_rules_agreed,
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

  // When several brands share one booth, operational documents and the order
  // form are handled under the booth's primary brand only, so these steps are
  // omitted for the non-primary brands.
  const boothPrimary = be.is_booth_primary !== false;

  if (boothPrimary && (be.documents?.length || exhibitorShowFascia(be) || exhibitorShowBadge(be))) {
    steps.push({
      key: "docs",
      label: t("ed.stepper.docs"),
      completed: exhibitorDocsComplete(be),
      current: !rulesLocked && be.brand_complete && !exhibitorDocsComplete(be),
      locked: rulesLocked,
    });
  }

  if (boothPrimary) {
    steps.push({
      key: "order",
      label: t("ed.stepper.order"),
      completed: be.orders_count > 0,
      current: !rulesLocked && be.brand_complete && be.orders_count === 0,
      locked: rulesLocked,
    });
  }

  return steps;
}
