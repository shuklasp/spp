<?php

namespace SPPMod\SPPXDB;

/**
 * Class Paginator
 * Structured response for paginated query results.
 */
class Paginator
{
    public $data;
    public $total;
    public $perPage;
    public $currentPage;
    public $lastPage;

    public function __construct(array $data, $total, $perPage, $currentPage)
    {
        $this->data = $data;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
    }

    /**
     * Determine if there are more pages.
     */
    public function hasMorePages()
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Get the next page number, or null if on the last page.
     */
    public function nextPage()
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    /**
     * Get the previous page number, or null if on the first page.
     */
    public function previousPage()
    {
        return $this->currentPage > 1 ? $this->currentPage - 1 : null;
    }

    /**
     * Convert paginator to array representation.
     */
    public function toArray()
    {
        return [
            'data' => $this->data,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'next_page' => $this->nextPage(),
            'prev_page' => $this->previousPage()
        ];
    }
}
