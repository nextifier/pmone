import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "skeleton",
  title: "Skeleton",
  description:
    "Animated placeholder block. Build loading states by stacking Skeletons in the rough shape of the eventual content.",
  installation: {
    importPath: "@/components/ui/skeleton",
    imports: ["Skeleton"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single block.",
      examples: ["default"],
    },
    {
      id: "card",
      title: "Card placeholder",
      description: "Mimic the shape of a card-style item.",
      examples: ["card"],
    },
  ],
  apiReference: [
    {
      component: "Skeleton",
      props: [
        { name: "class", type: "string", default: "—", description: "Width, height, radius. Default is bg-muted with pulse animation." },
      ],
    },
  ],
});
