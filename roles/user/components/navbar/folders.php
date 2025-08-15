<!-- Navbar -->
<nav>
    <i class='bx bx-menu'></i>
    <form action="#">
        <div class="form-input">
            <input type="search" placeholder="Search...">
            <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
        </div>
    </form>
    <input type="checkbox" id="switch-mode" hidden>
    <label for="switch-mode" class="switch-mode"></label>
    <a href="#" class="notification">
        <i class='bx bxs-bell'></i>
        <span class="num">8</span>
    </a>
    <a href="#" class="profile" title="<?php echo htmlspecialchars($currentUser['name'] . ($departmentCode ? ' - ' . $departmentCode : '')); ?>">
        <?php if ($departmentImage && file_exists($departmentImage)): ?>
            <img src="<?php echo htmlspecialchars($departmentImage); ?>" alt="<?php echo htmlspecialchars($departmentCode . ' Profile'); ?>" style="width: 36px; height: 36px;">
        <?php else: ?>
            <div class="profile-icon">
                <?php echo getUserInitials($currentUser['name']); ?>
            </div>
        <?php endif; ?>
    </a>
</nav>