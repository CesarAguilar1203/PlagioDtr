<?php
session_start();

// Inicializar documentos
if (!isset($_SESSION['documentos'])) {
    $_SESSION['documentos'] = [];
}

// Guardar
if (isset($_POST['guardar'])) {
    $titulo = trim($_POST['titulo']);
    $contenido = trim($_POST['contenido']);

    if ($titulo && $contenido) {
        $_SESSION['documentos'][] = [
            'id' => uniqid(),
            'titulo' => $titulo,
            'contenido' => $contenido
        ];
    }
}

// Comparar
$resultado = null;
if (isset($_POST['comparar'])) {
    $id1 = $_POST['doc1'];
    $id2 = $_POST['doc2'];

    if ($id1 != $id2) {
        $doc1 = null; $doc2 = null;
        foreach ($_SESSION['documentos'] as $doc) {
            if ($doc['id'] === $id1) $doc1 = $doc['contenido'];
            if ($doc['id'] === $id2) $doc2 = $doc['contenido'];
        }

        if ($doc1 && $doc2) {
            function tokenizar($t){
                $t = strtolower($t);
                $t = preg_replace('/[^\p{L}\p{N}\s]/u', '', $t);
                return array_unique(explode(' ', $t));
            }

            $tok1 = tokenizar($doc1);
            $tok2 = tokenizar($doc2);
            $inter = count(array_intersect($tok1,$tok2));
            $union = count(array_unique(array_merge($tok1,$tok2)));
            $j = $union>0 ? ($inter/$union)*100:0;

            $fr1 = explode('.',$doc1);
            $fr2 = explode('.',$doc2);
            $igs = [];
            foreach($fr1 as $f){
                if(in_array(trim($f),array_map('trim',$fr2)) && strlen(trim($f))>10){
                    $igs[] = trim($f);
                }
            }
            $resultado=['jaccard'=>number_format($j,2),'frases'=>$igs];
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
        <!-- subir -->
        <div class="col-md-6">
            <div class="card shadow p-3 mb-4">
                <h4>Subir Documento</h4>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contenido</label>
                        <textarea name="contenido" class="form-control" rows="5" required></textarea>
                    </div>
                    <button type="submit" name="guardar" class="btn btn-primary w-100">Guardar</button>
                </form>
            </div>
        </div>

        <!-- lista -->
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

    <!-- comparar -->
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

    <!-- resultados -->
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
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded",()=>{
    document.querySelectorAll('.verDoc').forEach(item=>{
        item.addEventListener('click',()=>{
            const t = item.getAttribute('data-titulo');
            const c = item.getAttribute('data-contenido');
            document.getElementById('visorTitulo').textContent = t;
            document.getElementById('visorContenido').textContent = c;
            document.getElementById('visor').style.display='block';
        });
    });
});
</script>
</body>
</html>
