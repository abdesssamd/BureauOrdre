<?php
// 1. Recuperer l'ID et l'Annee de l'exercice ouvert
function getActiveFiscalYear($pdo) {
    $stmt = $pdo->query("SELECT * FROM fiscal_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($active) {
        return $active;
    }

    // Aucun exercice actif: creation automatique de l'annee courante
    $currentYear = (int) date('Y');

    try {
        $pdo->beginTransaction();

        // Desactive tout exercice existant puis active/cree l'annee courante.
        $pdo->exec("UPDATE fiscal_years SET is_active = 0");

        $upsert = $pdo->prepare(
            "INSERT INTO fiscal_years (year, is_active) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)"
        );
        $upsert->execute([$currentYear]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }

    $stmt = $pdo->query("SELECT * FROM fiscal_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. Generer le numero base sur l'exercice actif
function generateMailReference($pdo, $type) {
    $fiscal = getActiveFiscalYear($pdo);

    if (!$fiscal) {
        return "ERREUR:AUCUN_EXERCICE_OUVERT";
    }

    $year = $fiscal['year'];
    $prefix = ($type === 'arrivee') ? 'ARR' : 'DEP';

    $sql = "SELECT reference_no FROM mails
            WHERE type = ? AND reference_no LIKE ?
            ORDER BY id DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$type, "$prefix/$year/%"]);
    $lastRef = $stmt->fetchColumn();

    if ($lastRef) {
        $parts = explode('/', $lastRef);
        $number = intval(end($parts)) + 1;
    } else {
        $number = 1;
    }

    return sprintf("%s/%s/%04d", $prefix, $year, $number);
}
?>
