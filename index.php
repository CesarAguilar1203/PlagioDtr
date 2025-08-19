<?php
session_start();
$_SESSION['documentos'] = $_SESSION['documentos'] ?? [];

/**
 * Extrae texto de un PDF utilizando pdftotext.
 */
function extraerTextoPdf(string $ruta): ?string {
    $salida = @shell_exec('pdftotext ' . escapeshellarg($ruta) . ' -');
    return $salida ? trim($salida) : null;
}

/**
 * Tokeniza un texto normalizándolo y eliminando caracteres especiales.
 */
function tokenizar(string $texto): array {
    $texto = strtolower($texto);
    $texto = preg_replace('/[^\p{L}\p{N}\s]/u', '', $texto);
    return array_unique(explode(' ', $texto));
}

/**
 * Guarda un documento en la sesión.
 */
function guardarDocumento(string $titulo, string $contenido): void {
    if (!$titulo || !$contenido) return;
    $_SESSION['documentos'][] = [
        'id' => uniqid(),
        'titulo' => $titulo,
        'contenido' => $contenido,
    ];
}

/**
 * Obtiene el contenido de un documento por su ID.
 */
function obtenerContenidoPorId(string $id): ?string {
    foreach ($_SESSION['documentos'] as $doc) {
        if ($doc['id'] === $id) return $doc['contenido'];
    }
    return null;
}

/**
 * Compara dos documentos y devuelve similitud de Jaccard y frases idénticas.
 */
function compararDocumentos(string $id1, string $id2): ?array {
    if ($id1 === $id2) return null;

    $doc1 = obtenerContenidoPorId($id1);
    $doc2 = obtenerContenidoPorId($id2);

    if (!$doc1 || !$doc2) return null;

    $tok1 = tokenizar($doc1);
    $tok2 = tokenizar($doc2);
    $inter = count(array_intersect($tok1, $tok2));
    $union = count(array_unique(array_merge($tok1, $tok2)));
    $jaccard = $union > 0 ? ($inter / $union) * 100 : 0;

    $fr1 = explode('.', $doc1);
    $fr2 = explode('.', $doc2);
    $igs = [];
    $fr2trim = array_map('trim', $fr2);
    foreach ($fr1 as $f) {
        $ftrim = trim($f);
        if (strlen($ftrim) > 10 && in_array($ftrim, $fr2trim, true)) {
            $igs[] = $ftrim;
        }
    }
    return ['jaccard' => number_format($jaccard, 2), 'frases' => $igs];
}

/**
 * Genera un PDF con el resumen de similitudes.
 */
function generarPdfResumen(array $res): void {
    require_once __DIR__ . '/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Resumen de similitud', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Similitud Jaccard: ' . $res['jaccard'] . '%', 0, 1);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'Frases identicas:', 0, 1);
    foreach ($res['frases'] as $f) {
        $pdf->MultiCell(0, 8, $f);
    }
    $pdf->Output('D', 'resumen.pdf');
    exit;
}

$resultado = null;

