<?php
// c:\projects\apache\school1\src\lekhak\generate_sppux_ui.php

$comp_dir = __DIR__ . '/comp';

$modules = [
    'lekhak_forum' => ['title' => 'Forum', 'icon' => '💬', 'desc' => 'Manage community discussions and topics', 'metrics' => ['Active Topics: 45', 'Posts Today: 120']],
    'lekhak_community' => ['title' => 'Community', 'icon' => '👥', 'desc' => 'Manage user profiles and social groups', 'metrics' => ['Members: 1,204', 'Groups: 15']],
    'lekhak_qa' => ['title' => 'Q&A', 'icon' => '❓', 'desc' => 'Manage Questions and Answers', 'metrics' => ['Questions: 340', 'Answers: 890']],
    'lekhak_newsletter' => ['title' => 'Newsletters', 'icon' => '✉️', 'desc' => 'Manage email campaigns and subscribers', 'metrics' => ['Subscribers: 5,600', 'Open Rate: 24%']],
    'lekhak_popups' => ['title' => 'Popups', 'icon' => '💥', 'desc' => 'Manage lead capture and popups', 'metrics' => ['Active Campaigns: 3', 'Conversions: 450']],
    'lekhak_academy' => ['title' => 'Academy LMS', 'icon' => '🎓', 'desc' => 'Manage courses, lessons, and students', 'metrics' => ['Active Courses: 12', 'Enrolled Students: 340']],
    'lekhak_helpdesk' => ['title' => 'Helpdesk', 'icon' => '🎫', 'desc' => 'Manage support tickets', 'metrics' => ['Open Tickets: 5', 'Resolved: 1,200']],
    'lekhak_events' => ['title' => 'Events', 'icon' => '📅', 'desc' => 'Manage event calendar and bookings', 'metrics' => ['Upcoming Events: 4', 'Bookings: 89']],
    'lekhak_classifieds' => ['title' => 'Classifieds', 'icon' => '🏷️', 'desc' => 'Manage classified ads', 'metrics' => ['Active Ads: 450', 'Categories: 12']],
    'lekhak_realestate' => ['title' => 'Real Estate', 'icon' => '🏠', 'desc' => 'Manage property listings', 'metrics' => ['Properties: 85', 'Agents: 14']],
    'lekhak_healthcare' => ['title' => 'Healthcare', 'icon' => '⚕️', 'desc' => 'Manage clinics and appointments', 'metrics' => ['Doctors: 24', 'Appointments: 150']],
    'lekhak_donations' => ['title' => 'Donations', 'icon' => '❤️', 'desc' => 'Manage fundraising campaigns', 'metrics' => ['Campaigns: 2', 'Raised: $4,500']],
    'lekhak_gallery' => ['title' => 'Gallery', 'icon' => '🖼️', 'desc' => 'Manage image and video galleries', 'metrics' => ['Albums: 34', 'Media Items: 1,200']],
    'lekhak_portfolio' => ['title' => 'Portfolio', 'icon' => '📁', 'desc' => 'Manage portfolio projects', 'metrics' => ['Projects: 18', 'Categories: 4']],
    'lekhak_documents' => ['title' => 'Documents', 'icon' => '📄', 'desc' => 'Manage secure file downloads', 'metrics' => ['Files: 450', 'Downloads: 3,400']],
    'lekhak_widgets' => ['title' => 'Widgets', 'icon' => '🧩', 'desc' => 'Manage sliders, accordions, and maps', 'metrics' => ['Active Widgets: 24']],
    'lekhak_lightbox' => ['title' => 'Lightbox', 'icon' => '🔎', 'desc' => 'Manage media overlay settings', 'metrics' => ['Configuration active']],
    'lekhak_subscriptions' => ['title' => 'Subscriptions', 'icon' => '💳', 'desc' => 'Manage recurring billing and plans', 'metrics' => ['Active Subs: 340', 'MRR: $4,500']],
    'lekhak_memberships' => ['title' => 'Memberships', 'icon' => '🔑', 'desc' => 'Manage access control groups', 'metrics' => ['Groups: 5', 'Protected Pages: 45']],
    'lekhak_backend_shield' => ['title' => 'Backend Shield', 'icon' => '🛡️', 'desc' => 'Stealth login URL protection', 'metrics' => ['Status: Active', 'Blocks: 124']],
    'lekhak_journal' => ['title' => 'Journal', 'icon' => '✒️', 'desc' => 'Advanced blogging and authoring', 'metrics' => ['Published: 1,200', 'Drafts: 15']],
    'lekhak_reviews' => ['title' => 'Reviews', 'icon' => '⭐', 'desc' => 'Manage ratings and reviews', 'metrics' => ['Pending: 12', 'Approved: 3,400']],
    'lekhak_glossary' => ['title' => 'Glossary', 'icon' => '📖', 'desc' => 'Manage tooltip dictionary terms', 'metrics' => ['Terms: 450']],
    'lekhak_reading_time' => ['title' => 'Reading Time', 'icon' => '⏱️', 'desc' => 'Progress bar configuration', 'metrics' => ['Status: Active']],
    'lekhak_authors' => ['title' => 'Authors', 'icon' => '🧑‍💻', 'desc' => 'Manage multi-author profiles', 'metrics' => ['Authors: 12', 'Guest Posts: 4']],
    'lekhak_migrations' => ['title' => 'Migrations', 'icon' => '🚚', 'desc' => 'Manage data import/export', 'metrics' => ['Profiles: 3', 'Last Run: Today']],
    'lekhak_webhooks' => ['title' => 'Webhooks', 'icon' => '🪝', 'desc' => 'Manage API push events', 'metrics' => ['Endpoints: 4', 'Failed: 0']],
    'lekhak_ab_testing' => ['title' => 'A/B Testing', 'icon' => '⚖️', 'desc' => 'Manage split test campaigns', 'metrics' => ['Running: 2', 'Completed: 14']],
    'lekhak_audit_trail' => ['title' => 'Audit Trail', 'icon' => '🕵️', 'desc' => 'Review user action logs', 'metrics' => ['Log Size: 1.2MB', 'Events: 14,000']],
    'lekhak_pwa' => ['title' => 'PWA', 'icon' => '📱', 'desc' => 'Progressive Web App manifest', 'metrics' => ['Offline Cache: Active', 'Installs: 340']],
    'lekhak_pdf' => ['title' => 'PDF Generator', 'icon' => '📋', 'desc' => 'Configure PDF exports', 'metrics' => ['Generated: 1,200']],
    'lekhak_watermark' => ['title' => 'Watermarks', 'icon' => '©️', 'desc' => 'Configure image watermarks', 'metrics' => ['Rules: 2', 'Applied: 4,500']],
    'lekhak_affiliates' => ['title' => 'Affiliates', 'icon' => '🤝', 'desc' => 'Manage referral tracking', 'metrics' => ['Affiliates: 45', 'Pending Payouts: $1,200']],
    'lekhak_gdpr' => ['title' => 'GDPR', 'icon' => '🍪', 'desc' => 'Manage cookie consent', 'metrics' => ['Consent Given: 45,000', 'Rejected: 1,200']],
    'lekhak_search_pro' => ['title' => 'Search Pro', 'icon' => '🔍', 'desc' => 'Advanced faceted search settings', 'metrics' => ['Index Size: 45MB', 'Queries Today: 1,200']]
];

