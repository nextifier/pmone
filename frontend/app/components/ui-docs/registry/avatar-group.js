import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "avatar-group",
  title: "Avatar Group",
  description:
    "Stacked avatar list with automatic overlap masking. Optional max limit shows the remainder count as a hover-card with the full list.",
  installation: {
    importPath: "@/components/ui/avatar-group",
    imports: ["AvatarGroup"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pass items array of objects with at least a name.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-max",
      title: "With max",
      description: "max truncates and adds a +N overflow with a hover card listing the rest.",
      examples: ["with-max"],
      align: "center",
    },
    {
      id: "sizes",
      title: "Sizes",
      description: "size accepts a rem value.",
      examples: ["sizes"],
      align: "center",
    },
    {
      id: "overlap",
      title: "Overlap",
      description: "overlap controls how tightly avatars stack (fraction of size). 0.2, default 0.2, then 0.5.",
      examples: ["overlap"],
      align: "center",
    },
    {
      id: "colorful",
      title: "Colorful",
      description: "colorful toggles the auto mesh-gradient initials background. Set false for a neutral fill.",
      examples: ["colorful"],
      align: "center",
    },
    {
      id: "tooltip",
      title: "Tooltip",
      description: "Names show on hover by default. Set :show-tooltip=\"false\" to disable.",
      examples: ["tooltip"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "AvatarGroup",
      props: [
        {
          name: "items",
          type: "{ id?, name?, profile_image? }[]",
          default: "[]",
          description: "List of avatar models.",
        },
        { name: "size", type: "number", default: "2.5", description: "Avatar diameter in rem." },
        { name: "overlap", type: "number", default: "0.2", description: "Fraction of size each avatar overlaps." },
        { name: "gap", type: "number", default: "2", description: "Gap between mask cutouts in pixels." },
        { name: "max", type: "number", default: "—", description: "Max avatars to show. Rest collapse into +N." },
        {
          name: "firstOnTop",
          type: "boolean",
          default: "true",
          description: "Stack first item above others. Set false to invert.",
        },
        { name: "colorful", type: "boolean", default: "true", description: "Forward to child Avatars." },
        { name: "showTooltip", type: "boolean", default: "true", description: "Show name tooltips on hover." },
        { name: "label", type: "string", default: "—", description: "aria-label override for the group." },
      ],
      slots: [
        {
          name: "overflow",
          description: "Custom slot for the +N indicator. Receives { count, hiddenItems }.",
        },
      ],
    },
  ],
});
