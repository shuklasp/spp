<?php
// Extracted from DevLifecycleCommand.php
echo "
            <div style='padding: 1rem;'>
                <p>Found " . count($updates) . " modules with pending updates:</p>
                $listHtml
            </div>";
