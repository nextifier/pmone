import { readFileSync, readdirSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import matter from "gray-matter";
import { marked } from "marked";

function getDocsRoot() {
  return join(process.cwd(), "docs");
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
    // Directory doesn't exist
  }
  return files;
}

function filePathToSlug(filePath: string, docsRoot: string): string {
  const rel = relative(docsRoot, filePath)
    .replace(/\.md$/, "")
    .replace(/\\/g, "/");

  const parts = rel.split("/");
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

function buildSlugMap(): Map<string, string> {
  const now = Date.now();
  if (slugMap && now - slugMapTimestamp < CACHE_TTL) {
    return slugMap;
  }

  const docsRoot = getDocsRoot();
  const allFiles = [
    ...scanMarkdownFiles(join(docsRoot, "staff", "en")),
    ...scanMarkdownFiles(join(docsRoot, "exhibitor", "en")),
    ...scanMarkdownFiles(join(docsRoot, "exhibitor", "zh")),
  ];

  const map = new Map<string, string>();
  for (const filePath of allFiles) {
    try {
      const raw = readFileSync(filePath, "utf-8");
      const { data: fm } = matter(raw);
      const baseSlug = filePathToSlug(filePath, docsRoot);
      const slug = fm.locale === "zh" ? `${baseSlug}-zh` : baseSlug;
      map.set(slug, filePath);
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

  const map = buildSlugMap();
  const filePath = map.get(slug);

  if (!filePath) {
    throw createError({
      statusCode: 404,
      message: `Doc "${slug}" not found`,
    });
  }

  try {
    const raw = readFileSync(filePath, "utf-8");
    const { data: fm, content: mdContent } = matter(raw);

    // Remove HTML comments (video placeholder etc.)
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
