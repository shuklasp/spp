<?php
namespace SPPMod\SPPView;

/**
 * Trait WithPagination
 * 
 * Provides seamless reactive pagination for SPPLive components.
 */
trait WithPagination
{
    public int $page = 1;

    public function mountWithPagination(): void
    {
        if (isset($_GET['page']) && is_numeric($_GET['page'])) {
            $this->page = (int) $_GET['page'];
        }
        
        if (!property_exists($this, 'queryString')) {
            $this->queryString = [];
        }
        
        if (!in_array('page', $this->queryString)) {
            $this->queryString[] = 'page';
        }
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->setPage($this->page - 1);
        }
    }

    public function nextPage(): void
    {
        $this->setPage($this->page + 1);
    }

    public function gotoPage(int $page): void
    {
        $this->setPage($page);
    }

    private function setPage(int $page): void
    {
        if (method_exists($this, 'updatingPage')) {
            $this->updatingPage($page);
        }
        
        $this->page = $page;
        
        if (method_exists($this, 'updatedPage')) {
            $this->updatedPage($page);
        }
    }
}
