<?php
// c:\projects\apache\school1\src\lekhak\generate_sidebar.php

$blade_file = __DIR__ . '/resources/views/standalone-admin.blade.php';
$content = file_get_contents($blade_file);

// The categories mapping
$categories = [
    'Overview & Core' => [
        ['id' => 'dashboard', 'title' => 'Dashboard', 'icon' => '📊'],
        ['id' => 'sankhyaki', 'title' => 'Sankhyaki Analytics', 'icon' => '📈', 'route' => '/lekhak/admin/sankhyaki'],
        ['id' => 'content', 'title' => 'Content Manager', 'icon' => '📝'],
        ['id' => 'media', 'title' => 'Media Library', 'icon' => '🖼️']
    ],
    'Structure & Design' => [
        ['id' => 'structure', 'title' => 'Content Types', 'icon' => '🏗️'],
        ['id' => 'lekhak_query_builder', 'title' => 'Views Builder', 'icon' => '👁️'],
        ['id' => 'lekhak_blocks_nested', 'title' => 'Blocks & Layouts', 'icon' => '🧱'],
        ['id' => 'canvas', 'title' => 'Visual Canvas', 'icon' => '🎨']
    ],
    'Community & Audience' => [
        ['id' => 'lekhak_forum', 'title' => 'Community Forum', 'icon' => '💬'],
        ['id' => 'lekhak_community', 'title' => 'Social Profiles', 'icon' => '👥'],
        ['id' => 'lekhak_qa', 'title' => 'Questions & Answers', 'icon' => '❓'],
        ['id' => 'lekhak_newsletter', 'title' => 'Newsletters', 'icon' => '✉️'],
        ['id' => 'lekhak_popups', 'title' => 'Popups & Leads', 'icon' => '💥']
    ],
    'E-Commerce & Subscriptions' => [
        ['id' => 'lekhak_store', 'title' => 'eCommerce Store', 'icon' => '🛒'],
        ['id' => 'lekhak_subscriptions', 'title' => 'Subscriptions', 'icon' => '💳'],
        ['id' => 'lekhak_memberships', 'title' => 'Memberships', 'icon' => '🔑'],
        ['id' => 'lekhak_donations', 'title' => 'Donations', 'icon' => '❤️']
    ],
    'Education & Services' => [
        ['id' => 'lekhak_academy', 'title' => 'Academy LMS', 'icon' => '🎓'],
        ['id' => 'lekhak_helpdesk', 'title' => 'Helpdesk Tickets', 'icon' => '🎫'],
        ['id' => 'lekhak_events', 'title' => 'Events Calendar', 'icon' => '📅'],
        ['id' => 'lekhak_healthcare', 'title' => 'Healthcare', 'icon' => '⚕️']
    ],
    'Directories & Media' => [
        ['id' => 'lekhak_realestate', 'title' => 'Real Estate', 'icon' => '🏠'],
        ['id' => 'lekhak_classifieds', 'title' => 'Classified Ads', 'icon' => '🏷️'],
        ['id' => 'lekhak_reviews', 'title' => 'Reviews & Ratings', 'icon' => '⭐'],
        ['id' => 'lekhak_portfolio', 'title' => 'Portfolio', 'icon' => '📁'],
        ['id' => 'lekhak_gallery', 'title' => 'Media Gallery', 'icon' => '🖼️']
    ],
    'System & Utilities' => [
        ['id' => 'settings', 'title' => 'Themes & Settings', 'icon' => '🎨'],
        ['id' => 'users', 'title' => 'Users & Roles', 'icon' => '👤', 'route' => '/lekhak/admin/users'],
        ['id' => 'lekhak_security', 'title' => 'Security Firewall', 'icon' => '🛡️'],
        ['id' => 'lekhak_migrations', 'title' => 'Data Migrations', 'icon' => '🚚'],
        ['id' => 'lekhak_audit_trail', 'title' => 'Audit Trail', 'icon' => '🕵️']
    ]
];

