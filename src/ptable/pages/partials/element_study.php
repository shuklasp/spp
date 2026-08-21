<?php
/**
 * External View Partial: element_study.php
 * Context: Element Comprehensive Study View
 */
?>
<?php if (isset($element) && isset($wiki)): ?>
<div class="pro-study-container <?= htmlspecialchars($element['category']) ?>">
    
    <!-- Premium Hero Section -->
    <header class="pro-hero">
        <div class="pro-hero-content">
            <span class="pro-badge">Atomic Number <?= $element['atomic'] ?></span>
            <h1 class="pro-title"><?= htmlspecialchars($element['name']) ?></h1>
            <p class="pro-subtitle">Symbol <?= $element['symbol'] ?> &mdash; <?= htmlspecialchars($element['category']) ?></p>
        </div>
        <?php if (!empty($wiki['thumbnail'])): ?>
            <div class="pro-hero-image">
                <img src="<?= htmlspecialchars($wiki['thumbnail']) ?>" alt="<?= htmlspecialchars($element['name']) ?>" />
            </div>
        <?php endif; ?>
    </header>
    
    <!-- Two Column Layout -->
    <div class="pro-layout">
        <!-- Sidebar Navigation -->
        <aside class="pro-sidebar">
            <nav class="pro-toc">
                <h3 class="pro-toc-title">Contents</h3>
                <ul>
                    <li><a href="#section-overview">Overview</a></li>
                    <?php if (!empty($wiki['sections'])): ?>
                        <?php foreach (array_keys($wiki['sections']) as $title): ?>
                            <li><a href="#section-<?= md5($title) ?>"><?= htmlspecialchars($title) ?></a></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <!-- Main Reading Content -->
        <article class="pro-article">
            <section id="section-overview" class="pro-section">
                <div class="pro-text">
                    <?= $wiki['extract_html'] ?? '' ?>
                </div>
            </section>
            
            <?php if (!empty($wiki['sections'])): ?>
                <?php foreach ($wiki['sections'] as $title => $html): ?>
                    <section id="section-<?= md5($title) ?>" class="pro-section">
                        <h2><?= htmlspecialchars($title) ?></h2>
                        <div class="pro-text">
                            <?= $html ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>

            <footer class="pro-footer">
                <p>Curated Scientific Database &bull; Clean Offline Edition</p>
            </footer>
        </article>
    </div>
</div>
<?php else: ?>
    <div class="pro-error">
        <p>Comprehensive study material is currently unavailable for this element.</p>
    </div>
<?php endif; ?>
