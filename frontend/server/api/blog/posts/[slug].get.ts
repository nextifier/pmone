export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const slug = getRouterParam(event, "slug");

  if (!slug) {
    throw createError({
      statusCode: 400,
      message: "Post slug is required",
    });
  }

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout

  try {
    const data = await $fetch(`${config.public.pmOneApiUrl}/api/public/blog/posts/${slug}`, {
      headers: {
        "X-API-Key": config.pmOneApiKey, // Private - not exposed to browser
        Accept: "application/json",
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
      message: error.message || "Failed to fetch post",
    });
  } finally {
    clearTimeout(timeoutId);
  }
});
