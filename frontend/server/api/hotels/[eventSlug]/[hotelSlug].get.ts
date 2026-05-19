export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const eventSlug = getRouterParam(event, "eventSlug");
  const hotelSlug = getRouterParam(event, "hotelSlug");

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  return await $fetch(`${baseUrl}/api/public/events/${eventSlug}/hotels/${hotelSlug}`, {
    headers: { "X-API-Key": apiKey },
  });
});
