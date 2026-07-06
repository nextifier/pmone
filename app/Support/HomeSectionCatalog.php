<?php

namespace App\Support;

/**
 * Thin reader over config/home_sections.php. Resolves the ordered list of
 * home-page section definitions for a given project and exposes the valid
 * section keys for request validation.
 *
 * @phpstan-type SectionDefinition array{
 *     key: string,
 *     label: string,
 *     description: ?string,
 *     default: bool,
 *     force_param: ?string,
 *     legacy_path: ?string,
 * }
 */
class HomeSectionCatalog
{
    /**
     * Ordered, fully-resolved section definitions a project's home page renders.
     * Unknown keys (listed for a project but missing a master definition) are
     * skipped so a typo never surfaces a broken toggle.
     *
     * @return list<SectionDefinition>
     */
    public static function for(string $username): array
    {
        $sections = config('home_sections.sections', []);
        $order = config("home_sections.projects.{$username}")
            ?? config('home_sections.default', []);

        $resolved = [];

        foreach ($order as $key) {
            if (! isset($sections[$key])) {
                continue;
            }

            $definition = $sections[$key];

            $resolved[] = [
                'key' => $key,
                'label' => $definition['label'] ?? $key,
                'description' => $definition['description'] ?? null,
                'default' => (bool) ($definition['default'] ?? true),
                'force_param' => $definition['force_param'] ?? null,
                'legacy_path' => $definition['legacy_path'] ?? null,
            ];
        }

        return $resolved;
    }

    /**
     * Public-facing catalog shape for the admin Website Settings page. Only the
     * fields the toggle list renders (key, label) plus the default used to seed
     * the switch; server-only fields (legacy_path, description) are dropped.
     *
     * @return list<array{key: string, label: string, default: bool}>
     */
    public static function forResource(string $username): array
    {
        return array_map(
            static fn (array $section): array => [
                'key' => $section['key'],
                'label' => $section['label'],
                'default' => $section['default'],
            ],
            self::for($username),
        );
    }

    /**
     * Every valid section key across the whole catalog. Used to reject unknown
     * keys in the update request regardless of which project is being edited.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('home_sections.sections', []));
    }

    /**
     * Resolve every master section to a boolean for the public read endpoint.
     * Resolution order per key: stored `home_sections.{key}` -> legacy path
     * (four originals only) -> catalog default. Iterates the full master set so
     * any wired section always finds its key with the right default, even if a
     * project's list omits it.
     *
     * @param  array<string, mixed>  $websiteSettings  the project's `website_settings` block
     * @return array<string, bool>
     */
    public static function resolveAll(array $websiteSettings): array
    {
        $stored = data_get($websiteSettings, 'home_sections', []);
        $resolved = [];

        foreach (config('home_sections.sections', []) as $key => $definition) {
            $legacy = $definition['legacy_path'] ?? null;

            $value = $stored[$key]
                ?? ($legacy !== null ? data_get($websiteSettings, $legacy) : null)
                ?? ($definition['default'] ?? true);

            $resolved[$key] = (bool) $value;
        }

        return $resolved;
    }
}
