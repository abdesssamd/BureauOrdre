<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/lang_setup.php'; // Charge la langue ($t) et la direction ($dir)

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$mail_id = $_GET['id'] ?? 0;

// Récupération des infos du courrier
$stmt = $pdo->prepare("SELECT m.*, c.name as contact_name FROM mails m LEFT JOIN contacts c ON m.contact_id = c.id WHERE m.id = ?");
$stmt->execute([$mail_id]);
$mail = $stmt->fetch();

if (!$mail) die("Document introuvable");
?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $t['receipt_title'] ?> - <?= $mail['reference_no'] ?></title>
    <style>
        /* Police Arabe pour l'impression */
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap');

        body { 
            font-family: 'Segoe UI', 'Tajawal', sans-serif; 
            padding: 40px; 
            background: #fff;
            color: #000;
        }
        
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            border: 2px solid #000; 
            padding: 30px; 
            position: relative;
        }

        .header { text-align: center; margin-bottom: 40px; }
        .header h3 { margin: 5px 0; text-transform: uppercase; font-size: 14px; }
        .header h1 { margin: 20px 0; font-size: 24px; text-decoration: underline; }

        .content { margin-bottom: 50px; font-size: 16px; line-height: 1.8; }
        .row { display: flex; margin-bottom: 10px; }
        .label { font-weight: bold; width: 200px; }
        .value { flex: 1; border-bottom: 1px dotted #999; }

        .footer { display: flex; justify-content: space-between; margin-top: 50px; }
        .box { 
            width: 40%; height: 100px; border: 1px solid #000; 
            padding: 10px; text-align: center; font-weight: bold; 
        }

        /* Bouton d'impression (caché sur le papier) */
        .print-btn {
            position: fixed; top: 20px; right: 20px;
            background: #3b82f6; color: white; padding: 10px 20px;
            border: none; border-radius: 5px; cursor: pointer; font-size: 16px;
            font-family: inherit;
        }
        
        /* Ajustements RTL (Arabe) */
        [dir="rtl"] .label { margin-left: 20px; }
        [dir="rtl"] .print-btn { left: 20px; right: auto; }

        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
            .container { border: none; }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="print-btn">🖨 <?= $t['print_receipt'] ?></button>

    <div class="container">
        <div class="header">
            <h3><?= $t['republic'] ?></h3>
            <h3><?= $t['ministry'] ?></h3>
            
            <h1><?= $t['receipt_title'] ?></h1>
        </div>

        <div class="content">
            <div class="row">
                <div class="label"><?= $t['receipt_ref'] ?> :</div>
                <div class="value" style="font-size: 1.2em; font-weight: bold;">
                    <?= $mail['reference_no'] ?>
                </div>
            </div>

            <div class="row">
                <div class="label"><?= $t['received_on'] ?> :</div>
                <div class="value">
                    <?= date('d/m/Y', strtotime($mail['mail_date'])) ?>
                </div>
            </div>

            <div class="row">
                <div class="label"><?= $t['received_from'] ?> :</div>
                <div class="value">
                    <?= htmlspecialchars($mail['contact_name']) ?>
                </div>
            </div>

            <div class="row">
                <div class="label"><?= $t['object_mail'] ?> :</div>
                <div class="value">
                    <?= htmlspecialchars($mail['object']) ?>
                </div>
            </div>
            
            <?php if($mail['external_ref']): ?>
            <div class="row">
                <div class="label"><?= $t['ext_ref'] ?> :</div>
                <div class="value">
                    <?= htmlspecialchars($mail['external_ref']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <div class="box">
                <?= $t['signature_agent'] ?>
            </div>
            <div class="box">
                <?= $t['cachet'] ?>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 50px; font-size: 12px; color: #666;">
            Généré automatiquement par AdminShare le <?= date('d/m/Y H:i') ?>
        </div>
    </div>

</body>
</html>