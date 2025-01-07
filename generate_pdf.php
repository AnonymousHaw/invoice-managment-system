<?php
session_start();
require('fpdf/fpdf.php');
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['company_id'])) {
    die('Please log in to generate invoices');
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    die('Invoice ID not provided');
}

$invoiceId = intval($_GET['id']);
$companyId = $_SESSION['company_id'];

// Fetch invoice data with company verification
$query = "
    SELECT i.*, c.name AS client_name, c.address AS client_address, 
           c.email AS client_email, comp.name AS company_name,
           comp.email AS company_email, comp.phone AS company_phone,
           comp.logo_path
    FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    LEFT JOIN companies comp ON i.company_id = comp.id
    WHERE i.id = ? AND i.company_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $invoiceId, $companyId);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();

if (!$invoice) {
    die('Invoice not found or access denied');
}

// Fetch invoice items
$itemsQuery = "SELECT * FROM invoice_items WHERE invoice_id = ?";
$stmt = $conn->prepare($itemsQuery);
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$itemsResult = $stmt->get_result();
$items = $itemsResult->fetch_all(MYSQLI_ASSOC);

class PDF extends FPDF {
    function Header() {
        // This space intentionally left empty to override default header
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Create PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Add Company Logo if exists
if (!empty($invoice['logo_path']) && file_exists($invoice['logo_path'])) {
    // Adjust the positioning and size of the logo (10, 10 is the x and y position)
    $pdf->Image($invoice['logo_path'], 10, 10, 30); // You can adjust 30 to change the size of the logo
    $pdf->Ln(35); // Adjust the line break after the logo
} else {
    $pdf->Ln(10); // If no logo, just add a line break
}

// Add Invoice Title and Company Info
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $invoice['company_name'], 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Phone: ' . $invoice['company_phone'], 0, 1);
$pdf->Cell(0, 5, 'Email: ' . $invoice['company_email'], 0, 1);

// Add Invoice Info
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Invoice Details:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Invoice Number: ' . sprintf('INV-%06d', $invoice['id']), 0, 1);
$pdf->Cell(0, 5, 'Issue Date: ' . date('d/m/Y', strtotime($invoice['issue_date'])), 0, 1);
$pdf->Cell(0, 5, 'Due Date: ' . date('d/m/Y', strtotime($invoice['due_date'])), 0, 1);
$pdf->Cell(0, 5, 'Status: ' . ucfirst($invoice['status']), 0, 1);

// Add Client Info
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Bill To:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, $invoice['client_name'], 0, 1);
$pdf->Cell(0, 5, $invoice['client_address'], 0, 1);
$pdf->Cell(0, 5, 'Email: ' . $invoice['client_email'], 0, 1);

// Add Items Table
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(90, 7, 'Description', 1, 0, 'L', true);
$pdf->Cell(30, 7, 'Quantity', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Unit Price', 1, 0, 'R', true);
$pdf->Cell(35, 7, 'Total', 1, 0, 'R', true);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
$subtotal = 0;
foreach ($items as $item) {
    $total = $item['quantity'] * $item['unit_price'];
    $subtotal += $total;
    
    $pdf->Cell(90, 7, $item['description'], 1);
    $pdf->Cell(30, 7, $item['quantity'], 1, 0, 'C');
    $pdf->Cell(35, 7, '$' . number_format($item['unit_price'], 2), 1, 0, 'R');
    $pdf->Cell(35, 7, '$' . number_format($total, 2), 1, 0, 'R');
    $pdf->Ln();
}

// Add Totals
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(155, 7, 'Subtotal:', 1, 0, 'R');
$pdf->Cell(35, 7, '$' . number_format($subtotal, 2), 1, 0, 'R');
$pdf->Ln();

$tax = $subtotal * 0.1; // 10% tax
$pdf->Cell(155, 7, 'Tax (10%):', 1, 0, 'R');
$pdf->Cell(35, 7, '$' . number_format($tax, 2), 1, 0, 'R');
$pdf->Ln();

$pdf->Cell(155, 7, 'Total Amount:', 1, 0, 'R');
$pdf->Cell(35, 7, '$' . number_format($invoice['total_amount'], 2), 1, 0, 'R');

// Add Terms and Notes
$pdf->Ln(20);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'Terms and Conditions:', 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 5, 'Payment is due within ' . 
    round((strtotime($invoice['due_date']) - strtotime($invoice['issue_date'])) / (60 * 60 * 24)) . 
    ' days of issue date. Please include invoice number with your payment.');

// Output PDF
$pdf->Output('Invoice-' . sprintf('INV-%06d', $invoice['id']) . '.pdf', 'D');
?>
