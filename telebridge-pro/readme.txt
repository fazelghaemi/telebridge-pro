Telebridge Pro - Complete Development Specification
🎯 Project Overview
Telebridge Pro is an advanced WordPress plugin system that automatically transfers AI prompts from Telegram to WooCommerce as products, with intelligent categorization and AI-powered content generation.
Company: Ready Studio (readystudio.ir)
Version: 6.0.0
Status: Production Ready

🏗️ Architecture Overview
Three-Plugin System:

Telebridge Pro (Main)

Telegram webhook receiver
GapGPT integration for content generation
Product creation with auto-tagging
Admin settings panel


Telebridge Auto Categorization (Addon)

Intelligent prompt type detection
AI-powered site relevance scoring
Automatic AI site selection
Works with GapGPT (gpt-4o-mini model)


AI Sites Manager Ultimate (Existing)

Manages 27+ predefined AI platforms
6 categorized site types (Text, Image, Video, Audio, Code, Tools)
Custom site management
Frontend display (shortcode + WooCommerce tab)




📋 Complete Feature List
🔌 Plugin 1: Telebridge Pro
Core Features:

✅ Telegram Webhook Integration

Real-time post receiving from Telegram channel
Automatic image downloading from Telegram
Caption extraction as prompt text
Channel ID security verification


✅ AI Content Generation (GapGPT)

Model: gpt-4o-mini (selectable: gpt-4, gpt-3.5-turbo)
Auto-generates Persian product titles (max 8 words)
Auto-generates English product titles (max 5 words)
Auto-generates descriptions (2-3 lines, detailed & SEO-optimized)
Stores raw Telegram caption in meta


✅ Automatic Tag Generation

Creates exactly 2 tags per product
Tags are Persian, SEO-friendly, prompt-specific
Uses AI to analyze and categorize content


✅ Product Creation

Posts WooCommerce products with "pending" status (for manual review)
Sets featured image automatically
Stores meta data (prompt, English title, author)
Assigns to default category
Sets as virtual product
Stores price (configurable)


✅ Custom Meta Fields (JetEngine Compatible)

prompt-text: Raw Telegram caption
latin-name-product: English product title
idea-owner: Always set to "readystudio"
_telebridge_auto_scores: AI categorization scores (debug)
_telebridge_needs_review: Manual review flag



Admin Features:

✅ Settings Dashboard

Telegram Bot Token input (password field)
Channel ID configuration
GapGPT API Key input (password field)
AI Model selection dropdown
Default product price
Default category selection
Publication status (pending/draft/publish)
Webhook URL display & copy button


✅ Webhook Management

One-click webhook setup button
Automatic Telegram webhook registration
Webhook URL display with copy functionality
Connection status feedback


✅ Admin UI/UX

Material Design 3 inspired
RTL support (Persian)
Responsive design (desktop/tablet/mobile)
Form validation (real-time & on-submit)
Loading states & animations
Success/Error message notifications
Dark mode support
Accessibility features (focus management, keyboard navigation)



Technical Features:

✅ Security

CSRF protection (nonce verification)
WordPress permission checks (manage_options)
Telegram channel verification
Token format validation
URL sanitization & validation
Input sanitization (sanitize_text_field, esc_url_raw)


✅ Error Handling

Comprehensive error logging to debug.log
Graceful fallbacks (if AI fails, uses prompt excerpt)
HTTP error detection & reporting
Connection timeout handling
JSON parsing validation


✅ Performance

REST API endpoint for webhook
Asynchronous image processing
Action hook for plugin integration
Efficient database queries




🔌 Plugin 2: Telebridge Auto Categorization
Core Features:

✅ Automatic Prompt Type Detection

Types: image, video, audio, text, code, tool
Uses GapGPT for intelligent analysis
Fallback to 'tool' if detection fails


✅ AI-Powered Site Scoring

Analyzes prompt against 27+ AI platforms
Scores relevance 0-100 for each site
Returns top 4 most relevant sites
Stores detailed scores in meta


✅ Smart Site Filtering

Direct category matching (highest priority)
Related category matching (secondary)
Dynamic filtering based on type


✅ Integration with AI Sites Manager

