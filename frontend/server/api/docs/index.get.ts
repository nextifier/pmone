import matter from "gray-matter";

interface DocEntry {
  slug: string;
  title: string;
  tags: string[];
  excerpt: string;
  order: number;
  sectionOrder: number;
}

function keyToSlug(key: string): string {
  // key: "staff:en:01-getting-started:01-dashboard-overview.md"
  // -> "staff-getting-started-dashboard-overview"
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

function extractSectionOrder(key: string): number {
  const parts = key.split(":");
  // Section folder is 3rd segment: staff:en:01-getting-started:...
  const sectionFolder = parts[2] || "";
  const match = sectionFolder.match(/^(\d+)-/);
  return match ? parseInt(match[1], 10) : 999;
}

let cachedDocs: { entries: DocEntry[]; timestamp: number } | null = null;
const CACHE_TTL = 60_000;

export default defineEventHandler(async (event) => {
  const query = getQuery(event);
  const locale = (query.locale as string) || "en";

  const now = Date.now();
  if (cachedDocs && now - cachedDocs.timestamp < CACHE_TTL) {
    return { data: filterByLocale(cachedDocs.entries, locale) };
  }

  const storage = useStorage("assets:server:docs");
  const allKeys = await storage.getKeys();

  const mdKeys = allKeys.filter(
    (k) => k.endsWith(".md") && !k.split(":").pop()?.startsWith("_") && !k.startsWith("README"),
  );

  const entries: DocEntry[] = [];

  for (const key of mdKeys) {
    try {
      const raw = (await storage.getItem(key)) as string;
      if (!raw) continue;

      const { data: fm } = matter(raw);
      if (!fm.title) continue;

      const slug = keyToSlug(key);
      const tag = mapToTag(fm.audience || "staff", fm.section || "");
      const localeTag = fm.locale === "zh" ? "zh" : "en";

      entries.push({
        slug: localeTag === "zh" ? `${slug}-zh` : slug,
        title: fm.title,
        tags: ["docs", tag, localeTag],
        excerpt: fm.description || "",
        order: fm.order || 999,
        sectionOrder: extractSectionOrder(key),
      });
    } catch {
      // Skip
    }
  }

  entries.sort((a, b) => {
    if (a.sectionOrder !== b.sectionOrder) return a.sectionOrder - b.sectionOrder;
    return a.order - b.order;
  });

  cachedDocs = { entries, timestamp: now };
  return { data: filterByLocale(entries, locale) };
});

function filterByLocale(entries: DocEntry[], locale: string): DocEntry[] {
  return entries.filter((e) => {
    const isZh = e.tags.includes("zh");
    const isEn = e.tags.includes("en");

    if (locale === "zh") {
      const isStaff = e.tags.includes("staff-guide") || e.tags.includes("getting-started");
      if (isStaff) return isEn;
      return isZh;
    }

    return isEn;
  });
}
