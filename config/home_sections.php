<?php

/*
|--------------------------------------------------------------------------
| Home Page Section Catalog
|--------------------------------------------------------------------------
|
| Single source of truth for the show/hide toggles rendered on each event
| website's home page. Consumed by:
|   - ProjectController::updateWebsiteSettings() (key validation)
|   - PublicProjectController::websiteSettings()  (resolved defaults sent to
|     the pmone-events websites under `settings.home_sections`)
|   - ProjectResource                              (catalog shape sent to the
|     admin so the Website Settings page renders one toggle per section)
|
| Visibility values live in `projects.settings.website_settings.home_sections`
| as a { sectionKey => bool } map. The four original toggles (rundown,
| brand_preview, partners, hotels) keep a `legacy_path` so already-stored
| values and deployed event sites keep working (see the read endpoint).
|
| Adding a new toggleable section = add one entry under `sections`, list its
| key under the relevant `projects.*`, and wire `useHomeSection('key')` in the
| matching pmone-events index.vue.
|
| IMPORTANT: any section that currently renders unconditionally on a live site
| MUST default to `true`, otherwise adding its `v-if` would hide it on projects
| that have never saved a value. Only the four legacy toggles default to their
| historical values.
|
*/

return [

    /*
    | Master section definitions, keyed by sectionKey.
    |   label       – shown in the admin toggle row
    |   description – one-line helper under the label (optional)
    |   default     – value when neither `home_sections` nor legacy is stored
    |   force_param – URL query that force-shows the section for QA/preview
    |   legacy_path – (4 originals only) pre-existing storage path to fall back to
    */
    'sections' => [

        // --- Four original toggles (preserve historical defaults + legacy path) ---
        'brand_preview' => [
            'label' => 'Brand Preview',
            'description' => 'A preview carousel of exhibitor brands.',
            'default' => false,
            'force_param' => 'show-brands',
            'legacy_path' => 'brands.show_brand_preview_on_home_page',
        ],
        'rundown' => [
            'label' => 'Rundown',
            'description' => 'The event schedule / rundown.',
            'default' => false,
            'force_param' => 'show-rundown',
            'legacy_path' => 'rundown.show_rundown_on_home_page',
        ],
        'hotels' => [
            'label' => 'Hotels',
            'description' => 'Hotel reservation and accommodation section.',
            'default' => false,
            'force_param' => 'show-hotel',
            'legacy_path' => 'hotels.show_hotel_section_on_home_page',
        ],
        'partners' => [
            'label' => 'Credits',
            'description' => 'The Credits wall of partners and supporters.',
            'default' => true,
            'force_param' => 'show-partners',
            'legacy_path' => 'partners.show_partners_on_home_page',
        ],

        // --- Common sections (render unconditionally today -> default true) ---
        'hero' => [
            'label' => 'Hero',
            'description' => 'The main hero banner at the top of the home page.',
            'default' => true,
            'force_param' => 'show-hero',
        ],
        'guest_list' => [
            'label' => 'Guest List',
            'description' => 'Featured speakers and guests.',
            'default' => true,
            'force_param' => 'show-guest-list',
        ],
        'about_event' => [
            'label' => 'About Event',
            'description' => 'The "About the event" section.',
            'default' => true,
            'force_param' => 'show-about-event',
        ],
        'partnerships' => [
            'label' => 'Partnerships',
            'description' => 'Partnership and sponsorship opportunities.',
            'default' => true,
            'force_param' => 'show-partnerships',
        ],
        'visitor_cta' => [
            'label' => 'Visitor CTA',
            'description' => 'Call-to-action inviting visitors to register.',
            'default' => true,
            'force_param' => 'show-visitor-cta',
        ],
        'media_coverages_slider' => [
            'label' => 'Media Coverage',
            'description' => 'Press and media coverage highlights.',
            'default' => true,
            'force_param' => 'show-media-coverage',
        ],
        'blog_post_slider' => [
            'label' => 'Blog Posts',
            'description' => 'Latest blog posts carousel.',
            'default' => true,
            'force_param' => 'show-blog',
        ],
        'faq' => [
            'label' => 'FAQ',
            'description' => 'Frequently asked questions.',
            'default' => true,
            'force_param' => 'show-faq',
        ],

        // --- Project-specific sections (default true) ---
        'event_stats' => [
            'label' => 'Event Stats',
            'description' => 'Key event statistics.',
            'default' => true,
            'force_param' => 'show-event-stats',
        ],
        'theme_concept' => [
            'label' => 'Theme Concept',
            'description' => 'The event theme and concept.',
            'default' => true,
            'force_param' => 'show-theme-concept',
        ],
        'who_visits' => [
            'label' => 'Who Visits',
            'description' => 'Audience / who-visits breakdown.',
            'default' => true,
            'force_param' => 'show-who-visits',
        ],
        'why_exhibit' => [
            'label' => 'Why Exhibit',
            'description' => 'Reasons to exhibit.',
            'default' => true,
            'force_param' => 'show-why-exhibit',
        ],
        'facts_and_figures' => [
            'label' => 'Facts & Figures',
            'description' => 'Highlighted facts and figures.',
            'default' => true,
            'force_param' => 'show-facts',
        ],
        'mega_property_intro' => [
            'label' => 'MegaProperty Intro',
            'description' => 'MegaProperty introduction section.',
            'default' => true,
            'force_param' => 'show-mega-property',
        ],
        'architect_designer_alley' => [
            'label' => 'Architect & Designer Alley',
            'description' => 'Architect & Designer Alley highlight.',
            'default' => true,
            'force_param' => 'show-architect-alley',
        ],
        'speakers' => [
            'label' => 'Speakers',
            'description' => 'Featured speakers lineup.',
            'default' => true,
            'force_param' => 'show-speakers',
        ],
        'past_exhibitors' => [
            'label' => 'Past Exhibitors',
            'description' => 'Past exhibitors showcase.',
            'default' => true,
            'force_param' => 'show-past-exhibitors',
        ],

        // --- iicc-specific sections (default true) ---
        'about' => [
            'label' => 'About',
            'description' => 'About the conference.',
            'default' => true,
            'force_param' => 'show-about',
        ],
        'topics' => [
            'label' => 'Topics',
            'description' => 'Conference topics.',
            'default' => true,
            'force_param' => 'show-topics',
        ],
        'programs' => [
            'label' => 'Programs',
            'description' => 'Program highlights.',
            'default' => true,
            'force_param' => 'show-programs',
        ],
        'why_attend' => [
            'label' => 'Why Attend',
            'description' => 'Reasons to attend.',
            'default' => true,
            'force_param' => 'show-why-attend',
        ],
        'who_attends' => [
            'label' => 'Who Attends',
            'description' => 'Who attends the event.',
            'default' => true,
            'force_param' => 'show-who-attends',
        ],
        'why_yogyakarta' => [
            'label' => 'Why Yogyakarta',
            'description' => 'Why the event is held in Yogyakarta.',
            'default' => true,
            'force_param' => 'show-why-yogyakarta',
        ],
        'tickets' => [
            'label' => 'Tickets',
            'description' => 'Ticket / registration section.',
            'default' => true,
            'force_param' => 'show-tickets-section',
        ],
        'past_events' => [
            'label' => 'Past Events',
            'description' => 'Past editions showcase.',
            'default' => true,
            'force_param' => 'show-past-events',
        ],
    ],

    /*
    | Ordered section keys each project's home page actually renders, derived
    | from the matching pmone-events app `index.vue`. Order here drives the
    | order of toggles in the admin UI. Sections that are commented out in an
    | app's index.vue are intentionally omitted (no meaningful toggle).
    |
    | NOTE: project `cbe` is shared by three sites (cafeexpo, cokelatexpo/cei,
    | icf) whose home pages are identical and all read website settings from
    | `cbe`. Toggling a section here affects all three simultaneously.
    */
    'projects' => [
        'megabuild' => [
            'hero', 'brand_preview', 'rundown', 'hotels', 'mega_property_intro',
            'architect_designer_alley', 'about_event', 'facts_and_figures',
            'partnerships', 'visitor_cta', 'media_coverages_slider', 'partners',
            'blog_post_slider', 'faq',
        ],
        'inacon' => [
            'hero', 'brand_preview', 'guest_list', 'rundown', 'hotels',
            'about_event', 'partnerships', 'visitor_cta', 'media_coverages_slider',
            'partners', 'blog_post_slider', 'faq',
        ],
        'icc' => [
            'hero', 'brand_preview', 'rundown', 'hotels', 'about_event',
            'partnerships', 'blog_post_slider', 'faq',
        ],
        'cbe' => [
            'hero', 'event_stats', 'brand_preview', 'about_event', 'theme_concept',
            'rundown', 'who_visits', 'hotels', 'why_exhibit', 'partnerships',
            'visitor_cta', 'partners', 'blog_post_slider', 'faq',
        ],
        'keramika' => [
            'hero', 'brand_preview', 'rundown', 'hotels', 'about_event',
            'partnerships', 'visitor_cta', 'media_coverages_slider', 'partners',
            'blog_post_slider', 'faq',
        ],
        'renex' => [
            'hero', 'brand_preview', 'rundown', 'hotels', 'about_event',
            'facts_and_figures', 'partnerships', 'visitor_cta',
            'media_coverages_slider', 'partners', 'blog_post_slider', 'faq',
        ],
        'flei' => [
            'hero', 'brand_preview', 'rundown', 'hotels', 'about_event',
            'partnerships', 'visitor_cta', 'partners', 'blog_post_slider', 'faq',
        ],
        'morefood' => [
            'hero', 'brand_preview', 'rundown', 'hotels', 'about_event',
            'partnerships', 'visitor_cta', 'media_coverages_slider', 'partners',
            'blog_post_slider', 'faq',
        ],
        'ioe' => [
            'hero', 'brand_preview', 'past_exhibitors', 'rundown', 'hotels',
            'about_event', 'partnerships', 'media_coverages_slider', 'partners',
            'blog_post_slider', 'faq',
        ],
        'globalaiexpo' => [
            'hero', 'speakers', 'brand_preview', 'rundown', 'hotels', 'about_event',
            'partnerships', 'visitor_cta', 'partners', 'blog_post_slider', 'faq',
        ],
        'askindo' => [
            'hero', 'about', 'topics', 'programs', 'rundown', 'why_attend',
            'who_attends', 'why_yogyakarta', 'tickets', 'hotels', 'faq',
            'past_events', 'partners',
        ],
    ],

    /*
    | Fallback list for any project without an explicit entry above. Mirrors the
    | minimal base layer home page in pmone-events.
    */
    'default' => [
        'hero', 'about_event', 'partnerships', 'media_coverages_slider', 'blog_post_slider',
    ],
];
