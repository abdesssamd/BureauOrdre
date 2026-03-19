<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function share_acl_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS share_groups (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            owner_id INT(11) NOT NULL,
            department_id INT(11) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_group_dept (department_id),
            INDEX idx_group_owner (owner_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS share_group_members (
            group_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            role ENUM('manager','member') NOT NULL DEFAULT 'member',
            added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (group_id, user_id),
            INDEX idx_sgm_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS folder_shares (
            id INT(11) NOT NULL AUTO_INCREMENT,
            folder_id INT(11) NOT NULL,
            shared_with_user_id INT(11) DEFAULT NULL,
            shared_with_department_id INT(11) DEFAULT NULL,
            shared_with_group_id INT(11) DEFAULT NULL,
            can_view TINYINT(1) NOT NULL DEFAULT 1,
            can_download TINYINT(1) NOT NULL DEFAULT 1,
            can_share TINYINT(1) NOT NULL DEFAULT 0,
            can_edit TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATE DEFAULT NULL,
            created_by INT(11) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_folder_share_folder (folder_id),
            INDEX idx_folder_share_user (shared_with_user_id),
            INDEX idx_folder_share_dept (shared_with_department_id),
            INDEX idx_folder_share_group (shared_with_group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    share_acl_add_column_if_missing($pdo, 'file_shares', 'shared_with_group_id', "INT(11) DEFAULT NULL");
    share_acl_add_column_if_missing($pdo, 'file_shares', 'can_view', "TINYINT(1) NOT NULL DEFAULT 1");
    share_acl_add_column_if_missing($pdo, 'file_shares', 'can_download', "TINYINT(1) NOT NULL DEFAULT 1");
    share_acl_add_column_if_missing($pdo, 'file_shares', 'can_share', "TINYINT(1) NOT NULL DEFAULT 0");
    share_acl_add_column_if_missing($pdo, 'file_shares', 'can_edit', "TINYINT(1) NOT NULL DEFAULT 0");
    share_acl_add_column_if_missing($pdo, 'file_shares', 'expires_at', "DATE DEFAULT NULL");
    share_acl_add_column_if_missing($pdo, 'file_shares', 'created_by', "INT(11) DEFAULT NULL");

    share_acl_add_index_if_missing($pdo, 'file_shares', 'idx_file_share_group', '(shared_with_group_id)');
    share_acl_add_index_if_missing($pdo, 'file_shares', 'idx_file_share_dept', '(shared_with_department_id)');
}

function share_acl_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    if (!$stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function share_acl_add_index_if_missing(PDO $pdo, string $table, string $indexName, string $columnsSql): void
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->execute([$table, $indexName]);
    if (!$stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE {$table} ADD INDEX {$indexName} {$columnsSql}");
    }
}

function share_acl_user_group_ids(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT group_id FROM share_group_members WHERE user_id = ?");
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function share_acl_get_file_permission(PDO $pdo, int $fileId, int $userId, ?int $deptId): array
{
    $stmt = $pdo->prepare("SELECT id, owner_id, department_id, visibility, folder_id FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        return ['exists' => false, 'can_view' => false, 'can_download' => false, 'can_share' => false, 'can_edit' => false];
    }

    if ((int) $file['owner_id'] === $userId) {
        return ['exists' => true, 'can_view' => true, 'can_download' => true, 'can_share' => true, 'can_edit' => true];
    }

    $baseView = false;
    $baseDownload = false;
    if ($file['visibility'] === 'public') {
        $baseView = true;
        $baseDownload = true;
    } elseif ($file['visibility'] === 'department' && $deptId !== null && (int) $file['department_id'] === (int) $deptId) {
        $baseView = true;
        $baseDownload = true;
    }

    $groups = share_acl_user_group_ids($pdo, $userId);
    $groupPlaceholders = count($groups) ? implode(',', array_fill(0, count($groups), '?')) : 'NULL';
    $sql = "SELECT MAX(can_view) AS can_view, MAX(can_download) AS can_download, MAX(can_share) AS can_share, MAX(can_edit) AS can_edit
            FROM file_shares
            WHERE file_id = ?
              AND (expires_at IS NULL OR expires_at >= CURDATE())
              AND (
                  shared_with_user_id = ?
                  OR shared_with_department_id = ?";
    if (count($groups)) {
        $sql .= " OR shared_with_group_id IN ($groupPlaceholders)";
    }
    $sql .= ")";
    $params = [$fileId, $userId, $deptId];
    if (count($groups)) {
        $params = array_merge($params, $groups);
    }
    $stmtShare = $pdo->prepare($sql);
    $stmtShare->execute($params);
    $s = $stmtShare->fetch(PDO::FETCH_ASSOC) ?: [];

    $canView = $baseView || ((int)($s['can_view'] ?? 0) === 1);
    $canDownload = $baseDownload || ((int)($s['can_download'] ?? 0) === 1);
    $canShare = ((int)($s['can_share'] ?? 0) === 1);
    $canEdit = ((int)($s['can_edit'] ?? 0) === 1);

    if (!$canView && !empty($file['folder_id'])) {
        $folderPerm = share_acl_get_folder_permission($pdo, (int)$file['folder_id'], $userId, $deptId);
        $canView = $folderPerm['can_view'];
        $canDownload = $canDownload || $folderPerm['can_download'];
        $canShare = $canShare || $folderPerm['can_share'];
        $canEdit = $canEdit || $folderPerm['can_edit'];
    }

    return [
        'exists' => true,
        'can_view' => $canView,
        'can_download' => $canDownload,
        'can_share' => $canShare,
        'can_edit' => $canEdit,
    ];
}

function share_acl_get_folder_permission(PDO $pdo, int $folderId, int $userId, ?int $deptId): array
{
    $stmt = $pdo->prepare("SELECT id, owner_id, department_id FROM folders WHERE id = ?");
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$folder) {
        return ['exists' => false, 'can_view' => false, 'can_download' => false, 'can_share' => false, 'can_edit' => false];
    }
    if ((int)$folder['owner_id'] === $userId) {
        return ['exists' => true, 'can_view' => true, 'can_download' => true, 'can_share' => true, 'can_edit' => true];
    }

    $groups = share_acl_user_group_ids($pdo, $userId);
    $groupPlaceholders = count($groups) ? implode(',', array_fill(0, count($groups), '?')) : 'NULL';
    $sql = "SELECT MAX(can_view) AS can_view, MAX(can_download) AS can_download, MAX(can_share) AS can_share, MAX(can_edit) AS can_edit
            FROM folder_shares
            WHERE folder_id = ?
              AND (expires_at IS NULL OR expires_at >= CURDATE())
              AND (
                  shared_with_user_id = ?
                  OR shared_with_department_id = ?";
    if (count($groups)) {
        $sql .= " OR shared_with_group_id IN ($groupPlaceholders)";
    }
    $sql .= ")";
    $params = [$folderId, $userId, $deptId];
    if (count($groups)) {
        $params = array_merge($params, $groups);
    }
    $stmtPerm = $pdo->prepare($sql);
    $stmtPerm->execute($params);
    $s = $stmtPerm->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'exists' => true,
        'can_view' => ((int)($s['can_view'] ?? 0) === 1),
        'can_download' => ((int)($s['can_download'] ?? 0) === 1),
        'can_share' => ((int)($s['can_share'] ?? 0) === 1),
        'can_edit' => ((int)($s['can_edit'] ?? 0) === 1),
    ];
}

