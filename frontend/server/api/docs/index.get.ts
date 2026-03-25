import { readFileSync, readdirSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import matter from "gray-matter";

interface DocEntry {
  slug: string;
  title: string;
  tags: string[];
  excerpt: string;
  order: number;
  sectionOrder: number;
}

function getDocsRoot() {
  // docs/ lives at the project root (one level above frontend/)
  return join(process.cwd(), "..", "docs");
}

function scanMarkdownFiles(dir: string): string[] {
  const files: string[] = [];

  try {
    const entries = readdirSync(dir);
    for (const entry of entries) {
      const fullPath = join(dir, entry);
      const stat = statSync(fullPath);
      if (stat.isDirectory()) {
        files.push(...scanMarkdownFiles(fullPath));
      } else if (entry.endsWith(".md") && !entry.startsWith("_")) {
        files.push(fullPath);
      }
    }
  } catch {
    // Directory doesn't exist, return empty
  }

  return files;
}

function filePathToSlug(filePath: string, docsRoot: string): string {
  // e.g. staff/en/01-getting-started/01-dashboard-overview.md
  //   -> staff-getting-started-dashboard-overview
  const rel = relative(docsRoot, filePath)
    .replace(/\.md$/, "")
    .replace(/\\/g, "/");

  const parts = rel.split("/");
  // Remove locale segment (en/zh) and strip numeric prefixes
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

// Extract section order from folder name like "01-getting-started"
function extractSectionOrder(filePath: string, docsRoot: string): number {
  const rel = relative(docsRoot, filePath).replace(/\\/g, "/");
  const parts = rel.split("/");
  // Section folder is the 3rd segment: staff/en/01-getting-started/...
  const sectionFolder = parts[2] || "";
  const match = sectionFolder.match(/^(\d+)-/);
  return match ? parseInt(match[1], 10) : 999;
}

let cachedDocs: { entries: DocEntry[]; timestamp: number } | null = null;
const CACHE_TTL = 60_000; // 1 minute in dev, sufficient for file changes

export default defineEventHandler(async (event) => {
  const query = getQuery(event);
  const locale = (query.locale as string) || "en";

  const now = Date.now();
  if (cachedDocs && now - cachedDocs.timestamp < CACHE_TTL) {
    const filtered = filterByLocale(cachedDocs.entries, locale);
    return { data: filtered };
  }

  const docsRoot = getDocsRoot();
  const allFiles = [
    ...scanMarkdownFiles(join(docsRoot, "staff", "en")),
    ...scanMarkdownFiles(join(docsRoot, "exhibitor", "en")),
    ...scanMarkdownFiles(join(docsRoot, "exhibitor", "zh")),
  ];

  const entries: DocEntry[] = [];

  for (const filePath of allFiles) {
    try {
      const raw = readFileSync(filePath, "utf-8");
      const { data: fm } = matter(raw);

      if (!fm.title) continue;

      const slug = filePathToSlug(filePath, docsRoot);
      const tag = mapToTag(fm.audience || "staff", fm.section || "");
      const localeTag = fm.locale === "zh" ? "zh" : "en";

      entries.push({
        slug: localeTag === "zh" ? `${slug}-zh` : slug,
        title: fm.title,
        tags: ["docs", tag, localeTag],
        excerpt: fm.description || "",
        order: fm.order || 999,
        sectionOrder: extractSectionOrder(filePath, docsRoot),
      });
    } catch {
      // Skip files that can't be parsed
    }
  }

  // Sort by section order, then article order
  entries.sort((a, b) => {
    if (a.sectionOrder !== b.sectionOrder) return a.sectionOrder - b.sectionOrder;
    return a.order - b.order;
  });

  cachedDocs = { entries, timestamp: now };

  const filtered = filterByLocale(entries, locale);
  return { data: filtered };
});

function filterByLocale(entries: DocEntry[], locale: string): DocEntry[] {
  return entries.filter((e) => {
    const isZh = e.tags.includes("zh");
    const isEn = e.tags.includes("en");

    if (locale === "zh") {
      // Show staff EN + exhibitor ZH
      const isStaff = e.tags.includes("staff-guide") || e.tags.includes("getting-started");
      if (isStaff) return isEn;
      return isZh;
    }

    // Default EN: show only EN docs
    return isEn;
  });
}
