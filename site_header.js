document.addEventListener('DOMContentLoaded', function () {
    var storeHeader = document.querySelector('.store-header');
    if (storeHeader) {
        var updateStoreHeaderHeight = function () {
            document.documentElement.style.setProperty(
                '--store-header-height',
                storeHeader.getBoundingClientRect().height + 'px'
            );
        };

        updateStoreHeaderHeight();
        if ('ResizeObserver' in window) {
            new ResizeObserver(updateStoreHeaderHeight).observe(storeHeader);
        } else {
            window.addEventListener('resize', updateStoreHeaderHeight);
        }
    }

    var storeNavigation = document.getElementById('storeNavigation');
    var menuToggle = document.querySelector('.store-mobile-menu-toggle');
    var menuBackdrop = document.querySelector('.store-menu-backdrop');
    var menuCloseButton = document.querySelector('.store-mobile-menu-close');
    var submenuToggles = document.querySelectorAll('.store-nav-submenu-toggle');

    function toggleMobileSubmenu(submenuToggle) {
        var menuItem = submenuToggle.closest('.store-nav-disclosure-item');
        var isExpanded = menuItem.classList.toggle('is-expanded');
        var submenuLabel = submenuToggle.getAttribute('data-submenu-label');
        submenuToggle.setAttribute('aria-expanded', String(isExpanded));
        submenuToggle.setAttribute(
            'aria-label',
            (isExpanded ? 'Thu gọn ' : 'Mở ') + submenuLabel
        );
    }

    submenuToggles.forEach(function (submenuToggle) {
        submenuToggle.addEventListener('click', function () {
            toggleMobileSubmenu(submenuToggle);
        });
    });

    function closeMobileMenu() {
        if (!storeNavigation || !menuToggle || !menuBackdrop) {
            return;
        }
        storeNavigation.classList.remove('is-open');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Mở menu');
        menuBackdrop.hidden = true;
        document.body.classList.remove('store-menu-open');
    }

    if (storeNavigation && menuToggle && menuBackdrop) {
        menuToggle.addEventListener('click', function () {
            var isOpen = storeNavigation.classList.toggle('is-open');
            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.setAttribute('aria-label', isOpen ? 'Đóng menu' : 'Mở menu');
            menuBackdrop.hidden = !isOpen;
            document.body.classList.toggle('store-menu-open', isOpen);
        });
        menuBackdrop.addEventListener('click', closeMobileMenu);
        if (menuCloseButton) {
            menuCloseButton.addEventListener('click', closeMobileMenu);
        }
        storeNavigation.addEventListener('click', function (event) {
            var disclosureLink = event.target.closest('.store-nav-disclosure-item > a');
            if (disclosureLink && window.matchMedia('(max-width: 760px)').matches) {
                event.preventDefault();
                var submenuToggle = disclosureLink.parentElement.querySelector('.store-nav-submenu-toggle');
                if (submenuToggle) {
                    toggleMobileSubmenu(submenuToggle);
                }
                return;
            }
            if (event.target.closest('a')) {
                closeMobileMenu();
            }
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 760) {
                closeMobileMenu();
            }
        });
    }

    // === VIÊN HOVER TRƯỢT CHO MENU DESKTOP ===
    // Một "viên" nền xám chạy từ nút này sang nút khác thay vì mỗi nút
    // tự bật/tắt nền, nên hiệu ứng mượt và không nhảy layout.
    var navList = storeNavigation ? storeNavigation.querySelector('.store-navigation-list') : null;
    var desktopNavQuery = window.matchMedia('(min-width: 761px)');

    if (navList) {
        var navPill = document.createElement('span');
        navPill.className = 'store-nav-hover-pill';
        navPill.setAttribute('aria-hidden', 'true');
        navList.insertBefore(navPill, navList.firstChild);

        var activeNavLink = null;

        var getNavLinks = function () {
            return navList.querySelectorAll(
                '.store-navigation-item > a, .store-navigation-item > .store-nav-search-toggle'
            );
        };

        var moveNavPill = function (link) {
            if (!desktopNavQuery.matches) {
                return;
            }
            var isFirstShow = !navPill.classList.contains('is-visible');
            var listRect = navList.getBoundingClientRect();
            var linkRect = link.getBoundingClientRect();

            if (isFirstShow) {
                navPill.classList.add('is-snapping');
            }

            navPill.style.width = linkRect.width + 'px';
            navPill.style.height = linkRect.height + 'px';
            navPill.style.transform =
                'translate3d(' +
                (linkRect.left - listRect.left) + 'px, ' +
                (linkRect.top - listRect.top) + 'px, 0)';

            if (isFirstShow) {
                // Ép trình duyệt ghi nhận vị trí mới trước khi bật hiệu ứng mờ dần.
                void navPill.offsetWidth;
                navPill.classList.remove('is-snapping');
            }

            navPill.classList.add('is-visible');
            activeNavLink = link;
        };

        var hideNavPill = function () {
            navPill.classList.remove('is-visible');
            activeNavLink = null;
        };

        Array.prototype.forEach.call(getNavLinks(), function (link) {
            link.addEventListener('mouseenter', function () {
                moveNavPill(link);
            });
            link.addEventListener('focus', function () {
                moveNavPill(link);
            });
        });

        navList.addEventListener('mouseleave', hideNavPill);
        navList.addEventListener('focusout', function (event) {
            if (!navList.contains(event.relatedTarget)) {
                hideNavPill();
            }
        });

        window.addEventListener('resize', function () {
            if (!desktopNavQuery.matches) {
                hideNavPill();
                return;
            }
            if (activeNavLink) {
                moveNavPill(activeNavLink);
            }
        });
    }

    var searchPanel = document.querySelector('.store-search-panel');
    var searchToggles = document.querySelectorAll('.store-nav-search-toggle');
    var searchToggle = document.querySelector('.store-nav-search-item .store-nav-search-toggle');
    var searchItem = searchToggle ? searchToggle.closest('.store-nav-search-item') : null;
    var searchBackdrop = document.querySelector('.store-search-backdrop');
    var searchCloseButton = document.querySelector('.store-search-close');
    var searchInput = document.getElementById('storeSearchInput');
    var searchResults = document.getElementById('storeSearchResults');
    var popularProducts = document.getElementById('storePopularProducts');
    var historyList = document.getElementById('storeSearchHistory');
    var historySection = document.getElementById('storeSearchHistorySection');
    var searchForm = document.querySelector('.store-search-form');
    var clearHistoryButton = document.getElementById('clearStoreSearchHistory');
    var debounceTimer;
    var historyStorageKey = 'pop_pop_search_history';
    var popularProductsLoaded = false;

    if (!searchPanel || !searchToggle || !searchInput || !searchResults || !popularProducts) {
        return;
    }

    function readHistory() {
        try {
            var storedHistory = JSON.parse(localStorage.getItem(historyStorageKey) || '[]');
            return Array.isArray(storedHistory)
                ? storedHistory.filter(function (term) {
                    return typeof term === 'string' && term.trim() !== '';
                }).slice(0, 6)
                : [];
        } catch (error) {
            console.error('Không thể đọc lịch sử tìm kiếm:', error);
            return [];
        }
    }

    function saveHistory(term) {
        var normalizedTerm = term.trim();
        if (!normalizedTerm) {
            return;
        }

        try {
            var nextHistory = [normalizedTerm].concat(readHistory().filter(function (item) {
                return item.toLocaleLowerCase() !== normalizedTerm.toLocaleLowerCase();
            })).slice(0, 6);
            localStorage.setItem(historyStorageKey, JSON.stringify(nextHistory));
        } catch (error) {
            console.error('Không thể lưu lịch sử tìm kiếm:', error);
        }
    }

    function renderProducts(list, products, emptyMessage) {
        list.replaceChildren();
        if (!products.length) {
            var emptyItem = document.createElement('li');
            emptyItem.className = 'store-search-message';
            emptyItem.textContent = emptyMessage;
            list.appendChild(emptyItem);
            return;
        }

        products.forEach(function (product) {
            var item = document.createElement('li');
            var link = document.createElement('a');
            var image = document.createElement('img');
            var details = document.createElement('span');
            var name = document.createElement('strong');
            var price = document.createElement('small');

            link.href = './product_details.php?id=' + encodeURIComponent(product.product_id);
            image.src = product.image || './img/product.png';
            image.alt = '';
            image.loading = 'lazy';
            name.textContent = product.name;
            price.textContent = Number(product.price).toLocaleString('vi-VN') + ' đ';
            details.append(name, price);
            link.append(image, details);
            item.appendChild(link);
            list.appendChild(item);
        });
    }

    function showRequestError(list, message) {
        list.replaceChildren();
        var errorItem = document.createElement('li');
        errorItem.className = 'store-search-message';
        errorItem.textContent = message;
        list.appendChild(errorItem);
    }

    function renderHistory() {
        if (!historyList || !historySection) {
            return;
        }

        var history = readHistory();
        historySection.hidden = history.length === 0;
        historyList.replaceChildren();
        history.forEach(function (term) {
            var item = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = term;
            button.addEventListener('click', function () {
                searchInput.value = term;
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
            item.appendChild(button);
            historyList.appendChild(item);
        });
    }

    function loadProducts(url, list, emptyMessage) {
        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Không thể tải danh sách sản phẩm.');
                }
                return response.json();
            })
            .then(function (products) {
                if (!Array.isArray(products)) {
                    throw new Error('Dữ liệu sản phẩm không hợp lệ.');
                }
                renderProducts(list, products, emptyMessage);
            })
            .catch(function (error) {
                console.error('Lỗi tìm kiếm sản phẩm:', error);
                showRequestError(list, 'Không tải được sản phẩm. Vui lòng thử lại.');
            });
    }

    function showPopularProducts() {
        if (popularProductsLoaded) {
            return;
        }

        loadProducts('./search_suggestions.php', popularProducts, 'Chưa có sản phẩm phổ biến.');
        popularProductsLoaded = true;
    }

    function openSearchPanel(focusInput) {
        if (window.matchMedia('(max-width: 760px)').matches && storeHeader) {
            storeHeader.appendChild(searchPanel);
            if (searchBackdrop) {
                searchBackdrop.hidden = false;
            }
        }
        searchPanel.classList.add('is-open');
        searchToggles.forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        });
        renderHistory();
        showPopularProducts();
        if (focusInput) {
            searchInput.focus();
        }
    }

    function closeSearchPanel() {
        searchPanel.classList.remove('is-open');
        searchToggles.forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        });
        if (searchBackdrop) {
            searchBackdrop.hidden = true;
        }
        if (searchItem && searchPanel.parentElement !== searchItem) {
            searchItem.appendChild(searchPanel);
        }
    }

    searchToggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            if (toggle.classList.contains('store-mobile-search-toggle')) {
                closeMobileMenu();
            }
            if (searchPanel.classList.contains('is-open')) {
                closeSearchPanel();
            } else {
                openSearchPanel(true);
            }
        });
    });

    if (searchBackdrop) {
        searchBackdrop.addEventListener('click', closeSearchPanel);
    }
    if (searchCloseButton) {
        searchCloseButton.addEventListener('click', closeSearchPanel);
    }

    if (searchItem) {
        searchItem.addEventListener('pointerenter', function (event) {
            if (event.pointerType !== 'touch' && !window.matchMedia('(max-width: 760px)').matches) {
                openSearchPanel(false);
            }
        });

        searchItem.addEventListener('pointerleave', function () {
            if (!window.matchMedia('(max-width: 760px)').matches && !searchItem.contains(document.activeElement)) {
                closeSearchPanel();
            }
        });

        searchItem.addEventListener('focusout', function () {
            window.setTimeout(function () {
                if (!window.matchMedia('(max-width: 760px)').matches &&
                    !searchItem.contains(document.activeElement) &&
                    !searchItem.matches(':hover')) {
                    closeSearchPanel();
                }
            }, 0);
        });
    }

    searchInput.addEventListener('input', function () {
        window.clearTimeout(debounceTimer);
        var query = searchInput.value.trim();
        renderHistory();

        if (!query) {
            searchResults.hidden = true;
            popularProducts.closest('section').hidden = false;
            return;
        }

        popularProducts.closest('section').hidden = true;
        searchResults.hidden = false;
        showRequestError(searchResults, 'Đang tìm kiếm...');

        debounceTimer = window.setTimeout(function () {
            loadProducts(
                './search_suggestions.php?search=' + encodeURIComponent(query),
                searchResults,
                'Không tìm thấy sản phẩm.'
            );
        }, 250);
    });

    if (searchForm) {
        searchForm.addEventListener('submit', function () {
            saveHistory(searchInput.value);
        });
    }

    searchResults.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            saveHistory(searchInput.value);
        }
    });

    popularProducts.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            saveHistory(searchInput.value);
        }
    });

    if (clearHistoryButton) {
        clearHistoryButton.addEventListener('click', function () {
            try {
                localStorage.removeItem(historyStorageKey);
            } catch (error) {
                console.error('Không thể xóa lịch sử tìm kiếm:', error);
            }
            renderHistory();
        });
    }

    document.addEventListener('click', function (event) {
        if (!searchPanel.contains(event.target) && !Array.prototype.some.call(searchToggles, function (toggle) {
            return toggle.contains(event.target);
        }) && !(searchBackdrop && searchBackdrop.contains(event.target))) {
            closeSearchPanel();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSearchPanel();
            closeMobileMenu();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 760 && searchItem && searchPanel.parentElement !== searchItem) {
            closeSearchPanel();
        }
    });
});
