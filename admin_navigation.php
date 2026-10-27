<?php
$adminNavigationItems = [
    'dashboard' => ['href' => './admin_dashboard.php', 'icon' => 'fa-gauge-high', 'label' => 'Tổng quan', 'permission' => 'dashboard.view'],
    'categories' => ['href' => './manage_categories.php', 'icon' => 'fa-tags', 'label' => 'Danh mục', 'permission' => 'categories.view'],
    'products' => ['href' => './manage_products.php', 'icon' => 'fa-box-open', 'label' => 'Sản phẩm', 'permission' => 'products.view'],
    'customers' => ['href' => './manage_customers.php', 'icon' => 'fa-users', 'label' => 'Khách hàng', 'permission' => 'customers.view'],
    'orders' => ['href' => './manage_orders.php', 'icon' => 'fa-receipt', 'label' => 'Đơn hàng', 'permission' => 'orders.view'],
    'contacts' => ['href' => './manage_contacts.php', 'icon' => 'fa-envelope', 'label' => 'Liên hệ', 'permission' => 'contacts.view'],
    'staff' => ['href' => './manage_staff.php', 'icon' => 'fa-user-shield', 'label' => 'Nhân viên', 'permission' => 'staff.manage']
];
?>
<header class="admin-topbar">
    <button class="admin-mobile-menu-toggle store-mobile-menu-toggle" type="button" aria-label="Mở menu quản trị" aria-controls="admin-navigation" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
    <a class="admin-brand store-logo-link" href="./admin_dashboard.php">
        <img class="logo" src="./img/logo.png" alt="Ủa Store">
        <span class="admin-brand-copy">
            <span class="store-brand-name" aria-label="Ủa Store"><span aria-hidden="true">Ủ</span><span aria-hidden="true">a</span><span class="store-brand-space" aria-hidden="true">&nbsp;</span><span aria-hidden="true">S</span><span aria-hidden="true">t</span><span aria-hidden="true">o</span><span aria-hidden="true">r</span><span aria-hidden="true">e</span></span>
            <small>QUẢN TRỊ</small>
        </span>
    </a>
    <button class="admin-mobile-menu-backdrop store-menu-backdrop" type="button" aria-label="Đóng menu quản trị" hidden></button>
    <nav class="admin-navigation" id="admin-navigation" aria-label="Điều hướng quản trị">
        <div class="admin-mobile-menu-heading store-mobile-menu-heading">
            <span>MENU</span>
            <button class="admin-mobile-menu-close store-mobile-menu-close" type="button" aria-label="Đóng menu quản trị">
                &times;
            </button>
        </div>
        <?php foreach ($adminNavigationItems as $page => $item): ?>
            <?php if (!adminHasPermission($item['permission'])) continue; ?>
            <a href="<?php echo $item['href']; ?>"
                class="<?php echo isset($adminActivePage) && $adminActivePage === $page ? 'is-active' : ''; ?>"
                <?php echo isset($adminActivePage) && $adminActivePage === $page ? 'aria-current="page"' : ''; ?>>
                <i class="fas <?php echo $item['icon']; ?>" aria-hidden="true"></i>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
        <a class="admin-mobile-logout" href="./logout.php">
            <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
            <span>Đăng xuất</span>
        </a>
    </nav>
    <a class="admin-logout" href="./logout.php">
        <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
        <span>Đăng xuất</span>
    </a>
</header>
<script>
    (function () {
        var menu = document.getElementById('admin-navigation');
        var toggle = document.querySelector('.admin-mobile-menu-toggle');
        var closeButton = document.querySelector('.admin-mobile-menu-close');
        var backdrop = document.querySelector('.admin-mobile-menu-backdrop');

        if (!menu || !toggle || !closeButton || !backdrop) {
            return;
        }

        var topbar = menu.closest('.admin-topbar');
        var updateHeaderHeight = function () {
            if (topbar) {
                document.documentElement.style.setProperty(
                    '--admin-header-height',
                    topbar.getBoundingClientRect().height + 'px'
                );
            }
        };

        updateHeaderHeight();
        if (topbar && 'ResizeObserver' in window) {
            new ResizeObserver(updateHeaderHeight).observe(topbar);
        } else {
            window.addEventListener('resize', updateHeaderHeight);
        }

        function setMenuOpen(isOpen) {
            menu.classList.toggle('is-open', isOpen);
            backdrop.hidden = !isOpen;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggle.setAttribute('aria-label', isOpen ? 'Đóng menu quản trị' : 'Mở menu quản trị');
            document.body.classList.toggle('admin-menu-open', isOpen);
        }

        toggle.addEventListener('click', function () {
            setMenuOpen(!menu.classList.contains('is-open'));
        });
        closeButton.addEventListener('click', function () {
            setMenuOpen(false);
        });
        backdrop.addEventListener('click', function () {
            setMenuOpen(false);
        });
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setMenuOpen(false);
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 760) {
                setMenuOpen(false);
            }
        });
    }());
</script>
