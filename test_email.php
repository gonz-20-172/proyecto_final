<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    // Probar PHPMailer
    echo "Probando PHPMailer...\n";
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    echo "✅ PHPMailer instalado correctamente\n\n";
    
    // Probar DomPDF
    echo "Probando DomPDF...\n";
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($options);
    echo "✅ DomPDF instalado correctamente\n\n";
    
    // Probar generación de PDF simple
    echo "Probando generación de PDF...\n";
    $html = '<html><body><h1>Test PDF</h1><p>Esto es una prueba de DomPDF</p></body></html>';
    $dompdf->loadHtml($html);
    $dompdf->setPaper('Letter', 'portrait');
    $dompdf->render();
    echo "✅ Generación de PDF funciona correctamente\n\n";
    
    // Probar función getMailer
    echo "Probando función getMailer()...\n";
    require_once __DIR__ . '/includes/functions.php';
    $mailer = getMailer();
    echo "✅ Función getMailer() funciona correctamente\n\n";
    
    echo "🎉 ¡Todo configurado correctamente!\n";
    echo "📝 Nota: Se cambió de mPDF a DomPDF exitosamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Detalles: " . $e->getTraceAsString() . "\n";
}