Auto-populates _ai_sites_checked meta
Checkboxes are pre-selected in product editor
Visual badge shows "auto-selected" items
Enables auto-display on product page



Dependency Management:

✅ Plugin Requirements Check

Verifies Telebridge Pro is active
Verifies AI Sites Manager Ultimate is active
Shows admin notices if missing
Gracefully disabled without dependencies



Customization:

✅ Filters & Hooks

telebridge_auto_cat_max_sites - Change max selected sites
Logging system with different levels (success, error, warning, info)




🔌 Plugin 3: AI Sites Manager Ultimate
Core Features:

✅ Predefined AI Sites (27 platforms)

Text (7): ChatGPT, Claude, Gemini, Copilot, Perplexity, Mistral, Poe
Image (6): Midjourney, DALL-E 3, Leonardo, Firefly, FLUX.1, Ideogram
Video (6): Runway, Pika, Sora, Kling, Luma, HeyGen
Audio (3): Suno, Udio, ElevenLabs
Code (4): GitHub Copilot, Cursor, Replit, Tabnine
Tools (3): Notion AI, Gamma, ChatPDF


✅ Category System

6 predefined categories with custom SVG icons
Per-category grouping in UI
Automatic icon display on frontend


✅ Meta Box Editor

Checkbox-based UI with grouping
Real-time search across platforms
Auto-selected sites show visual badge
Add custom sites (existing + new)
Set per-product or global
Category assignment for custom sites


✅ Custom Site Management

Add unlimited custom platforms
Per-product custom sites
Global custom sites (available to all products)
Category assignment for custom
Delete functionality with confirmation



Frontend Display:

✅ WooCommerce Integration

New "سازگاری با هوش مصنوعی" tab on product page
Only shows if auto-display is enabled
Professional chip-style design


✅ Shortcode Support

[ai_sites] shortcode
Customizable title parameter
Works on any page/post


✅ Frontend Design

Pill/chip-style links with icons
Smooth hover animations
Icon + name + external link icon
Responsive grid layout
RTL support
Dark mode compatible



Technical:

✅ Data Storage

Post meta for checked sites: _ai_sites_checked
Post meta for custom sites: _ai_sites_custom
Post meta for auto-display: _ai_sites_auto_display
Global option for custom sites: ai_sites_pro_global_db


✅ Security

Nonce verification on save
Permission checks (edit_post)
Autosave prevention
Input sanitization




🎨 Design & UI Features
Admin Panel (Telebridge Pro):

Material Design 3 aesthetic
Color scheme: #1a73e8 (primary), #00b0a4 (accent)
Responsive grid layout (2 columns on desktop, 1 on mobile)
Smooth transitions & animations
Form validation with visual feedback
Success/Error message notifications
Dark mode support
Accessibility compliant (WCAG)

Frontend (AI Sites Manager):

Pill-shaped chip design
Icon + text + external link
Hover animations (scale, color change)
Responsive flex layout
RTL-ready
Mobile-optimized spacing


🔧 API & Integration Points
Action Hooks:
php// Fired when product is created from Telegram
do_action('rti_product_created', $product_id, $raw_prompt, $ai_key, $model);
Filters:
php// Customize AI site list
apply_filters('ai_sites_ultimate_list', $full_list);

// Customize max auto-selected sites
apply_filters('telebridge_auto_cat_max_sites', 4);
REST Endpoints:
POST /wp-json/telebridge/v1/webhook
AJAX Actions:
telebridge_set_webhook (admin-ajax.php)

💾 Database Schema
Post Meta:

_price - Product price
_regular_price - Regular price
_virtual - Virtual product flag
prompt-text - Raw Telegram caption
latin-name-product - English title
idea-owner - Always "readystudio"
_ai_sites_checked - Selected site keys (array)
_ai_sites_custom - Custom sites (array)
_ai_sites_auto_display - Show tab on product (yes/no)
_telebridge_auto_scores - AI scoring data (array)
_telebridge_needs_review - Review flag (yes/no)

Options:

telebridge_pro_settings - Main plugin settings
ai_sites_pro_global_db - Global custom sites


🔐 Security Features

