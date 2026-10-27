<?php

function staffPermissionGroups()
{
    return [
        'inventory' => [
            'title' => 'Sản phẩm & danh mục',
            'icon' => 'fa-boxes-stacked',
            'description' => 'Quản lý sản phẩm và danh mục',
            'permissions' => [
                'products.view',
                'products.create',
                'products.update',
                'products.delete',
                'categories.view',
                'categories.create',
                'categories.update',
                'categories.delete'
            ]
        ],
        'orders' => [
            'title' => 'Đơn hàng',
            'icon' => 'fa-receipt',
            'description' => 'Quản lý và cập nhật đơn hàng',
            'permissions' => ['orders.view', 'orders.update', 'orders.delete']
        ],
        'customers' => [
            'title' => 'Khách hàng',
            'icon' => 'fa-users',
            'description' => 'Quản lý thông tin khách hàng',
            'permissions' => ['customers.view', 'customers.create', 'customers.update', 'customers.delete']
        ],
        'contacts' => [
            'title' => 'Liên hệ',
            'icon' => 'fa-envelope',
            'description' => 'Xem và xử lý tin nhắn liên hệ',
            'permissions' => ['contacts.view', 'contacts.update']
        ]
    ];
}

function staffGroupKeysFromRequest($requestedGroups, $permissionGroups)
{
    if (!is_array($requestedGroups)) {
        return [];
    }

    $groupKeys = [];
    foreach ($requestedGroups as $groupKey) {
        if (!is_string($groupKey) || !isset($permissionGroups[$groupKey])) {
            continue;
        }
        $groupKeys[] = $groupKey;
    }

    return array_values(array_unique($groupKeys));
}

function staffPermissionsFromGroups($groupKeys, $permissionGroups)
{
    $permissions = ['dashboard.view'];
    foreach ($groupKeys as $groupKey) {
        $permissions = array_merge($permissions, $permissionGroups[$groupKey]['permissions']);
    }
    return array_values(array_unique($permissions));
}

function saveStaffPermissionGroups(mysqli $conn, $staffId, $groupKeys)
{
    $statement = $conn->prepare(
        'INSERT INTO staff_permission_groups (staff_id, group_key) VALUES (?, ?)'
    );
    if (!$statement) {
        return 'Không thể chuẩn bị lưu nhóm quyền: ' . $conn->error;
    }

    foreach ($groupKeys as $groupKey) {
        $statement->bind_param('is', $staffId, $groupKey);
        if (!$statement->execute()) {
            $error = 'Không thể lưu nhóm quyền nhân viên: ' . $statement->error;
            $statement->close();
            return $error;
        }
    }

    $statement->close();
    return '';
}

function saveStaffPermissions(mysqli $conn, $staffId, $permissions)
{
    $statement = $conn->prepare(
        'INSERT INTO staff_permissions (staff_id, permission_key) VALUES (?, ?)'
    );
    if (!$statement) {
        return 'Không thể chuẩn bị phân quyền nhân viên: ' . $conn->error;
    }

    foreach ($permissions as $permission) {
        $statement->bind_param('is', $staffId, $permission);
        if (!$statement->execute()) {
            $error = 'Không thể lưu quyền nhân viên: ' . $statement->error;
            $statement->close();
            return $error;
        }
    }

    $statement->close();
    return '';
}