if (isset($_GET['reporte']) && isset($_SESSION['ultimo_resultado'])) {
    generarPdfResumen($_SESSION['ultimo_resultado']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar'])) {
        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        if (!empty($_FILES['archivo']['tmp_name'])) {
            $textoPdf = extraerTextoPdf($_FILES['archivo']['tmp_name']);
            if ($textoPdf !== null) $contenido = $textoPdf;
        }
        guardarDocumento($titulo, $contenido);

    } elseif (isset($_POST['comparar'])) {
        $id1 = $_POST['doc1'] ?? '';
        $id2 = $_POST['doc2'] ?? '';
        $resultado = compararDocumentos($id1, $id2);
        if ($resultado) {
            $_SESSION['ultimo_resultado'] = $resultado;
        }

    } elseif (isset($_POST['comparar_pdf'])) {
        $pdf1 = $_FILES['pdf1']['tmp_name'] ?? '';
        $pdf2 = $_FILES['pdf2']['tmp_name'] ?? '';
        $texto1 = $pdf1 ? extraerTextoPdf($pdf1) : null;
        $texto2 = $pdf2 ? extraerTextoPdf($pdf2) : null;
        if ($texto1 && $texto2) {
            // usar la lógica de comparación directamente con el texto
            $tok1 = tokenizar($texto1);
            $tok2 = tokenizar($texto2);
            $inter = count(array_intersect($tok1, $tok2));
            $union = count(array_unique(array_merge($tok1, $tok2)));
            $jaccard = $union > 0 ? ($inter / $union) * 100 : 0;

            $fr1 = explode('.', $texto1);
            $fr2 = explode('.', $texto2);
            $igs = [];
            $fr2trim = array_map('trim', $fr2);
            foreach ($fr1 as $f) {
                $ftrim = trim($f);
                if (strlen($ftrim) > 10 && in_array($ftrim, $fr2trim, true)) {
                    $igs[] = $ftrim;
                }
            }
            $resultado = ['jaccard' => number_format($jaccard, 2), 'frases' => $igs];
            $_SESSION['ultimo_resultado'] = $resultado;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detector de Plagio</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <h1 class="text-center mb-4">Detector de Plagio</h1>
    <div class="row">
        <!-- Sección: Subir Documento -->
        <div class="col-md-6">
            <div class="card shadow p-3 mb-4">
                <h4>Subir Documento</h4>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Archivo PDF (opcional)</label>
                        <input type="file" name="archivo" class="form-control" accept="application/pdf">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contenido</label>
                        <textarea name="contenido" class="form-control" rows="5"></textarea>
                    </div>
                    <button type="submit" name="guardar" class="btn btn-primary w-100">Guardar</button>
                </form>
            </div>
        </div>

        <!-- Sección: Documentos Guardados -->
        <div class="col-md-6">
            <div class="card shadow p-3 mb-3">
                <h4>Documentos Guardados</h4>
                <ul class="list-group" id="listaDocs">
                    <?php foreach ($_SESSION['documentos'] as $doc): ?>
                        <li class="list-group-item verDoc"
                            data-contenido="<?php echo htmlspecialchars($doc['contenido']); ?>"
                            data-titulo="<?php echo htmlspecialchars($doc['titulo']); ?>"
                            style="cursor:pointer;">
                            <?php echo htmlspecialchars($doc['titulo']); ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($_SESSION['documentos'])): ?>
                        <li class="list-group-item text-muted">No hay documentos todavía.</li>
                    <?php endif; ?>
                </ul>

                <!-- área donde se mostrará el contenido -->
                <div id="visor" class="mt-4" style="display:none;">
                    <h5 id="visorTitulo"></h5>
                    <p id="visorContenido"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección: Comparar Documentos Guardados -->
    <div class="card shadow p-3 mb-4">
        <h4>Comparar Documentos</h4>
        <form method="POST">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <select name="doc1" class="form-select" required>
                        <option value="">Seleccione Documento 1</option>
                        <?php foreach ($_SESSION['documentos'] as $doc): ?>
                            <option value="<?php echo $doc['id']; ?>"><?php echo htmlspecialchars($doc['titulo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 mb-3">
                    <select name="doc2" class="form-select" required>
                        <option value="">Seleccione Documento 2</option>
                        <?php foreach ($_SESSION['documentos'] as $doc): ?>
                            <option value="<?php echo $doc['id']; ?>"><?php echo htmlspecialchars($doc['titulo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="submit" name="comparar" class="btn btn-success w-100">Comparar</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Sección: Comparar PDFs -->
    <div class="card shadow p-3 mb-4">
        <h4>Comparar PDFs</h4>
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <input type="file" name="pdf1" class="form-control" accept="application/pdf" required>
                </div>
                <div class="col-md-5 mb-3">
                    <input type="file" name="pdf2" class="form-control" accept="application/pdf" required>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="submit" name="comparar_pdf" class="btn btn-success w-100">Comparar</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Sección: Resultados -->
    <?php if ($resultado): ?>
    <div class="card shadow p-3 mb-4">
        <h4>Resultados</h4>
        <p><strong>Similitud Jaccard:</strong> <?php echo $resultado['jaccard']; ?>%</p>
        <h5>Frases idénticas:</h5>
        <?php if (!empty($resultado['frases'])): ?>
            <ul>
                <?php foreach ($resultado['frases'] as $f): ?>
                    <li><?php echo htmlspecialchars($f); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No se encontraron frases idénticas.</p>
        <?php endif; ?>
        <a href="?reporte=1" class="btn btn-secondary mt-3">Descargar resumen PDF</a>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.verDoc').forEach(item => {
        item.addEventListener('click', () => {
            const t = item.getAttribute('data-titulo');
            const c = item.getAttribute('data-contenido');
            document.getElementById('visorTitulo').textContent = t;
            document.getElementById('visorContenido').textContent = c;
            document.getElementById('visor').style.display = 'block';
        });
    });
});
</script>
</body>
</html>
