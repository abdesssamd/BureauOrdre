<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_admin();

require('../vendor/fpdf/fpdf.php'); // Assure-toi d'avoir FPDF dans /vendor

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Export des logs',0,1,'C');
$pdf->Ln(5);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(40,10,'Date',1);
$pdf->Cell(50,10,'Utilisateur',1);
$pdf->Cell(40,10,'Action',1);
$pdf->Cell(60,10,'Details',1);
$pdf->Ln();

$pdf->SetFont('Arial','',10);

$stmt = $pdo->query("
    SELECT l.*, u.full_name
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
");
while ($log = $stmt->fetch()) {
    $pdf->Cell(40,8,$log['created_at'],1);
    $pdf->Cell(50,8,utf8_decode($log['full_name']??'Systeme'),1);
    $pdf->Cell(40,8,$log['action'],1);
    $pdf->Cell(60,8,utf8_decode(substr($log['details'],0,40)),1);
    $pdf->Ln();
}

$pdf->Output('D', 'logs_export.pdf');
