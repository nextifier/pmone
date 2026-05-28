export default {
  name: "introduction",
  title: "Introduction",
  description:
    "A small set of Vue components for building admin dashboards and marketing sites. Built on top of reka-ui primitives, styled with Tailwind, and shipped with a few project-specific additions where the defaults fell short.",
  sections: [
    {
      id: "principles",
      title: "Design principles",
      description:
        "Keep typography tight (tracking-tight by default, tracking-tighter for larger headings). Font weight stops at font-semibold. Colors come from CSS variables, never raw Tailwind palette values. Animations are reserved for state changes that matter, not decoration.",
    },
    {
      id: "stack",
      title: "Stack",
      description:
        "Nuxt 4 with auto-imports. Tailwind CSS v4. Headless primitives from reka-ui (formerly Radix Vue). Forms wrap controls in Field. For modals, default to DialogResponsive (Dialog on desktop, Drawer on mobile).",
    },
    {
      id: "structure",
      title: "Folder structure",
      description:
        "Each component lives in components/ui/{name}. The index.ts re-exports the main component and its sub-components. A few components use class-variance-authority for variant styles. Project-specific components (DialogResponsive, InputPhone, TableData) sit in the same folder with descriptive suffixes.",
    },
    {
      id: "contributing",
      title: "Adding a component",
      description:
        "Create the folder under components/ui, mirror the pattern from a sibling (index.ts for exports, one .vue per sub-component, context.ts when there are variants). Then add a registry entry under components/ui-docs/registry and a new item in sidebar-nav.js.",
    },
  ],
};