CSRF protection (wp_nonce)
Permission verification (current_user_can)
Input sanitization (sanitize_text_field, esc_url_raw)
Output escaping (esc_html, esc_attr, esc_url)
Telegram channel ID verification
API token format validation
SSL certificate validation (with fallback option)
Escape all user output
Proper REST API permissions


📱 Responsive Design
Breakpoints:

Desktop: 1024px+
Tablet: 768px - 1024px
Mobile: 480px - 768px
Small Phone: < 480px

Mobile Optimizations:

Touch-friendly button sizes (40px+)
Font size 16px in inputs (prevents iOS zoom)
Full-width layout on small screens
Stacked form fields
Optimized spacing


🌐 Internationalization

Text Domain: telebridge-pro, ai-sites-ultimate
Full Persian (fa_IR) support
RTL layout support
All strings wrapped in __() or _e()
Proper locale handling


⚙️ Configuration
Telebridge Pro Settings:
php[
    'bot_token' => '123456:ABC...',
    'channel_id' => '@channel_name',
    'gapgpt_key' => 'sk-...',
    'ai_model' => 'gpt-4o-mini',
    'price' => '10000',
    'cat' => '5',
    'status' => 'pending'
]
Supported Models:

gpt-4o-mini (default, recommended)
gpt-4
gpt-3.5-turbo


📊 Workflow
1. Telegram Post → Sent to Webhook
2. Telebridge Pro → Receives & Processes
3. Image Download → Stored in WordPress
4. GapGPT Call → Generate Title + Description
5. Product Creation → Posted as "pending"
6. Tag Generation → 2 Persian Tags
7. Action Hook Fired → rti_product_created
8. Auto Categorization → Detects Type + Scores Sites
9. AI Sites → Checkboxes Auto-Selected
10. Admin Review → Approve/Edit/Publish

🐛 Error Handling & Logging
Log Levels:

success: Green - Operation successful
error: Red - Operation failed
warning: Yellow - Potential issues
info: Blue - General information

Log Location:
/wp-content/debug.log
Debug Prefix:
[Telebridge Auto Cat success] Message
[Telebridge HTTP 200] Response details

🚀 Deployment
Requirements:

WordPress 5.0+
PHP 7.4+
WooCommerce (for product creation)
cURL enabled
REST API enabled

Installation:

Upload plugin files to /wp-content/plugins/
Activate all three plugins in WordPress
Configure Telebridge Pro settings
Click "اتصال خودکار به تلگرام"
Done!


📞 Support & Maintenance
Debugging:

Enable WP_DEBUG in wp-config.php
Check /wp-content/debug.log
Verify Telegram token format
Check webhook URL accessibility
Verify GapGPT API key

Troubleshooting:

Token validation with regex pattern
Webhook URL validation
HTTP error code detection
SSL certificate validation
JSON response validation


🎁 Bonus Features

✅ Keyboard shortcuts (Ctrl+Enter to submit)
✅ Form state persistence (sessionStorage)
✅ Clipboard copy with visual feedback
✅ Auto-dismiss notifications (5 seconds)
✅ Unsaved changes warning
✅ Smooth animations throughout
✅ Console logging with colors
✅ Debounce & Throttle utilities
✅ Form state recovery dialog


📈 Future Enhancements
Potential additions:

 Admin dashboard with statistics
 Bulk import from Telegram
 Custom AI models support
 Multiple channel support
 Advanced filtering options
 Export/Import functionality
 Webhook history log
 Performance analytics


📝 File Structure
telebridge-pro/
├── telebridge-pro.php (اصلی)
├── assets/
│   ├── admin-style.css
│   └── admin-script.js
└── readme.txt

telebridge-auto-categorization/
├── telebridge-auto-categorization.php
└── readme.txt

ai-sites-manager-ultimate/
├── ai-sites-manager-ultimate.php
└── readme.txt

🎯 Summary
Telebridge Pro is a complete, production-ready system that:

Automates AI prompt transfer from Telegram to WooCommerce
Intelligently generates product content with AI
Automatically categorizes products by AI platform compatibility
Provides an intuitive, modern admin interface
Maintains enterprise-level security
Delivers excellent user experience on all devices
Integrates seamlessly with WordPress ecosystem

Total Lines of Code: ~3,500+
Complexity Level: Advanced
Maintenance Level: Low
Security Level: Enterprise-Grade