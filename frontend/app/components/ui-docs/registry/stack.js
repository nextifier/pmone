import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "stack",
  title: "Stack",
  description:
    "Isometric 3D card-stack illustration. Layers lift apart on hover and assemble on entrance; the thickness, outline, and motion all follow the chosen recede direction.",
  installation: {
    importPath: "@/components/ui/stack",
    imports: ["Stack"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description:
        "A three-layer stack with an icon on the front layer - the empty-state illustration.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "directions",
      title: "Directions",
      description:
        "Six recede directions. The slab thickness, outline, and the hover lift-apart adapt to each angle - never hardcoded to one pose.",
      examples: ["directions"],
      align: "center",
    },
    {
      id: "layers",
      title: "Layer count",
      description: "Any number of layers; depth spacing and the opacity fade derive automatically.",
      examples: ["layers"],
      align: "center",
    },
    {
      id: "sizing",
      title: "Size & aspect ratio",
      description: "Tune the card edge with size, or pass width + aspect for non-square slabs.",
      examples: ["sizing"],
      align: "center",
    },
    {
      id: "content",
      title: "Per-layer content",
      description: "Fill individual layers via #layer-N slots (1 = front, ascending = receding back).",
      examples: ["content"],
      align: "center",
    },
    {
      id: "interactive",
      title: "Interactive",
      description: "interactive toggles the hover lift-apart. Turn it off for a purely static mark.",
      examples: ["interactive"],
      align: "center",
    },
    {
      id: "entrance",
      title: "Entrance",
      description: "The stack fades and rises in on mount. Remount to replay it.",
      examples: ["entrance"],
      align: "center",
    },
    {
      id: "in-empty",
      title: "In an Empty state",
      description: "Composed inside Empty / EmptyMedia - the real-world usage this extracts from.",
      examples: ["in-empty"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Stack",
      props: [
        {
          name: "direction",
          type: '"br-tl" | "bl-tr" | "tl-br" | "tr-bl" | "b-t" | "t-b"',
          default: '"br-tl"',
          description:
            "Recede direction / pose. Front layer points toward the FROM edge, stack recedes toward the TO edge.",
        },
        { name: "layers", type: "number", default: "3", description: "Number of stacked slabs." },
        {
          name: "gap",
          type: "number",
          default: "59",
          description: "Depth (scene-Z) spacing between adjacent layers, in px.",
        },
        {
          name: "thickness",
          type: "number",
          default: "0.5",
          description: "Slab side-wall depth scale (drives the box-shadow thickness).",
        },
        {
          name: "size",
          type: "string",
          default: '"3rem"',
          description: "Card edge length (any CSS length).",
        },
        {
          name: "width",
          type: "string",
          default: "—",
          description: "Optional non-square layer width (overrides the square size).",
        },
        {
          name: "aspect",
          type: "string",
          default: "—",
          description: 'Optional layer aspect-ratio, e.g. "4 / 3".',
        },
        {
          name: "interactive",
          type: "boolean",
          default: "true",
          description: "Lift the layers apart on hover.",
        },
        { name: "class", type: "string", default: "—", description: "Merged onto the root box." },
      ],
      slots: [
        { name: "default", description: "Front-layer content (e.g. an icon)." },
        {
          name: "layer-N",
          description: "Per-layer content; 1 = front, ascending = receding back.",
        },
      ],
    },
  ],
});
