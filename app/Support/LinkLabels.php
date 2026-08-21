<?php

namespace App\Support;

final class LinkLabels
{
    /**
     * The link labels the editors offer, in the order they are offered. Kept in
     * one place because the same list drives the brand form, the import
     * template, and the fixed link columns in the Google Sheets feed - and a
     * sheet whose columns disagree with the editor is worse than no sheet.
     *
     * Labels outside this list are still allowed: the editor has a "Custom"
     * option, so consumers must treat this as the canonical head of the list,
     * not the whole of it.
     *
     * @var array<int, string>
     */
    public const PREDEFINED = ['Website', 'Instagram', 'Facebook', 'X', 'TikTok', 'LinkedIn', 'YouTube'];
}