$sidebar_html = <<<HTML
        <nav class="nav-list" id="sidebar-nav">
            <?php
                \$active_modules = [];
                try {
                    \$db = new \SPPMod\SPPDB\SPPDB();
                    // We also consider core non-module views as "active"
                    \$core_views = ['dashboard', 'content', 'media', 'structure', 'canvas', 'settings', 'users'];
                    \$res = \$db->execute_query("SELECT machine_name FROM lekhak_modules WHERE status = 1");
                    if (\$res && is_array(\$res)) {
                        \$active_modules = array_merge(\$core_views, array_column(\$res, 'machine_name'));
                    } else {
                        // Fallback: If table doesnt exist yet, enable all for demonstration
                        \$active_modules = \$core_views;
                        \$all_dirs = scandir(__DIR__ . '/../../modules/');
                        foreach (\$all_dirs as \$d) {
                            if (\$d !== '.' && \$d !== '..') \$active_modules[] = \$d;
                        }
                    }
                } catch (\Exception \$e) {
                    // Fallback on error
                }
            ?>
            <div style="padding: 0 1rem 1rem;">
                <input type="text" id="nav-search" placeholder="Search menus..." style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border); background: rgba(0,0,0,0.1); color: var(--text); outline: none;">
            </div>
HTML;

foreach ($categories as $cat => $items) {
    $sidebar_html .= "\n            <div class=\"nav-group\">\n";
    $sidebar_html .= "                <div class=\"nav-section-header\" style=\"font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;\" onclick=\"toggleNavGroup(this)\">\n";
    $sidebar_html .= "                    <span>$cat</span>\n";
    $sidebar_html .= "                    <span class=\"toggle-icon\" style=\"font-size: 0.6rem; transition: transform 0.2s;\">▼</span>\n";
    $sidebar_html .= "                </div>\n";
    $sidebar_html .= "                <div class=\"nav-group-content\">\n";

    foreach ($items as $item) {
        $sidebar_html .= "                    <?php if (in_array('{$item['id']}', \$active_modules)): ?>\n";
        $sidebar_html .= "                    <div class=\"nav-item\">\n";
        if (isset($item['route'])) {
            $sidebar_html .= "                        <a class=\"nav-link\" data-php-route=\"true\" href=\"<?php echo \$base_url; ?>{$item['route']}\">\n";
        } else {
            $sidebar_html .= "                        <a class=\"nav-link\" data-view=\"{$item['id']}\" href=\"#{$item['id']}\">\n";
        }
        $sidebar_html .= "                            <span class=\"nav-icon\">{$item['icon']}</span> <span class=\"nav-text\">{$item['title']}</span>\n";
        $sidebar_html .= "                        </a>\n";
        $sidebar_html .= "                    </div>\n";
        $sidebar_html .= "                    <?php endif; ?>\n";
    }

    $sidebar_html .= "                </div>\n";
    $sidebar_html .= "            </div>\n";
}

$sidebar_html .= "        </nav>";

// Replace the nav-list block
$content = preg_replace('/<nav class="nav-list">.*?<\/nav>/s', $sidebar_html, $content);

// Add JavaScript for search and accordion
$js = <<<JS
    <script>
        function toggleNavGroup(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'none';
                icon.style.transform = 'rotate(-90deg)';
            }
        }

        document.getElementById('nav-search').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const groups = document.querySelectorAll('.nav-group');

            groups.forEach(group => {
                const links = group.querySelectorAll('.nav-item');
                let hasVisible = false;

                links.forEach(link => {
                    const text = link.querySelector('.nav-text').textContent.toLowerCase();
                    if (text.includes(term)) {
                        link.style.display = 'block';
                        hasVisible = true;
                    } else {
                        link.style.display = 'none';
                    }
                });

                const content = group.querySelector('.nav-group-content');
                const icon = group.querySelector('.toggle-icon');
                
                if (term.length > 0) {
                    if (hasVisible) {
                        group.style.display = 'block';
                        content.style.display = 'block';
                        icon.style.transform = 'rotate(0deg)';
                    } else {
                        group.style.display = 'none';
                    }
                } else {
                    group.style.display = 'block';
                    link.style.display = 'block'; // Reset
                }
            });

            // If empty search, reset everything to visible but maybe keep some collapsed
            if (term.length === 0) {
                document.querySelectorAll('.nav-item').forEach(l => l.style.display = 'block');
                // You could collapse some by default here if desired.
            }
        });
    </script>
</body>
</html>
JS;

$content = str_replace('</body>', $js, $content);

file_put_contents($blade_file, $content);
echo "Sidebar revamp completed!\n";
