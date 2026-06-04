<?php
namespace SPPMod\Sppapi;

class SPPPaginator {
    
    public static function paginateQuery(string $query, int $page = 1, int $perPage = 15): array {
        if (!class_exists('\SPPMod\SPPDB\SPPDB')) {
            throw new \Exception("Database module not found.");
        }
        
        $db = new \SPPMod\SPPDB\SPPDB();
        
        // Very basic count injection (assumes simple SELECT)
        $countQuery = preg_replace('/SELECT\s+.*?\s+FROM/is', 'SELECT COUNT(*) as _count FROM', $query);
        $countRes = $db->exec_squery($countQuery);
        $total = $countRes[0]['_count'] ?? 0;
        
        $offset = ($page - 1) * $perPage;
        $paginatedQuery = $query . " LIMIT {$perPage} OFFSET {$offset}";
        $items = $db->exec_squery($paginatedQuery);
        
        return [
            'items' => $items,
            'total' => (int)$total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / max(1, $perPage))
        ];
    }
}
