import { defineCollection, defineContentConfig } from "@nuxt/content";
import { z } from "zod";

export default defineContentConfig({
  collections: {
    docs: defineCollection({
      type: "page",
      source: {
        include: "docs/**/*.md",
        exclude: ["docs/_*", "docs/README.md"],
      },
      schema: z.object({
        section: z.string().optional(),
        order: z.number().default(999),
        locale: z.string().default("en"),
        audience: z.string().default("staff"),
      }),
    }),
  },
});
