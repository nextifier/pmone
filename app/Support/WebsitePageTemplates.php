<?php

namespace App\Support;

use App\Models\Project;
use App\Models\WebsitePage;
use Illuminate\Support\Facades\File;

/**
 * Loads the built-in English starting-point HTML for a legal/policy page and
 * interpolates project-specific placeholders. These templates are a manual
 * copy of the baked `<div v-else>` bodies in the pmone-events legal pages
 * (`layers/base/app/pages/{terms,privacy,event-policy,help-center,
 * ticket-terms-and-conditions,ticket-refund-and-return-policy}.vue`). They are
 * copy-paste siblings: if the baked legal copy ever changes, update both.
 *
 * The "Load default template" admin action pours the result into the editor as
 * an editable starting point - it does NOT change what renders on the live
 * site, which still fails open to the baked Vue copy when no override exists.
 */
class WebsitePageTemplates
{
    /**
     * Render the template for a page key with the project's identity/contact
     * values interpolated. Missing values become empty strings so no raw
     * `{placeholder}` token ever reaches the editor.
     */
    public static function render(Project $project, string $key): string
    {
        $path = resource_path("website-page-templates/{$key}.html");

        if (! in_array($key, WebsitePage::KEYS, true) || ! File::exists($path)) {
            return '';
        }

        $identity = data_get($project->settings, 'website_settings.site_config.identity', []);

        $replacements = [
            '{company_name}' => (string) (data_get($identity, 'company_name') ?? ''),
            '{company_address}' => (string) (data_get($identity, 'company_address') ?? ''),
            '{website_name}' => (string) ($project->name ?? ''),
            '{website_url}' => (string) ($project->websiteUrl() ?? ''),
            '{contact_email}' => (string) ($project->email ?? ''),
        ];

        return strtr(File::get($path), $replacements);
    }
}
