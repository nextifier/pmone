import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "bottom-nav",
  title: "Bottom Nav",
  description:
    "Mobile bottom navigation bar with a sliding active indicator. Three surfaces, four indicator styles, optional center action, badges, and hide-on-scroll.",
  installation: {
    importPath: "@/components/ui/bottom-nav",
    imports: ["BottomNav", "BottomNavItem", "BottomNavAction"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "A bordered bar of equal-width items with the active item highlighted by a sliding pill.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "controlled",
      title: "Controlled (v-model)",
      description:
        "Give each item a value and bind v-model to track the active one without routing. The indicator follows the selection. Clicking an item also emits select.",
      examples: ["controlled"],
      align: "center",
    },
    {
      id: "variants",
      title: "Variants",
      description:
        "default is a bordered solid bar, floating is a detached rounded card with a shadow, glass is a translucent bar with a backdrop blur.",
      examples: ["variants"],
      align: "center",
    },
    {
      id: "indicators",
      title: "Indicators",
      description:
        "pill slides a filled box behind the active item, bar draws a line on the top edge, dot places a marker under the icon, none removes it.",
      examples: ["indicators"],
      align: "center",
    },
    {
      id: "sizes",
      title: "Sizes",
      description: "sm, md, and lg scale the icon, label, item padding, and bar height together.",
      examples: ["sizes"],
      align: "center",
    },
    {
      id: "label-display",
      title: "Label display",
      description:
        "always keeps every label visible, active reveals the label only on the current item, none hides labels for an icon-only bar.",
      examples: ["label-display"],
      align: "center",
    },
    {
      id: "with-badge",
      title: "With badge",
      description: "Pass a number for a count pill (capped at 99+) or true for a small dot overlay on the icon.",
      examples: ["with-badge"],
      align: "center",
    },
    {
      id: "active-icon",
      title: "Active icon",
      description: "Set activeIcon to crossfade to a filled variant when the item is active.",
      examples: ["active-icon"],
      align: "center",
    },
    {
      id: "center-action",
      title: "Center action",
      description: "Drop a BottomNavAction between the items to get a raised center FAB for the primary action.",
      examples: ["center-action"],
      align: "center",
    },
    {
      id: "scroll-hide",
      title: "Hide on scroll",
      description:
        "hideOnScroll slides the bar away on scroll down and brings it back on scroll up. scrollTarget points it at a custom scroll container, used here to drive the demo inside a static frame.",
      examples: ["scroll-hide"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "BottomNav",
      props: [
        {
          name: "modelValue",
          type: "string | number",
          default: "—",
          description: "Active item value for v-model. Items with a matching value prop render active. Use for non-routed (button) navs.",
        },
        {
          name: "variant",
          type: '"default" | "floating" | "glass"',
          default: '"default"',
          description: "Visual surface. floating is a detached rounded card, glass is translucent with a backdrop blur.",
        },
        {
          name: "indicator",
          type: '"pill" | "bar" | "dot" | "none"',
          default: '"pill"',
          description: "Active-item indicator style. pill and bar slide via measurement, dot is rendered per item.",
        },
        {
          name: "size",
          type: '"sm" | "md" | "lg"',
          default: '"md"',
          description: "Scales icon size, label text, item padding, bar height, and nav min-height.",
        },
        {
          name: "labelDisplay",
          type: '"always" | "active" | "none"',
          default: '"always"',
          description: "always shows every label, active reveals it only on the current item, none hides labels.",
        },
        {
          name: "position",
          type: '"fixed" | "static"',
          default: '"fixed"',
          description: "fixed pins the bar to the bottom (mobile only, lg:hidden). static renders it in flow for demos.",
        },
        {
          name: "hideOnScroll",
          type: "boolean",
          default: "false",
          description: "Slides the bar out on scroll down and back in on scroll up, driven by the resolved scroll source (see scrollTarget).",
        },
        {
          name: "scrollTarget",
          type: "HTMLElement | string | null",
          default: "null",
          description: "Scroll source for hideOnScroll. Element or CSS selector. Defaults to the window when null.",
        },
        {
          name: "ariaLabel",
          type: "string",
          default: '"Bottom navigation"',
          description: "Accessible name for the nav landmark. Set a distinct label when a page has more than one nav.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
      events: [
        {
          name: "update:modelValue",
          description: "Fired when an item with a value is clicked. Enables v-model.",
        },
      ],
      slots: [{ name: "default", description: "BottomNavItem and BottomNavAction children." }],
    },
    {
      component: "BottomNavItem",
      props: [
        {
          name: "to",
          type: "string",
          default: "—",
          description: "Renders as NuxtLink when set. External URLs (http) open in a new tab. Omit it for a button.",
        },
        {
          name: "icon",
          type: "string",
          default: "—",
          description: "Required hugeicons name for the default (inactive) icon.",
        },
        {
          name: "activeIcon",
          type: "string",
          default: "—",
          description: "Hugeicons name shown when active. The two icons crossfade on state change.",
        },
        {
          name: "label",
          type: "string",
          default: "—",
          description: "Text label under the icon. Hidden when labelDisplay is none.",
        },
        {
          name: "badge",
          type: "number | boolean",
          default: "—",
          description: "Overlay badge. A number above 0 shows a count pill (99+ cap), true shows a small dot.",
        },
        {
          name: "exact",
          type: "boolean",
          default: "false",
          description: "Route matching mode. exact matches the path exactly, otherwise it matches by prefix. A to of / is always matched exactly.",
        },
        {
          name: "active",
          type: "boolean",
          default: "false",
          description: "Forces the item active when true (overrides value and route detection). Use for button-mode demos and static layouts.",
        },
        {
          name: "value",
          type: "string | number",
          default: "—",
          description: "Identifies the item for v-model. Active when it equals the parent BottomNav modelValue. Click also emits select.",
        },
        {
          name: "as",
          type: "string",
          default: '"button"',
          description: "Element to render when to is empty.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
      events: [{ name: "select", description: "Fired on click in both link and button modes." }],
    },
    {
      component: "BottomNavAction",
      props: [
        {
          name: "to",
          type: "string",
          default: "—",
          description: "Renders as NuxtLink when set. External URLs (http) open in a new tab. Omit it for a button.",
        },
        {
          name: "icon",
          type: "string",
          default: '"hugeicons:add-01"',
          description: "Hugeicons name for the center FAB icon.",
        },
        {
          name: "label",
          type: "string",
          default: "—",
          description: "Caption under the FAB. Also used as the aria-label on the control.",
        },
        {
          name: "as",
          type: "string",
          default: '"button"',
          description: "Element to render when to is empty.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
      events: [{ name: "select", description: "Fired on click in both link and button modes." }],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Tab"], description: "Moves focus between the navigation items." },
      { keys: ["Enter"], description: "Activates the focused item or center action." },
      { keys: ["Space"], description: "Activates the focused item or center action." },
    ],
    notes: [
      "Rendered as a <nav> landmark with role navigation and an ariaLabel name.",
      "The active item carries aria-current=page.",
      "Icon-only items (and the center action) fall back to their label as an aria-label so they are never unnamed.",
      "Count badges are announced as part of the item name (for example, Messages, 3).",
    ],
  },
});
