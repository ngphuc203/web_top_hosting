<?php
/*
 * Đường dẫn tương đối từ trang hiện tại về thư mục gốc của dự án.
 * Giúp các link trong footer vẫn đúng khi up lên hosting, kể cả khi:
 *   - website nằm trong thư mục con (ví dụ /web_toy_hositng/)
 *   - trang đang xem nằm trong thư mục con (ví dụ /codetest/)
 */
$footerBase = './';
$footerRootDir = str_replace('\\', '/', __DIR__);
$footerScriptDir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : $footerRootDir));

if ($footerScriptDir !== $footerRootDir && strpos($footerScriptDir . '/', $footerRootDir . '/') === 0) {
    $footerSubPath = trim(substr($footerScriptDir, strlen($footerRootDir)), '/');

    if ($footerSubPath !== '') {
        $footerBase = str_repeat('../', substr_count($footerSubPath, '/') + 1);
    }
}
?>

<script>
    document.documentElement.classList.add('has-js');
</script>

<footer class="site-footer">
    <div class="footer-section">
        <div class="footer-header">
            <p>&ldquo;ỦA DIGITAL RETAIL STORE&rdquo;</p>
            <div class="footer-brand-subtitle">Cửa hàng đồ chơi &amp; sưu tầm</div>
        </div>
    
        <div class="footer-top">
            <div class="social-media">
                <a href="https://www.facebook.com/ngphuc203" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                    <i class="fab fa-facebook-f" aria-hidden="true"></i>
                </a>
                <a href="https://www.instagram.com/szismee" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                    <i class="fab fa-instagram" aria-hidden="true"></i>
                </a>
                <a href="https://www.tiktok.com/@szismee" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
                    <i class="fab fa-tiktok" aria-hidden="true"></i>
                </a>
                <a href="https://github.com/ngphuc203" target="_blank" rel="noopener noreferrer" aria-label="GitHub">
                    <i class="fab fa-github" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div class="newsletter">
            <h4>ĐĂNG KÝ ĐỂ NHẬN NHIỀU ƯU ĐÃI</h4>
            <form class="newsletter-form">
                <label class="visually-hidden" for="footerNewsletterEmail">Email của bạn</label>
                <input id="footerNewsletterEmail" type="email" name="newsletter_email" placeholder="Email address" autocomplete="email" required>
                <button type="submit">Đăng ký</button>
            </form>
            <p class="newsletter-note" role="status" aria-live="polite"></p>
        </div>

        <div class="hotline">
            <p>HOTLINE: <a href="tel:0389101040">0389.101.040</a> - <a href="tel:0345048132">0345.048.132</a></p>
        </div>

        <div class="footer-main">
            <div class="footer-accordion">
                <div class="footer-column" data-footer-column>
                    <h4 class="footer-column-heading">
                        <button class="footer-column-header" id="footer-header-about" type="button" aria-expanded="true" aria-controls="footer-content-about">
                            <span class="footer-column-title">Về chúng tôi</span>
                            <span class="footer-column-toggle" aria-hidden="true">▼</span>
                        </button>
                    </h4>
                    <div class="footer-column-content" id="footer-content-about" role="region" aria-labelledby="footer-header-about">
                        <div class="footer-column-body">
                            <p><strong>HỘ KINH DOANH “Ua Store”</strong></p>
                            <p>Trụ sở kinh doanh: Đang cập nhật địa chỉ</p>
                            <p>Người đại diện: Nguyễn Phúc</p>
                            <p>Email: <a href="mailto:ngphuc1753@gmail.com">ngphuc1753@gmail.com</a></p>
                        </div>
                    </div>
                </div>

                <div class="footer-column" data-footer-column>
                    <h4 class="footer-column-heading">
                        <button class="footer-column-header" id="footer-header-stores" type="button" aria-expanded="true" aria-controls="footer-content-stores">
                            <span class="footer-column-title">Hệ thống cửa hàng</span>
                            <span class="footer-column-toggle" aria-hidden="true">▼</span>
                        </button>
                    </h4>
                    <div class="footer-column-content" id="footer-content-stores" role="region" aria-labelledby="footer-header-stores">
                        <div class="footer-column-body">
                            <p><strong>CHI NHÁNH 1</strong><br>
                            Đang cập nhật<br>
                            Hotline: <a href="tel:0389101040">0389.101.040</a></p>
                            <p><strong>CHI NHÁNH 2</strong><br>
                            Đang cập nhật<br>
                            Hotline: <a href="tel:0345048132">0345.048.132</a></p>
                            <p><strong>ONLINE STORE</strong><br>
                            Mua sắm trực tuyến 24/7</p>
                        </div>
                    </div>
                </div>

                <div class="footer-column" data-footer-column>
                    <h4 class="footer-column-heading">
                        <button class="footer-column-header" id="footer-header-policy" type="button" aria-expanded="true" aria-controls="footer-content-policy">
                            <span class="footer-column-title">Chính sách</span>
                            <span class="footer-column-toggle" aria-hidden="true">▼</span>
                        </button>
                    </h4>
                    <div class="footer-column-content" id="footer-content-policy" role="region" aria-labelledby="footer-header-policy">
                        <div class="footer-column-body">
                            <a href="#">Chính sách đổi trả sản phẩm</a>
                            <a href="#">Hướng dẫn đổi trả</a>
                            <a href="#">Chính sách bảo mật thông tin</a>
                            <a href="#">Chính sách vận chuyển &amp; giao hàng</a>
                            <a href="#">Điều khoản giao dịch chung</a>
                            <a href="#">Chính sách thanh toán</a>
                        </div>
                    </div>
                </div>

                <div class="footer-column" data-footer-column>
                    <h4 class="footer-column-heading">
                        <button class="footer-column-header" id="footer-header-support" type="button" aria-expanded="true" aria-controls="footer-content-support">
                            <span class="footer-column-title">Hỗ trợ khách hàng</span>
                            <span class="footer-column-toggle" aria-hidden="true">▼</span>
                        </button>
                    </h4>
                    <div class="footer-column-content" id="footer-content-support" role="region" aria-labelledby="footer-header-support">
                        <div class="footer-column-body">
                            <a href="#">Hướng dẫn mua hàng</a>
                            <a href="#">Câu hỏi thường gặp</a>
                            <a href="#">Tra cứu đơn hàng</a>
                            <a href="<?php echo $footerBase; ?>contact.php">Liên hệ</a>
                            <a href="<?php echo $footerBase; ?>contact.php">Góp ý, khiếu nại</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-info">
            <p>© 2024 NGPHUC OFFICIAL STORE ALL RIGHT RESERVED</p>
        </div>
    </div>
