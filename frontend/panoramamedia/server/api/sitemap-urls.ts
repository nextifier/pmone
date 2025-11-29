import { asSitemapUrl, defineSitemapEventHandler } from "#imports";

export default defineSitemapEventHandler(async () => {
  const config = useAppConfig();

  const posts = await $fetch<ReturnType<typeof asSitemapUrl>>(
    `${config.app.pmOneApiUrl}/api/public/blog/posts`,
    {
      headers: {
        Authorization: `Bearer ${config.app.pmOneApiKey}`,
        Accept: "application/json",
      },
      query: {
        per_page: 1000,
        sort: "published_at",
        order: "desc",
      },
    },
  ).then((res) => {
    return res.data.map((post) => {
      return {
        loc: `/news/${post.slug}`,
        lastmod: post.updated_at,
      };
    });
  });

  // const brands = await $fetch<ReturnType<typeof asSitemapUrl>>(
  //   `${useAppConfig().app.apiUrl}/api/exhibitors?filter[is_published]=1`,
  // ).then((res) => {
  //   return res.map((brand) => {
  //     return {
  //       loc: `/brands/${brand.slug}`,
  //       lastmod: brand.updated_at,
  //     };
  //   });
  // });

  return [
    ...posts,
    // ...brands
  ];
});