function camel_case($str) {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
}

foreach ($modules as $id => $data) {
    $class_name = camel_case($id);
    
    $html_metrics = '';
    foreach ($data['metrics'] as $m) {
        $html_metrics .= "<div style=\"background:var(--bg); border:1px solid var(--border); padding:1rem; border-radius:8px;\">$m</div>";
    }

    $content = <<<JS
import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta } from './lekhak-nav.js';

export default class {$class_name}Component extends BaseComponent {
    async onInit() {
        registerNavHandlers();
        setPageMeta('{$data['title']}', '{$data['desc']}');
    }

    render() {
        return `
        <div style="padding: 2.5rem 2rem; max-width: 1200px; margin: 0 auto; font-family: 'Inter', sans-serif;">
            <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; margin: 0; color: var(--text);">
                        <span style="font-size: 2.5rem; margin-right: 10px; vertical-align: middle;">{$data['icon']}</span>
                        {$data['title']}
                    </h1>
                    <p style="color: var(--text-dim); margin-top: 5px;">{$data['desc']}</p>
                </div>
                <button class="btn" style="background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;border:none;cursor:pointer;font-weight:600;">+ Create New</button>
            </header>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                $html_metrics
            </div>
            
            <div style="background:var(--glass-bg,#fff); border:1px solid var(--border); border-radius:12px; padding:3rem; text-align:center; color:var(--text-dim);">
                <span style="font-size:3rem; display:block; margin-bottom:1rem; opacity:0.5;">{$data['icon']}</span>
                <h3>Dashboard Initialized</h3>
                <p>The {$data['title']} engine is active. Configuration and data views will appear here.</p>
            </div>
        </div>
        `;
    }
}
JS;

    file_put_contents($comp_dir . '/' . $id . '.js', $content);
    echo "Generated UI Component: $id.js\n";
}

echo "Finished generating 35 SPPUX components!\n";