</footer>

<script>
    (function () {
        var footer = document.querySelector('.site-footer');
        if (!footer) {
            return;
        }

        var columns = Array.prototype.slice.call(footer.querySelectorAll('[data-footer-column]'));
        var mobileQuery = window.matchMedia('(max-width: 768px)');
        var isMobile = mobileQuery.matches;

        function setColumnState(column, isOpen) {
            var header = column.querySelector('.footer-column-header');
            var content = column.querySelector('.footer-column-content');

            if (!header || !content) {
                return;
            }

            column.classList.toggle('active', isOpen);
            header.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            content.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

            if ('inert' in content) {
                content.inert = !isOpen;
            }
        }

        function applyMode() {
            isMobile = mobileQuery.matches;

            columns.forEach(function (column) {
                var header = column.querySelector('.footer-column-header');

                if (header) {
                    header.tabIndex = isMobile ? 0 : -1;
                }

                setColumnState(column, !isMobile);
            });
        }

        applyMode();

        columns.forEach(function (column) {
            var header = column.querySelector('.footer-column-header');

            if (!header) {
                return;
            }

            header.addEventListener('click', function () {
                if (!isMobile) {
                    return;
                }

                var willOpen = !column.classList.contains('active');

                columns.forEach(function (otherColumn) {
                    if (otherColumn !== column) {
                        setColumnState(otherColumn, false);
                    }
                });

                setColumnState(column, willOpen);
            });
        });

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', applyMode);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(applyMode);
        }

        var newsletterForm = footer.querySelector('.newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var emailInput = newsletterForm.querySelector('input[type="email"]');
                var note = footer.querySelector('.newsletter-note');

                if (!emailInput || !emailInput.checkValidity()) {
                    if (emailInput) {
                        emailInput.reportValidity();
                    }
                    return;
                }

                if (note) {
                    note.textContent = 'Cảm ơn bạn! Ưu đãi mới nhất sẽ được gửi tới email của bạn.';
                    note.classList.add('is-success');
                }

                newsletterForm.reset();
            });
        }
    })();
</script>
