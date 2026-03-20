<?php

namespace App\Agents;

use App\Ai\Tools\DatabaseQueryTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Middleware\RememberConversation;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5-20251001')]
#[MaxTokens(8192)]
#[MaxSteps(5)]
#[Timeout(120)]
class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function __construct(
        protected bool $canQueryDatabase = false,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        $base = <<<'PROMPT'
        You are a helpful AI assistant for PM One, a project management and event management platform.

        Guidelines:
        - Respond in the same language the user uses
        - Format responses using markdown when appropriate
        - Be concise but thorough
        - For code snippets, use fenced code blocks with language identifiers
        - If you don't know something, say so honestly
        PROMPT;

        if ($this->canQueryDatabase) {
            $base .= "\n\n".$this->databaseContext();
        }

        return $base;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        if ($this->canQueryDatabase) {
            return [new DatabaseQueryTool];
        }

        return [];
    }

    /**
     * Get the middleware for the agent.
     *
     * @return array<int, class-string>
     */
    public function middleware(): array
    {
        return [
            RememberConversation::class,
        ];
    }

    /**
     * Get database schema context for the system prompt.
     */
    protected function databaseContext(): string
    {
        return <<<'SCHEMA'
        You have access to a PostgreSQL database via the DatabaseQueryTool. Use it to answer data questions.

        Key tables and their purposes:
        - users: System users (id, name, username, email, email_verified_at, status, created_at)
        - projects: Project containers (id, name, username, status, visibility)
        - events: Events within projects (id, project_id, title, slug, start_date, end_date, location, status, is_active)
        - brands: Companies/exhibitors (id, name, slug, company_name, status)
        - brand_event: Brand-event pivot (id, brand_id, event_id, booth_number, booth_size, booth_type, booth_price, status)
        - brand_user: Brand-user pivot (brand_id, user_id, role)
        - orders: Booth orders (id, brand_event_id, order_number, operational_status, payment_status, subtotal, total, submitted_at)
        - order_items: Order line items (id, order_id, event_product_id, product_name, unit_price, quantity, total_price)
        - event_products: Products for events (id, event_id, name, price, unit, is_active)
        - event_product_categories: Product categories (id, event_id, title)
        - posts: Blog posts (id, title, slug, status, visibility, published_at, featured)
        - post_authors: Post-author pivot (post_id, user_id)
        - contacts: CRM contacts (id, name, job_title, emails, company_name, status, source)
        - contact_project: Contact-project pivot (contact_id, project_id)
        - contact_form_submissions: Form submissions (id, project_id, form_data, subject, status)
        - tasks: Task management (id, title, status, priority, assignee_id, project_id)
        - short_links: URL shortener (id, slug, destination_url, is_active)
        - clicks: Click tracking (id, clickable_type, clickable_id, ip_address, clicked_at)
        - visits: Visit tracking (id, visitable_type, visitable_id, visited_at)
        - tags: Spatie tags (id, name, type) - type can be 'category' or null
        - taggables: Tag pivot (tag_id, taggable_type, taggable_id)
        - roles: User roles (id, name)
        - model_has_roles: Role assignments (role_id, model_type, model_id)
        - forms: Form builder (id, title, slug, status, project_id)
        - form_fields: Form field definitions (id, form_id, type, label)
        - form_responses: Form submissions (id, form_id, response_data, status)
        - promotion_posts: Brand promotion content (id, brand_event_id, caption)
        - event_documents: Required exhibitor documents (id, event_id, title, document_type)
        - event_document_submissions: Document submissions from exhibitors
        - link_pages: Link collection pages (id, user_id, slug, title)
        - ga_properties: Google Analytics properties (id, project_id, property_id)

        Important notes:
        - This is PostgreSQL, use ILIKE for case-insensitive search
        - JSON columns (emails, phones, settings, custom_fields) are jsonb type
        - Most tables have soft deletes (deleted_at) - filter with WHERE deleted_at IS NULL
        - Always use LIMIT (max 50 rows) in queries
        - Use COUNT(*) for counting records
        - Present results in a clear, readable format using markdown tables when appropriate
        SCHEMA;
    }
}
