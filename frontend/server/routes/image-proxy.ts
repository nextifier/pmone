export default defineEventHandler(async (event) => {
  const query = getQuery(event);
  const url = query.url;

  if (!url) {
    throw createError({
      statusCode: 400,
      statusMessage: "Missing url parameter",
    });
  }

  try {
    const response = await fetch(url);

    if (!response.ok) {
      throw createError({
        statusCode: response.status,
        statusMessage: `Failed to fetch image: ${response.statusText}`,
      });
    }

    const imageBuffer = await response.arrayBuffer();
    const contentType = response.headers.get("content-type") || "image/png";

    setHeader(event, "Content-Type", contentType);
    setHeader(event, "Cache-Control", "public, max-age=31536000");
    setHeader(event, "Access-Control-Allow-Origin", "*");

    return new Uint8Array(imageBuffer);
  } catch (error) {
    console.error("[Image Proxy] Error:", error);
    throw createError({
      statusCode: 500,
      statusMessage: `Failed to proxy image: ${error.message}`,
    });
  }
});
