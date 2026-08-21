<?php
// Extracted from AdminLifecycleCommand.php
echo "
            <div style='padding: 1rem;'>
                <p>Found " . count($updates) . " modules with pending updates:</p>
                $listHtml
            </div>";
