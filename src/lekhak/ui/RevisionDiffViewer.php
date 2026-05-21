<?php
namespace App\Lekhak\UI;

/**
 * RevisionDiffViewer
 * 
 * Simple class to render differences between two revision arrays.
 */
class RevisionDiffViewer
{
    /**
     * Compare two revision arrays and return HTML highlighting the differences.
     *
     * @param array $old  The older revision data array.
     * @param array $new  The newer revision data array.
     * @return string     HTML output of the diff.
     */
    public static function renderDiff(array $old, array $new): string
    {
        $html = "<div class='spp-revision-diff'>";
        
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($allKeys);

        $html .= "<table class='spp-table'>";
        $html .= "<thead><tr><th>Field</th><th>Old Value</th><th>New Value</th></tr></thead>";
        $html .= "<tbody>";

        $hasDiff = false;
        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            // Skip identical complex structures for simplicity (or serialize them)
            if (is_array($oldVal)) $oldVal = json_encode($oldVal);
            if (is_array($newVal)) $newVal = json_encode($newVal);

            if ((string)$oldVal !== (string)$newVal) {
                $hasDiff = true;
                $html .= "<tr>";
                $html .= "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
                $html .= "<td class='spp-diff-removed'><del>" . htmlspecialchars((string)$oldVal) . "</del></td>";
                $html .= "<td class='spp-diff-added'><ins>" . htmlspecialchars((string)$newVal) . "</ins></td>";
                $html .= "</tr>";
            }
        }

        if (!$hasDiff) {
            $html .= "<tr><td colspan='3'>No differences found.</td></tr>";
        }

        $html .= "</tbody></table></div>";

        // basic styles for diff
        $html .= "<style>
            .spp-diff-removed del { background-color: #ffeef0; color: #b30000; text-decoration: none; padding: 2px 4px; border-radius: 3px; }
            .spp-diff-added ins { background-color: #e6ffed; color: #22863a; text-decoration: none; padding: 2px 4px; border-radius: 3px; }
        </style>";

        return $html;
    }
}
