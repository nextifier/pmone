export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const query = getQuery(event);

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 15000);

  try {
    const data = await $fetch(`${config.public.apiUrl}/api/public/blog/posts`, {
      headers: {
        "X-API-Key": config.pmOneApiKey,
        Accept: "application/json",
      },
      query: {
        per_page: 100,
        sort: "title",
        tag: "docs",
        ...query,
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
      message: error.message || "Failed to fetch docs",
    });
  } finally {
    clearTimeout(timeoutId);
  }
});
