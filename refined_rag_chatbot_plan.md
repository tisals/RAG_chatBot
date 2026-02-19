# Refined RAG Chatbot Plugin Plan

## HU-001: Parametrizable Chatbot Prompt
Descripion: Allow administrators to customize the chatbot's prompt template from the plugin settings.
Acceptance Criteria:
- New setting 'chat_prompt_template' stored in 'wp_rag_settings'.
- Admin UI with a textarea for prompt customization.
- Dynamic prompt loading in API calls.

## HU-002: Bulk FAQ Upload
Description: Enable importing FAQs from a CSV or XLSX file in the FAQ management section.
Acceptance Criteria:
- Upload button in the FAQ CRUD interface.
- Validation of file structure (columns: question, answer, category, source).
- Background processing with error reporting.

## HU-003: Source Tracking and Advanced Filters
Description: Add a 'source' column to track where answers come from and enable advanced filtering in the frontend.
Acceptance Criteria:
- New 'source' column in 'wp_rag_faqs'.
- Public tab with filters for category, date range, and unanswered questions.
- REST API endpoint for filtered searches.

## HU-004: Default Contact Page Redirection
Description: Automatically redirect users to a contact page when no answer is found.
Acceptance Criteria:
- Setting for 'fallback_page_id' in plugin options.
- Client-side redirection when no relevant answer is returned.

## HU-005: Elementor JSON Template Export
Description: Generate and download an Elementor-compatible JSON template for the plugin's support page.
Acceptance Criteria:
- Export button in admin settings.
- JSON file with Elementor structure and plugin shortcodes.
- README with import instructions.

## HU-006: Flexible Multi-API Support
Description: Allow configuration of multiple APIs and selection of the active one.
Acceptance Criteria:
- 'wp_rag_apis' table for storing API configurations.
- Admin CRUD for managing APIs.
- Selection dropdown for active API.

## HU-007: Webhook Integration (Make/n8n)
Description: Configure webhooks to send events to automation platforms like Make or n8n.
Acceptance Criteria:
- Settings for 'webhook_url' and event triggers.
- New admin tab for webhook configuration.
- Secure POST requests with retry logic.

## HU-008: Cross-Site Reusability
Description: Ensure the plugin works across different themes and multisite installations.
Acceptance Criteria:
- Theme-independent styling and functionality.
- Hooks and filters for customization.
- Multisite activation compatibility.

## HU-009: Python Scraper Integration
Description: Validate and enable integration with the provided Python scraping script.
Acceptance Criteria:
- Secure handling of JSON output from scraper.
- REST endpoint for importing scraped data.
- Documentation for running the script externally.

## HU-010: Query Auditing and Logs
Description: Log all user queries, sources, and responses for analytics.
Acceptance Criteria:
- 'wp_rag_logs' table for storing query data.
- Admin dashboard with analytics views.
- Export functionality for logs.
