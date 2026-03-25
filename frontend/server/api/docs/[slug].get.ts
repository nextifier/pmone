import matter from "gray-matter";
import { marked } from "marked";

function keyToSlug(key: string): string {
  const clean = key.replace(/\.md$/, "");
  const parts = clean.split(":");
  return parts
    .filter((p) => !["en", "zh"].includes(p))
    .map((p) => p.replace(/^\d+-/, ""))
    .join("-");
}

function mapToTag(audience: string, section: string): string {
  if (audience === "staff" && section === "getting-started") {
    return "getting-started";
  }
  if (audience === "staff") {
    return "staff-guide";
  }
  return "exhibitor-guide";
}

let slugMap: Map<string, string> | null = null;
let slugMapTimestamp = 0;
const CACHE_TTL = 60_000;

async function buildSlugMap(): Promise<Map<string, string>> {
  const now = Date.now();
  if (slugMap && now - slugMapTimestamp < CACHE_TTL) {
    return slugMap;
  }

  const storage = useStorage("assets:server:docs");
  const allKeys = await storage.getKeys();

  const mdKeys = allKeys.filter(
    (k) => k.endsWith(".md") && !k.split(":").pop()?.startsWith("_") && !k.startsWith("README"),
  );

  const map = new Map<string, string>();
  for (const key of mdKeys) {
    try {
      const raw = (await storage.getItem(key)) as string;
      if (!raw) continue;

      const { data: fm } = matter(raw);
      const baseSlug = keyToSlug(key);
      const slug = fm.locale === "zh" ? `${baseSlug}-zh` : baseSlug;
      map.set(slug, key);
    } catch {
      // Skip
    }
  }

  slugMap = map;
  slugMapTimestamp = now;
  return map;
}

export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, "slug");

  if (!slug) {
    throw createError({
      statusCode: 400,
      message: "Doc slug is required",
    });
  }

  const map = await buildSlugMap();
  const storageKey = map.get(slug);

  if (!storageKey) {
    throw createError({
      statusCode: 404,
      message: `Doc "${slug}" not found`,
    });
  }

  try {
    const storage = useStorage("assets:server:docs");
    const raw = (await storage.getItem(storageKey)) as string;

    if (!raw) {
      throw new Error("Empty content");
    }

    const { data: fm, content: mdContent } = matter(raw);
    const cleanMd = mdContent.replace(/<!--[\s\S]*?-->/g, "").trim();
    const html = await marked(cleanMd);
    const tag = mapToTag(fm.audience || "staff", fm.section || "");

    return {
      data: {
        slug,
        title: fm.title || slug,
        excerpt: fm.description || "",
        content: html,
        tags: [{ name: "docs" }, { name: tag }],
      },
    };
  } catch (error: any) {
    throw createError({
      statusCode: 500,
      message: `Failed to read doc: ${error.message}`,
    });
  }
});
