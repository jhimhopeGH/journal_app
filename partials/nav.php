<?php
// partials/nav.php
// Include this after setting $currentPage in the calling file.
// $currentPage values used: 'dashboard', 'journal', 'ej', 'admin', 'users', etc.
if (!isset($currentPage)) {
    $currentPage = '';
}

require_once __DIR__ . '/../auth.php';

$navUser = getCurrentUser();
$isNavAdmin = isAdmin();

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
        <?php
        $canDashboard     = canAccessNav('dashboard');
        $canJournal       = canAccessNav('journal');
        $canEJ            = canAccessNav('ej');
        $canReceipt       = canAccessNav('receipt_reprint');
        $hasReprintGroup  = $canJournal || $canEJ || $canReceipt;

        $canUsers         = canAccessNav('users');
        $canAdminDb       = canAccessNav('admin');
        $canStore         = canAccessNav('store_master');
        $canRegister      = canAccessNav('register_master');
        $canTender        = canAccessNav('tender_master');
        $canLayout        = canAccessNav('layout');
        $canEJLayout      = canAccessNav('ej_layout');
        $canReceiptLayout = canAccessNav('receipt_layout');
        $canAs400Config   = canAccessNav('as400_config');
        $canActivityLog   = canAccessNav('activity_log');
        $canCashier       = canAccessNav('cashier_master');
        $hasAdminGroup    = $canUsers || $canAdminDb || $canStore || $canRegister || $canTender || $canLayout || $canEJLayout || $canReceiptLayout || $canAs400Config || $canActivityLog || $canCashier;
        ?>

        <?php if ($canDashboard): ?>
            <div class="nav-group-label">
                <span class="nav-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                </span>
                Main
            </div>
            <a class="<?php echo navClass('dashboard', $currentPage); ?> nav-sublink" href="dashboard.php">
                <span class="nav-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                <span>Dashboard</span>
            </a>
        <?php endif; ?>

        <?php if ($hasReprintGroup): ?>
            <div class="nav-group-label">
                <span class="nav-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                </span>
                Reprint
            </div>
            <?php if ($canJournal): ?>
                <a class="<?php echo navClass('journal', $currentPage); ?> nav-sublink" href="records.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </span>
                    <span>Zread Reprint</span>
                </a>
            <?php endif; ?>
            <?php if ($canEJ): ?>
                <a class="<?php echo navClass('ej', $currentPage); ?> nav-sublink" href="ej_printing.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    </span>
                    <span>EJ Printing</span>
                </a>
            <?php endif; ?>
            <?php if ($canReceipt): ?>
                <a class="<?php echo navClass('receipt_reprint', $currentPage); ?> nav-sublink" href="receipt_reprint.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"></path><line x1="8" y1="8" x2="16" y2="8"></line><line x1="8" y1="12" x2="16" y2="12"></line><line x1="8" y1="16" x2="13" y2="16"></line></svg>
                    </span>
                    <span>Receipt Reprint</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($hasAdminGroup): ?>
            <div class="nav-group-label">
                <span class="nav-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                </span>
                Admin
            </div>
            <?php if ($canUsers): ?>
                <a class="<?php echo navClass('users', $currentPage); ?> nav-sublink" href="users.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </span>
                    <span>User Management</span>
                </a>
            <?php endif; ?>
            <?php if ($canAdminDb): ?>
                <a class="<?php echo navClass('admin', $currentPage); ?> nav-sublink" href="admin.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                    </span>
                    <span>Database</span>
                </a>
            <?php endif; ?>
            <?php if ($canStore): ?>
                <a class="<?php echo navClass('store_master', $currentPage); ?> nav-sublink" href="store_master.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3z"></path><path d="M3 7l2 12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2l2-12"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>
                    </span>
                    <span>Store Master</span>
                </a>
            <?php endif; ?>
            <?php if ($canRegister): ?>
                <a class="<?php echo navClass('register_master', $currentPage); ?> nav-sublink" href="register_master.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    </span>
                    <span>Register Master</span>
                </a>
            <?php endif; ?>
            <?php if ($canTender): ?>
                <a class="<?php echo navClass('tender_master', $currentPage); ?> nav-sublink" href="tender_master.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    </span>
                    <span>Tender Master</span>
                </a>
            <?php endif; ?>
            <?php if ($canCashier): ?>
                <a class="<?php echo navClass('cashier_master', $currentPage); ?> nav-sublink" href="cashier_master.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </span>
                    <span>Cashier Master</span>
                </a>
            <?php endif; ?>
            <?php if ($canLayout): ?>
                <a class="<?php echo navClass('layout', $currentPage); ?> nav-sublink" href="print_layout.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
                    </span>
                    <span>Zread Print Layout</span>
                </a>
            <?php endif; ?>
            <?php if ($canEJLayout): ?>
                <a class="<?php echo navClass('ej_layout', $currentPage); ?> nav-sublink" href="ej_print_layout.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </span>
                    <span>EJ Print Layout</span>
                </a>
            <?php endif; ?>
            <?php if ($canReceiptLayout): ?>
                <a class="<?php echo navClass('receipt_layout', $currentPage); ?> nav-sublink" href="receipt_print_layout.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                    </span>
                    <span>Receipt Print Layout</span>
                </a>
            <?php endif; ?>
            <?php if ($canAs400Config): ?>
                <a class="<?php echo navClass('as400_config', $currentPage); ?> nav-sublink" href="as400_config.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    </span>
                    <span>AS400 Settings</span>
                </a>
            <?php endif; ?>
            <?php if ($canActivityLog): ?>
                <a class="<?php echo navClass('activity_log', $currentPage); ?> nav-sublink" href="activity_log.php">
                    <span class="nav-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </span>
                    <span>Activity Log</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <!-- User Profile & Logout Widget -->
    <?php if ($navUser): ?>
        <div style="margin-top: auto; padding: 14px 18px 12px; border-top: 1px solid rgba(255,255,255,0.08);">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 9px; overflow: hidden;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                        <?php echo strtoupper(substr($navUser['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div style="overflow: hidden; line-height: 1.25;">
                        <div style="font-size: 12px; font-weight: 700; color: #f8fafc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($navUser['username'] ?? 'User'); ?>
                        </div>
                        <div style="font-size: 10px; font-weight: 600; color: <?php echo $isNavAdmin ? '#c084fc' : '#94a3b8'; ?>; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php echo htmlspecialchars($navUser['role'] ?? 'user'); ?>
                        </div>
                    </div>
                </div>
                <a href="logout.php" title="Sign Out" onclick="return confirm('Are you sure you want to log out?');" style="color: #f87171; background: rgba(239, 68, 68, 0.12); border-radius: 12px; padding: 6px 10px; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; transition: all 0.15s ease;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Logout</span>
                </a>
            </div>

            <!-- Change Password Button (Under User Logout) -->
            <button type="button" id="btn_nav_open_pwd_modal" class="nav-change-pw-btn" title="Change your account password">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <span>Change Password</span>
            </button>
        </div>

        <!-- Global Change Password Modal -->
        <div class="app-modal-overlay" id="nav_change_password_modal">
            <div class="app-modal-box">
                <div class="app-modal-header">
                    <h3>🔑 Change Your Password</h3>
                    <button type="button" class="app-modal-close" id="nav_modal_close_pwd" title="Close">&times;</button>
                </div>
                <div class="app-modal-body">
                    <form id="nav_change_password_form">
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 14px;">
                            Account: <strong style="color: #0f172a;"><?php echo htmlspecialchars($navUser['username']); ?></strong> &bull; Role: <span style="text-transform: uppercase; font-weight: 600; color: #0284c7;"><?php echo htmlspecialchars($navUser['role'] ?? 'user'); ?></span>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                Current Password <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="password" name="current_password" required placeholder="Enter current password" autocomplete="current-password" style="width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                New Password <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="password" name="new_password" required placeholder="Min. 8 chars (uppercase, lowercase, number, symbol)" autocomplete="new-password" style="width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                Confirm New Password <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="password" name="confirm_password" required placeholder="Re-enter new password" autocomplete="new-password" style="width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                        </div>

                        <div id="nav_pwd_status_msg" style="display: none; padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; margin-bottom: 14px;"></div>

                        <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
                            <button type="button" class="btn btn-secondary" id="nav_btn_cancel_pwd" style="margin: 0; padding: 7px 14px; font-size: 13px;">Cancel</button>
                            <button type="submit" class="btn" id="nav_btn_submit_pwd" style="margin: 0; padding: 7px 18px; font-size: 13px; background: #0284c7; font-weight: 700;">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const btnOpen = document.getElementById('btn_nav_open_pwd_modal');
            const modal   = document.getElementById('nav_change_password_modal');
            const btnClose= document.getElementById('nav_modal_close_pwd');
            const btnCancel=document.getElementById('nav_btn_cancel_pwd');
            const form    = document.getElementById('nav_change_password_form');
            const status  = document.getElementById('nav_pwd_status_msg');
            const submitBtn = document.getElementById('nav_btn_submit_pwd');

            function openPwdModal(e) {
                if (e) e.preventDefault();
                if (form) form.reset();
                if (status) { status.style.display = 'none'; status.textContent = ''; }
                if (modal) modal.classList.add('active');
            }
            function closePwdModal() {
                if (modal) modal.classList.remove('active');
            }

            if (btnOpen) btnOpen.addEventListener('click', openPwdModal);
            if (btnClose) btnClose.addEventListener('click', closePwdModal);
            if (btnCancel) btnCancel.addEventListener('click', closePwdModal);
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) closePwdModal();
                });
            }

            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Updating...'; }
                    if (status) { status.style.display = 'none'; }

                    const formData = new FormData(form);
                    try {
                        const resp = await fetch('change_password_ajax.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await resp.json();
                        if (data.success) {
                            status.style.display = 'block';
                            status.style.background = '#dcfce7';
                            status.style.color = '#15803d';
                            status.textContent = '✓ ' + (data.message || 'Password updated successfully!');
                            form.reset();
                            setTimeout(closePwdModal, 1400);
                        } else {
                            status.style.display = 'block';
                            status.style.background = '#fee2e2';
                            status.style.color = '#b91c1c';
                            status.textContent = '⚠️ ' + (data.error || 'Failed to update password.');
                        }
                    } catch (err) {
                        status.style.display = 'block';
                        status.style.background = '#fee2e2';
                        status.style.color = '#b91c1c';
                        status.textContent = '❌ Network error: ' + err.message;
                    } finally {
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Update Password'; }
                    }
                });
            }
        })();
        </script>
    <?php endif; ?>
</aside>
