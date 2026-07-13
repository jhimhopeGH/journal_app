<?php
// partials/nav.php
// Include this after setting $currentPage in the calling file.
// $currentPage values used: 'dashboard', 'journal', 'ej', 'admin'
if (!isset($currentPage)) {
    $currentPage = '';
}

function navClass($page, $current) {
    return 'nav-link' . ($page === $current ? ' active' : '');
}
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark">EJ</span>
        <span class="brand-text">Electronic Journal</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">
            <span class="nav-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            </span>
            Main
        </div>
        <a class="<?php echo navClass('dashboard', $currentPage); ?> nav-sublink" href="dashboard.php">
            Dashboard
        </a>

        <div class="nav-group-label">
            <span class="nav-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            </span>
            Reprint
        </div>
        <a class="<?php echo navClass('journal', $currentPage); ?> nav-sublink" href="records.php">Zread Reprint</a>
        <a class="<?php echo navClass('ej', $currentPage); ?> nav-sublink" href="ej_printing.php">EJ Printing</a>

        <div class="nav-group-label">
            <span class="nav-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </span>
            Admin
        </div>
        <a class="<?php echo navClass('admin', $currentPage); ?> nav-sublink" href="admin.php">
            Database
        </a>
<<<<<<< Updated upstream
=======
        <a class="<?php echo navClass('store_master', $currentPage); ?> nav-sublink" href="store_master.php">
            Store Master
        </a>
        <a class="<?php echo navClass('tender_master', $currentPage); ?> nav-sublink" href="tender_master.php">
            Tender Master
        </a>
>>>>>>> Stashed changes
        <a class="<?php echo navClass('layout', $currentPage); ?> nav-sublink" href="print_layout.php">
            Print Layout
        </a>
    </nav>
</aside>
