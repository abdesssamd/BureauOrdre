<?php
/**
 * API Messagerie - endpoints AJAX
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/auth.php';
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'non_connecte']);
    exit;
}

$me = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo->query("SELECT 1 FROM msg_conversations LIMIT 1");
} catch (Throwable $e) {
    echo json_encode(['error' => 'tables_messagerie_manquantes', 'count' => 0]);
    exit;
}

function json_error(string $code): void {
    echo json_encode(['error' => $code]);
    exit;
}

function user_in_conversation(PDO $pdo, int $convId, int $userId): bool {
    $check = $pdo->prepare("SELECT 1 FROM msg_participants WHERE conversation_id = ? AND user_id = ?");
    $check->execute([$convId, $userId]);
    return (bool) $check->fetchColumn();
}

function ensure_typing_table(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS msg_typing_status (
            conversation_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (conversation_id, user_id),
            INDEX idx_typing_conv_updated (conversation_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $done = true;
}

function ensure_presence_table(PDO $pdo): void {
    static $donePresence = false;
    if ($donePresence) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS msg_presence_status (
            user_id INT(11) NOT NULL,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            INDEX idx_presence_last_seen (last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $donePresence = true;
}

if ($action === 'conversations') {
    $q = trim((string) ($_GET['q'] ?? ''));

    $stmt = $pdo->prepare(
        "SELECT c.id, c.updated_at,
                (SELECT body FROM msg_messages WHERE conversation_id = c.id ORDER BY created_at DESC, id DESC LIMIT 1) AS last_msg,
                (SELECT created_at FROM msg_messages WHERE conversation_id = c.id ORDER BY created_at DESC, id DESC LIMIT 1) AS last_msg_at,
                (SELECT COUNT(*) FROM msg_messages m
                 WHERE m.conversation_id = c.id
                   AND m.sender_id != ?
                   AND (p.last_read_at IS NULL OR m.created_at > p.last_read_at)) AS unread
         FROM msg_conversations c
         JOIN msg_participants p ON p.conversation_id = c.id
         WHERE p.user_id = ?
         ORDER BY COALESCE((SELECT created_at FROM msg_messages WHERE conversation_id = c.id ORDER BY created_at DESC, id DESC LIMIT 1), c.updated_at) DESC"
    );
    $stmt->execute([$me, $me]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $otherIdStmt = $pdo->prepare("SELECT user_id FROM msg_participants WHERE conversation_id = ? AND user_id != ?");
    $otherStmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id = ?");
    ensure_presence_table($pdo);
    $presenceStmt = $pdo->prepare(
        "SELECT 1 FROM msg_presence_status WHERE user_id = ? AND last_seen_at >= (NOW() - INTERVAL 2 MINUTE) LIMIT 1"
    );

    $result = [];
    foreach ($conversations as $conv) {
        $otherIdStmt->execute([(int) $conv['id'], $me]);
        $otherId = (int) ($otherIdStmt->fetchColumn() ?: 0);

        $other = null;
        if ($otherId > 0) {
            $otherStmt->execute([$otherId]);
            $other = $otherStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($q !== '') {
            $haystack = strtolower(($other['full_name'] ?? '') . ' ' . ($other['username'] ?? ''));
            if (strpos($haystack, strtolower($q)) === false) {
                continue;
            }
        }

        $conv['id'] = (int) $conv['id'];
        $conv['unread'] = (int) $conv['unread'];
        $conv['other_id'] = $otherId;
        $conv['other'] = $other;
        $otherOnline = false;
        if ($otherId > 0) {
            $presenceStmt->execute([$otherId]);
            $otherOnline = (bool) $presenceStmt->fetchColumn();
        }
        $conv['other_online'] = $otherOnline;
        $result[] = $conv;
    }

    echo json_encode(['conversations' => $result]);
    exit;
}

if ($action === 'messages') {
    $with = (int) ($_GET['with'] ?? $_POST['with'] ?? 0);
    $convId = (int) ($_GET['conv'] ?? $_POST['conv'] ?? 0);
    $since = trim((string) ($_GET['since'] ?? ''));
    $before = trim((string) ($_GET['before'] ?? ''));
    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));

    if ($with > 0) {
        $exists = $pdo->prepare(
            "SELECT c.id
             FROM msg_conversations c
             JOIN msg_participants p1 ON p1.conversation_id = c.id AND p1.user_id = ?
             JOIN msg_participants p2 ON p2.conversation_id = c.id AND p2.user_id = ?
             LIMIT 1"
        );
        $exists->execute([$me, $with]);
        $convId = (int) ($exists->fetchColumn() ?: 0);

        if ($convId <= 0) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO msg_conversations () VALUES ()")->execute();
                $convId = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO msg_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)")
                    ->execute([$convId, $me, $convId, $with]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                json_error('creation_conversation_echouee');
            }
        }
    }

    if ($convId <= 0) {
        json_error('conv_invalide');
    }

    if (!user_in_conversation($pdo, $convId, $me)) {
        json_error('acces_refuse');
    }

    $messages = [];
    $hasMore = false;
    $mode = 'initial';

    if ($since !== '') {
        $mode = 'since';
        $sql = "SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name AS sender_name,
                       m.attachment_stored, m.attachment_original, m.attachment_mime
                FROM msg_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.conversation_id = ? AND m.created_at > ?
                ORDER BY m.created_at ASC, m.id ASC
                LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$convId, $since]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($before !== '') {
        $mode = 'before';
        $sql = "SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name AS sender_name,
                       m.attachment_stored, m.attachment_original, m.attachment_mime
                FROM msg_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.conversation_id = ? AND m.created_at < ?
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT " . ($limit + 1);
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$convId, $before]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($messages) > $limit) {
            $hasMore = true;
            array_pop($messages);
        }
        $messages = array_reverse($messages);
    } else {
        $sql = "SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name AS sender_name,
                       m.attachment_stored, m.attachment_original, m.attachment_mime
                FROM msg_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.conversation_id = ?
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT " . ($limit + 1);
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$convId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($messages) > $limit) {
            $hasMore = true;
            array_pop($messages);
        }
        $messages = array_reverse($messages);
    }

    $pdo->prepare("UPDATE msg_participants SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?")
        ->execute([$convId, $me]);

    ensure_presence_table($pdo);
    $otherStmt = $pdo->prepare(
        "SELECT u.id, u.full_name, p.last_read_at,
                (CASE WHEN ps.last_seen_at >= (NOW() - INTERVAL 2 MINUTE) THEN 1 ELSE 0 END) AS online
         FROM msg_participants p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN msg_presence_status ps ON ps.user_id = u.id
         WHERE p.conversation_id = ? AND p.user_id != ?
         LIMIT 1"
    );
    $otherStmt->execute([$convId, $me]);
    $otherUser = $otherStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    ensure_typing_table($pdo);
    $typingStmt = $pdo->prepare(
        "SELECT 1 FROM msg_typing_status
         WHERE conversation_id = ? AND user_id != ? AND updated_at >= (NOW() - INTERVAL 8 SECOND)
         LIMIT 1"
    );
    $typingStmt->execute([$convId, $me]);
    $isOtherTyping = (bool) $typingStmt->fetchColumn();

    $oldestAt = null;
    $newestAt = null;
    if (!empty($messages)) {
        $oldestAt = $messages[0]['created_at'] ?? null;
        $newestAt = $messages[count($messages) - 1]['created_at'] ?? null;
    }

    echo json_encode([
        'conversation_id' => $convId,
        'other' => $otherUser,
        'messages' => $messages,
        'mode' => $mode,
        'has_more' => $hasMore,
        'oldest_at' => $oldestAt,
        'newest_at' => $newestAt,
        'typing' => $isOtherTyping,
        'server_time' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

if ($action === 'send') {
    $convId = (int) ($_POST['conversation_id'] ?? 0);
    $body = trim((string) ($_POST['body'] ?? ''));
    $hasFile = !empty($_FILES['attachment']['name']) && (int) ($_FILES['attachment']['error'] ?? 1) === UPLOAD_ERR_OK;

    if ($convId <= 0 || ($body === '' && !$hasFile)) {
        json_error('donnees_invalides');
    }
    if (!user_in_conversation($pdo, $convId, $me)) {
        json_error('acces_refuse');
    }

    $attStored = null;
    $attOriginal = null;
    $attMime = null;
    $maxSize = 5 * 1024 * 1024;
    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv'
    ];

    if ($hasFile) {
        $file = $_FILES['attachment'];
        $size = (int) ($file['size'] ?? 0);
        if ($size > $maxSize) {
            json_error('fichier_trop_volumineux');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($file['tmp_name']);
        $attMime = $detectedMime !== '' ? $detectedMime : (string) ($file['type'] ?? '');
        if (!in_array($attMime, $allowedMimes, true)) {
            json_error('format_non_autorise');
        }

        $dir = dirname(__DIR__) . '/uploads/messenger/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = pathinfo((string) $file['name'], PATHINFO_EXTENSION);
        $ext = $ext !== '' ? strtolower($ext) : 'bin';
        $attStored = bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $dir . $attStored;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            json_error('upload_echoue');
        }

        $attOriginal = (string) $file['name'];
    }

    if ($body === '' && $attStored !== null) {
        $body = strpos((string) $attMime, 'image/') === 0 ? '[Photo]' : '[Fichier]';
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO msg_messages (conversation_id, sender_id, body, attachment_stored, attachment_original, attachment_mime)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$convId, $me, $body, $attStored, $attOriginal, $attMime]);
    } catch (Throwable $e) {
        if ($attStored !== null) {
            $path = dirname(__DIR__) . '/uploads/messenger/' . $attStored;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        json_error('envoi_echoue');
    }

    $msgId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE msg_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);
    ensure_typing_table($pdo);
    $pdo->prepare("DELETE FROM msg_typing_status WHERE conversation_id = ? AND user_id = ?")->execute([$convId, $me]);

    $row = $pdo->prepare(
        "SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name AS sender_name,
                m.attachment_stored, m.attachment_original, m.attachment_mime
         FROM msg_messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.id = ?"
    );
    $row->execute([$msgId]);
    $message = $row->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        $message = [
            'id' => $msgId,
            'sender_id' => $me,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
            'attachment_stored' => $attStored,
            'attachment_original' => $attOriginal,
            'attachment_mime' => $attMime,
        ];
    }

    echo json_encode(['success' => true, 'message' => $message]);
    exit;
}

if ($action === 'typing_ping') {
    $convId = (int) ($_POST['conversation_id'] ?? $_GET['conv'] ?? 0);
    if ($convId <= 0) {
        json_error('conv_invalide');
    }
    if (!user_in_conversation($pdo, $convId, $me)) {
        json_error('acces_refuse');
    }
    ensure_typing_table($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO msg_typing_status (conversation_id, user_id, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)"
    );
    $stmt->execute([$convId, $me]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'typing_state') {
    $convId = (int) ($_GET['conv'] ?? 0);
    if ($convId <= 0) {
        json_error('conv_invalide');
    }
    if (!user_in_conversation($pdo, $convId, $me)) {
        json_error('acces_refuse');
    }
    ensure_typing_table($pdo);
    $typingStmt = $pdo->prepare(
        "SELECT 1 FROM msg_typing_status
         WHERE conversation_id = ? AND user_id != ? AND updated_at >= (NOW() - INTERVAL 8 SECOND)
         LIMIT 1"
    );
    $typingStmt->execute([$convId, $me]);
    echo json_encode(['typing' => (bool) $typingStmt->fetchColumn()]);
    exit;
}

if ($action === 'presence_ping') {
    ensure_presence_table($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO msg_presence_status (user_id, last_seen_at)
         VALUES (?, NOW())
         ON DUPLICATE KEY UPDATE last_seen_at = VALUES(last_seen_at)"
    );
    $stmt->execute([$me]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'contacts') {
    ensure_presence_table($pdo);
    $q = trim((string) ($_GET['q'] ?? ''));
    $sql = "SELECT u.id, u.full_name, u.username,
                   CASE WHEN ps.last_seen_at >= (NOW() - INTERVAL 2 MINUTE) THEN 1 ELSE 0 END AS online,
                   ps.last_seen_at
            FROM users u
            LEFT JOIN msg_presence_status ps ON ps.user_id = u.id
            WHERE u.is_active = 1 AND u.id != ?";
    $params = [$me];
    if ($q !== '') {
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY online DESC, u.full_name ASC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['online'] = (int) $r['online'] === 1;
    }
    echo json_encode(['contacts' => $rows]);
    exit;
}

if ($action === 'users') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $sql = "SELECT id, full_name, username FROM users WHERE is_active = 1 AND id != ?";
    $params = [$me];

    if ($q !== '') {
        $sql .= " AND (full_name LIKE ? OR username LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    $sql .= " ORDER BY full_name LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'unread_count') {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS n
         FROM msg_messages m
         JOIN msg_participants p ON p.conversation_id = m.conversation_id AND p.user_id = ?
         WHERE m.sender_id != ?
           AND (p.last_read_at IS NULL OR m.created_at > p.last_read_at)"
    );
    $stmt->execute([$me, $me]);
    echo json_encode(['count' => (int) $stmt->fetchColumn()]);
    exit;
}

echo json_encode(['error' => 'action_inconnue']);
