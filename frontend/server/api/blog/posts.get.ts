export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const query = getQuery(event);

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout

  try {
    const data = await $fetch(`${config.public.apiUrl}/api/public/blog/posts`, {
      headers: {
        "X-API-Key": config.pmOneApiKey, // Private - not exposed to browser
        Accept: "application/json",
      },
      query: {
        per_page: query.per_page || 100,
        sort: query.sort || "-published_at",
        author: query.author || config.public.blogUsernames,
        ...query, // Forward any additional query params
      },
      signal: controller.signal,
    });

    return data;
  } catch (error: any) {
    if (error.name === "AbortError") {
      throw createError({
        statusCode: 504,
        message: "Request timeout - API server took too long to respond",
      });
    }
    throw createError({
      statusCode: error.response?.status || 500,
      message: error.message || "Failed to fetch posts",
    });
  } finally {
    clearTimeout(timeoutId);
  }
});
