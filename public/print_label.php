<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/lang_setup.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$mail_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM mails WHERE id = ?");
$stmt->execute([$mail_id]);
$mail = $stmt->fetch();

if (!$mail) die("Introuvable");

// URL vers la page de détails (pour le QR Code)
// Remplacez 'localhost' par l'IP de votre serveur si vous êtes en réseau (ex: 192.168.1.10)
$url_qr = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/read_file.php?id=" . $mail['file_id'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Étiquette <?= $mail['reference_no'] ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { margin: 0; padding: 0; font-family: sans-serif; background: #eee; }
        
        /* Format Étiquette standard (ex: 10cm x 5cm) */
        .label-container {
            width: 380px;
            height: 180px;
            background: white;
            border: 1px dashed #ccc;
            margin: 20px auto;
            padding: 15px;
            display: flex;
            align-items: center;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        .qr-area { width: 110px; margin-right: 15px; }
        .info-area { flex: 1; }
        
        h2 { margin: 0 0 5px 0; font-size: 18px; text-transform: uppercase; }
        .ref { font-size: 22px; font-weight: bold; color: #000; margin-bottom: 5px; display:block; }
        .meta { font-size: 12px; color: #333; margin-bottom: 2px; }
        .archive-loc { 
            margin-top: 10px; border: 2px solid #000; padding: 5px; 
            font-weight: bold; text-align: center; font-size: 14px; 
        }

        @media print {
            body { background: white; }
            .label-container { border: none; margin: 0; }
            button { display: none; }
        }
    </style>
</head>
<body>

    <div style="text-align:center; margin-top:20px;">
        <button onclick="window.print()" style="padding:10px 20px; font-size:16px; cursor:pointer;">🖨 Imprimer l'Étiquette</button>
    </div>

    <div class="label-container">
        <div class="qr-area">
            <div id="qrcode"></div>
        </div>

        <div class="info-area">
            <div style="font-size:10px; text-transform:uppercase;"><?= $t['ministry'] ?></div>
            <span class="ref"><?= $mail['reference_no'] ?></span>
            <div class="meta"><strong>Date :</strong> <?= date('d/m/Y', strtotime($mail['mail_date'])) ?></div>
            <div class="meta"><strong>Objet :</strong> <?= substr($mail['object'], 0, 30) ?>...</div>
            
            <?php if($mail['archive_box']): ?>
            <div class="archive-loc">
                BOX: <?= $mail['archive_box'] ?> | RAYON: <?= $mail['archive_shelf'] ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Génération du QR Code
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= $url_qr ?>",
            width: 100,
            height: 100,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    </script>

</body>
</html>