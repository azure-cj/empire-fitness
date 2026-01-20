<?php
/**
 * Pagination Helper
 * Provides pagination functionality for large data sets
 */

class Pagination {
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $totalPages;
    
    public function __construct($totalItems, $itemsPerPage = 20, $currentPage = 1) {
        $this->totalItems = (int)$totalItems;
        $this->itemsPerPage = (int)$itemsPerPage;
        $this->currentPage = max(1, (int)$currentPage);
        $this->totalPages = ceil($this->totalItems / $this->itemsPerPage);
        
        // Make sure current page doesn't exceed total pages
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }
    }
    
    /**
     * Get the offset for SQL LIMIT clause
     */
    public function getOffset() {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    /**
     * Get current page
     */
    public function getCurrentPage() {
        return $this->currentPage;
    }
    
    /**
     * Get total pages
     */
    public function getTotalPages() {
        return $this->totalPages;
    }
    
    /**
     * Get total items
     */
    public function getTotalItems() {
        return $this->totalItems;
    }
    
    /**
     * Get items per page
     */
    public function getItemsPerPage() {
        return $this->itemsPerPage;
    }
    
    /**
     * Check if there's a previous page
     */
    public function hasPreviousPage() {
        return $this->currentPage > 1;
    }
    
    /**
     * Check if there's a next page
     */
    public function hasNextPage() {
        return $this->currentPage < $this->totalPages;
    }
    
    /**
     * Get previous page number
     */
    public function getPreviousPage() {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : 1;
    }
    
    /**
     * Get next page number
     */
    public function getNextPage() {
        return $this->hasNextPage() ? $this->currentPage + 1 : $this->totalPages;
    }
    
    /**
     * Get pagination info text
     */
    public function getInfo() {
        if ($this->totalItems === 0) {
            return "0 items";
        }
        
        $start = ($this->currentPage - 1) * $this->itemsPerPage + 1;
        $end = min($this->currentPage * $this->itemsPerPage, $this->totalItems);
        
        return "Showing $start to $end of {$this->totalItems} items";
    }
    
    /**
     * Get page range for display (e.g., pages 1-5, 6-10)
     */
    public function getPageRange($range = 5) {
        $start = max(1, $this->currentPage - floor($range / 2));
        $end = min($this->totalPages, $start + $range - 1);
        
        if ($end - $start < $range - 1) {
            $start = max(1, $end - $range + 1);
        }
        
        return range($start, $end);
    }
}

/**
 * Generate pagination HTML
 */
function generatePaginationHTML($pagination, $pageParam = 'page', $class = '') {
    if ($pagination->getTotalPages() <= 1) {
        return '';
    }
    
    $html = '<div class="pagination ' . htmlspecialchars($class) . '">';
    
    // Previous button
    if ($pagination->hasPreviousPage()) {
        $prevPage = $pagination->getPreviousPage();
        $html .= '<a href="?' . htmlspecialchars($pageParam) . '=' . $prevPage . '" class="pagination-btn prev-btn" title="Previous page">';
        $html .= '<i class="fas fa-chevron-left"></i> Previous';
        $html .= '</a>';
    } else {
        $html .= '<span class="pagination-btn prev-btn disabled"><i class="fas fa-chevron-left"></i> Previous</span>';
    }
    
    // Page numbers
    $html .= '<div class="pagination-pages">';
    
    // First page if not in range
    $pageRange = $pagination->getPageRange(5);
    if ($pageRange[0] > 1) {
        $html .= '<a href="?' . htmlspecialchars($pageParam) . '=1" class="page-num">1</a>';
        if ($pageRange[0] > 2) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
    }
    
    // Page range
    foreach ($pageRange as $page) {
        if ($page === $pagination->getCurrentPage()) {
            $html .= '<span class="page-num active">' . $page . '</span>';
        } else {
            $html .= '<a href="?' . htmlspecialchars($pageParam) . '=' . $page . '" class="page-num">' . $page . '</a>';
        }
    }
    
    // Last page if not in range
    if ($pageRange[count($pageRange) - 1] < $pagination->getTotalPages()) {
        if ($pageRange[count($pageRange) - 1] < $pagination->getTotalPages() - 1) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
        $html .= '<a href="?' . htmlspecialchars($pageParam) . '=' . $pagination->getTotalPages() . '" class="page-num">' . $pagination->getTotalPages() . '</a>';
    }
    
    $html .= '</div>';
    
    // Next button
    if ($pagination->hasNextPage()) {
        $nextPage = $pagination->getNextPage();
        $html .= '<a href="?' . htmlspecialchars($pageParam) . '=' . $nextPage . '" class="pagination-btn next-btn" title="Next page">';
        $html .= 'Next <i class="fas fa-chevron-right"></i>';
        $html .= '</a>';
    } else {
        $html .= '<span class="pagination-btn next-btn disabled">Next <i class="fas fa-chevron-right"></i></span>';
    }
    
    $html .= '</div>';
    
    return $html;
}
