// Hàm tìm kiếm sản phẩm
function showResults(query) {
    // Ẩn kết quả khi ô tìm kiếm rỗng
    if (query.length === 0) {
        document.getElementById("searchResults").style.display = "none";
        return;
    }

    // Kiểm tra kích thước màn hình
    if (window.innerWidth <= 600) {
        // Nếu trên điện thoại, chuyển sang trang kết quả tìm kiếm
        window.location.href = `search.php?search=${query}`;
    } else {
        // Gửi yêu cầu AJAX đến PHP để tìm kiếm kết quả
        $.ajax({
            url: 'search_suggestions.php', // Tệp PHP xử lý tìm kiếm
            method: 'GET',
            data: { search: query },
            success: function(data) {
                let results = JSON.parse(data);
                let resultHtml = '';
                
                if (results.length > 0) {
                    // Nếu có kết quả tìm kiếm, hiển thị chúng
                    results.forEach(function(product) {
                        // Kiểm tra nếu có ảnh của sản phẩm
                        let productImage = product.image ? `<img src="${product.image}" alt="${product.name}" style="width: 50px; height: 50px;">` : '<img src="images/default.png" alt="No image" style="width: 50px; height: 50px;">';

                        resultHtml += `
                            <li>
                                <a href="product_details.php?id=${product.product_id}">
                                    ${product.name} - ${Number(product.price).toLocaleString('vi-VN')} đ
                                    <div>${productImage}</div> <!-- Hiển thị hình ảnh -->
                                </a>
                            </li>
                        `;
                    });
                } else {
                    // Nếu không có kết quả, hiển thị thông báo
                    resultHtml = '<li>Không tìm thấy sản phẩm nào.</li>';
                }

                // Cập nhật kết quả tìm kiếm
                document.getElementById("resultsList").innerHTML = resultHtml;
                document.getElementById("searchResults").style.display = "block"; // Hiển thị kết quả
            },
            error: function() {
                console.log('Có lỗi khi tìm kiếm');
            }
        });
    }
}
