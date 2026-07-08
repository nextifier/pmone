<?php

namespace App\Support;

class FormTemplates
{
    /**
     * Ready-to-use form definitions shared by the create-from-template
     * endpoint and ExampleFormsSeeder. Each entry: title, description,
     * settings, fields (CustomField attribute arrays, in display order).
     *
     * @return array<string, array{title: string, description: string, settings: array, fields: array}>
     */
    public static function all(): array
    {
        return [
            'event-registration' => [
                'title' => 'Event Registration',
                'description' => '<p>Register your attendance for the upcoming exhibition. Fields marked with * are required.</p>',
                'settings' => [
                    'confirmation_message' => 'You are registered! We will email your e-ticket shortly.',
                    'require_email' => true,
                    'prevent_duplicate' => true,
                    'prevent_duplicate_by' => 'email',
                ],
                'fields' => [
                    ['type' => 'text', 'label' => 'Full Name', 'placeholder' => 'Your full name', 'validation' => ['required' => true, 'max' => 100]],
                    ['type' => 'email', 'label' => 'Work Email', 'placeholder' => 'name@company.com', 'help_text' => 'Your e-ticket will be sent to this address.', 'validation' => ['required' => true]],
                    ['type' => 'phone', 'label' => 'Phone Number', 'validation' => ['required' => true]],
                    ['type' => 'country', 'label' => 'Country of Residence', 'validation' => ['required' => true]],
                    ['type' => 'select', 'label' => 'Ticket Type', 'placeholder' => 'Choose your ticket', 'validation' => ['required' => true], 'options' => [
                        ['value' => 'visitor', 'label' => 'Visitor (Free)'],
                        ['value' => 'delegate', 'label' => 'Delegate'],
                        ['value' => 'vip', 'label' => 'VIP'],
                    ]],
                    ['type' => 'date', 'label' => 'Planned Visit Date', 'validation' => ['required' => true]],
                    ['type' => 'checkbox_group', 'label' => 'Sessions You Want to Attend', 'validation' => ['max_selections' => 3], 'help_text' => 'Pick up to 3 sessions.', 'options' => [
                        ['value' => 'keynote', 'label' => 'Opening Keynote'],
                        ['value' => 'workshop', 'label' => 'Hands-on Workshop'],
                        ['value' => 'networking', 'label' => 'Networking Night'],
                        ['value' => 'expo-tour', 'label' => 'Guided Expo Tour'],
                    ]],
                    ['type' => 'switch', 'label' => 'Newsletter', 'placeholder' => 'Send me event updates and offers'],
                    ['type' => 'checkbox', 'label' => 'Terms & Conditions', 'placeholder' => 'I agree to the terms and privacy policy', 'validation' => ['required' => true]],
                ],
            ],
            'customer-feedback-survey' => [
                'title' => 'Customer Feedback Survey',
                'description' => '<p>Tell us about your experience. Your feedback helps us improve.</p>',
                'settings' => [
                    'confirmation_message' => 'Thanks for the feedback! We read every response.',
                ],
                'fields' => [
                    ['type' => 'section', 'label' => 'Your Experience', 'settings' => ['description' => '<p>A few quick questions about your most recent visit.</p>']],
                    ['type' => 'rating', 'label' => 'Overall Rating', 'validation' => ['required' => true], 'settings' => ['max' => 5]],
                    ['type' => 'linear_scale', 'label' => 'How likely are you to recommend us?', 'validation' => ['required' => true, 'min' => 1, 'max' => 10], 'settings' => ['min_label' => 'Not likely', 'max_label' => 'Very likely']],
                    ['type' => 'radio', 'label' => 'How did you hear about us?', 'options' => [
                        ['value' => 'social-media', 'label' => 'Social Media'],
                        ['value' => 'friend', 'label' => 'Friend or Colleague'],
                        ['value' => 'search', 'label' => 'Search Engine'],
                        ['value' => 'other', 'label' => 'Other'],
                    ]],
                    ['type' => 'multi_select', 'label' => 'What did you enjoy most?', 'placeholder' => 'Select all that apply', 'options' => [
                        ['value' => 'exhibitors', 'label' => 'Exhibitor Lineup'],
                        ['value' => 'talks', 'label' => 'Talks & Seminars'],
                        ['value' => 'venue', 'label' => 'Venue & Facilities'],
                        ['value' => 'food', 'label' => 'Food & Beverages'],
                        ['value' => 'pricing', 'label' => 'Ticket Pricing'],
                    ]],
                    ['type' => 'slider', 'label' => 'Value for Money (0-100)', 'validation' => ['min' => 0, 'max' => 100], 'settings' => ['step' => 5]],
                    ['type' => 'textarea', 'label' => 'Suggestions', 'placeholder' => 'What can we do better next time?', 'validation' => ['max' => 1000]],
                ],
            ],
            'job-application' => [
                'title' => 'Job Application',
                'description' => '<p>Apply to join our team. We review applications every Friday.</p>',
                'settings' => [
                    'confirmation_message' => 'Application received. We will get back to you within two weeks.',
                    'require_email' => true,
                ],
                'fields' => [
                    ['type' => 'section', 'label' => 'Personal Information'],
                    ['type' => 'text', 'label' => 'Full Name', 'validation' => ['required' => true, 'max' => 100]],
                    ['type' => 'email', 'label' => 'Email Address', 'validation' => ['required' => true]],
                    ['type' => 'phone', 'label' => 'WhatsApp Number', 'validation' => ['required' => true]],
                    ['type' => 'url', 'label' => 'Portfolio or LinkedIn', 'help_text' => 'A link that shows your best work.'],
                    ['type' => 'section', 'label' => 'Application', 'settings' => ['description' => '<p>Tell us why you are a great fit.</p>']],
                    ['type' => 'rich_text', 'label' => 'Cover Letter', 'placeholder' => 'Introduce yourself and explain why you want this role', 'validation' => ['required' => true]],
                    ['type' => 'tags', 'label' => 'Skills', 'placeholder' => 'Type a skill and press Enter', 'validation' => ['max_selections' => 8], 'help_text' => 'Up to 8 skills.'],
                    ['type' => 'number', 'label' => 'Expected Salary (IDR, millions)', 'placeholder' => 'e.g. 15', 'validation' => ['min' => 1, 'max' => 200]],
                    ['type' => 'date', 'label' => 'Available From', 'validation' => ['required' => true]],
                    ['type' => 'file', 'label' => 'CV / Resume', 'validation' => ['required' => false, 'max_file_size' => 5120, 'allowed_file_types' => ['pdf', 'doc', 'docx']], 'help_text' => 'PDF preferred, max 5 MB.', 'settings' => ['multiple' => false]],
                ],
            ],
            'vendor-application' => [
                'title' => 'Vendor Application',
                'description' => '<p>Apply to become a vendor at our next event. Our partnership team will review your submission.</p>',
                'settings' => [
                    'confirmation_message' => 'Thanks! Our partnership team will contact you soon.',
                ],
                'fields' => [
                    ['type' => 'text', 'label' => 'Company Name', 'validation' => ['required' => true, 'max' => 150]],
                    ['type' => 'country', 'label' => 'Company Headquarters', 'validation' => ['required' => true]],
                    ['type' => 'url', 'label' => 'Company Website'],
                    ['type' => 'multi_select', 'label' => 'Product Categories', 'validation' => ['required' => true, 'min_selections' => 1, 'max_selections' => 3], 'options' => [
                        ['value' => 'food-beverage', 'label' => 'Food & Beverage'],
                        ['value' => 'fashion', 'label' => 'Fashion & Apparel'],
                        ['value' => 'technology', 'label' => 'Technology'],
                        ['value' => 'crafts', 'label' => 'Crafts & Homeware'],
                        ['value' => 'services', 'label' => 'Professional Services'],
                    ]],
                    ['type' => 'date_range', 'label' => 'Booth Availability', 'validation' => ['required' => true], 'help_text' => 'The dates your team can run the booth.'],
                    ['type' => 'datetime', 'label' => 'Preferred Meeting Slot', 'help_text' => 'When can our team call you?'],
                    ['type' => 'color', 'label' => 'Primary Brand Color', 'help_text' => 'Used for your booth signage mockup.'],
                    ['type' => 'file', 'label' => 'Company Deck & Catalogs', 'validation' => ['max_file_size' => 10240, 'max_files' => 3, 'allowed_file_types' => ['pdf', 'ppt', 'pptx']], 'settings' => ['multiple' => true], 'help_text' => 'Up to 3 files, 10 MB each.'],
                    ['type' => 'textarea', 'label' => 'Anything else we should know?', 'validation' => ['max' => 1000]],
                ],
            ],
            'field-showcase' => [
                'title' => 'Field Showcase',
                'description' => '<p>A demo form featuring every available field type. Open it on the public page to try each input.</p>',
                'settings' => [
                    'confirmation_message' => 'Submission received. Feel free to submit again to keep testing!',
                ],
                'fields' => [
                    ['type' => 'section', 'label' => 'Text Inputs', 'settings' => ['description' => '<p>Single line, multi line, and rich text answers.</p>']],
                    ['type' => 'text', 'label' => 'Short Text', 'placeholder' => 'A short answer'],
                    ['type' => 'textarea', 'label' => 'Long Text', 'placeholder' => 'A longer answer', 'validation' => ['max' => 500]],
                    ['type' => 'rich_text', 'label' => 'Rich Text', 'help_text' => 'Supports bold, italics, lists, and links.'],
                    ['type' => 'email', 'label' => 'Email'],
                    ['type' => 'phone', 'label' => 'Phone'],
                    ['type' => 'url', 'label' => 'Link'],
                    ['type' => 'section', 'label' => 'Choices'],
                    ['type' => 'select', 'label' => 'Dropdown', 'options' => [
                        ['value' => 'small', 'label' => 'Small'],
                        ['value' => 'medium', 'label' => 'Medium'],
                        ['value' => 'large', 'label' => 'Large'],
                    ]],
                    ['type' => 'multi_select', 'label' => 'Multi Select', 'options' => [
                        ['value' => 'design', 'label' => 'Design'],
                        ['value' => 'development', 'label' => 'Development'],
                        ['value' => 'marketing', 'label' => 'Marketing'],
                        ['value' => 'sales', 'label' => 'Sales'],
                    ]],
                    ['type' => 'radio', 'label' => 'Radio', 'options' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'no', 'label' => 'No'],
                        ['value' => 'maybe', 'label' => 'Maybe'],
                    ]],
                    ['type' => 'checkbox', 'label' => 'Single Checkbox', 'placeholder' => 'Check me'],
                    ['type' => 'checkbox_group', 'label' => 'Checkbox Group', 'options' => [
                        ['value' => 'breakfast', 'label' => 'Breakfast'],
                        ['value' => 'lunch', 'label' => 'Lunch'],
                        ['value' => 'dinner', 'label' => 'Dinner'],
                    ]],
                    ['type' => 'switch', 'label' => 'Switch', 'placeholder' => 'Toggle me on'],
                    ['type' => 'tags', 'label' => 'Tags', 'validation' => ['max_selections' => 5]],
                    ['type' => 'country', 'label' => 'Country'],
                    ['type' => 'section', 'label' => 'Date & Time'],
                    ['type' => 'date', 'label' => 'Date'],
                    ['type' => 'time', 'label' => 'Time'],
                    ['type' => 'datetime', 'label' => 'Date & Time'],
                    ['type' => 'date_range', 'label' => 'Date Range'],
                    ['type' => 'section', 'label' => 'Numbers & Scales'],
                    ['type' => 'number', 'label' => 'Number', 'validation' => ['min' => 0, 'max' => 1000]],
                    ['type' => 'slider', 'label' => 'Slider', 'validation' => ['min' => 0, 'max' => 10], 'settings' => ['step' => 1]],
                    ['type' => 'rating', 'label' => 'Rating', 'settings' => ['max' => 5]],
                    ['type' => 'linear_scale', 'label' => 'Linear Scale', 'validation' => ['min' => 1, 'max' => 7], 'settings' => ['min_label' => 'Low', 'max_label' => 'High']],
                    ['type' => 'section', 'label' => 'Special'],
                    ['type' => 'file', 'label' => 'File Upload', 'validation' => ['max_file_size' => 5120], 'settings' => ['multiple' => false]],
                    ['type' => 'color', 'label' => 'Favorite Color'],
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
