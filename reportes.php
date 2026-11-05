<?php
require_once "config/auth.php";
require_once "config/conexion.php";

$titulo = "Reportes - VitaSoft";
$conn =$conexion;

/* =========================================================
   DATOS PRINCIPALES
   ========================================================= */
function contarRegistros(mysqli $conn, string $tabla): int {$permitidas = ["entradas", "salidas", "movimientos"];
    if (!in_array($tabla,$permitidas, true)) {
        return 0;
    }

    $resultado =$conn->query("SELECT COUNT(*) AS total FROM `$tabla`");
    if (!$resultado) {
        return 0;
    }

    $fila =$resultado->fetch_assoc();
    return (int)($fila["total"] ?? 0);
}

$entradas = contarRegistros($conn, "entradas");
$salidas = contarRegistros($conn, "salidas");
$movimientos = contarRegistros($conn, "movimientos");

/* =========================================================
   NOTIFICACIONES
   ========================================================= */
$notificaciones = [];
$notificacionesBajoStock = 0;
$notificacionesVencimiento = 0;

function columnasTabla(mysqli $conn, string $tabla): array {$permitidas = ["productos"];
    if (!in_array($tabla,$permitidas, true)) {
        return [];
    }

    $cols = [];
    $res =$conn->query("SHOW COLUMNS FROM `$tabla`");

    if ($res) {
        while ($row =$res->fetch_assoc()) {
            $cols[] = strtolower($row["Field"]);
        }
    }

    return $cols;
}

function buscarColumna(array $columnas, array$posibles): ?string {
    foreach ($posibles as$posible) {
        if (in_array(strtolower($posible),$columnas, true)) {
            return $posible;
        }
    }
    return null;
}

$columnasProductos = columnasTabla($conn, "productos");

$colNombre = buscarColumna($columnasProductos, [
    "nombre", "nombre_producto", "producto", "descripcion"
]);

$colStock = buscarColumna($columnasProductos, [
    "stock", "stock_actual", "cantidad"
]);

$colStockMin = buscarColumna($columnasProductos, [
    "stock_minimo", "stock_min", "minimo_stock"
]);

$colVencimiento = buscarColumna($columnasProductos, [
    "fecha_vencimiento", "vencimiento", "fecha_expiracion"
]);

if ($colNombre &&$colStock && $colStockMin) {$sql = "SELECT `$colNombre` AS nombre, `$colStock` AS stock, `$colStockMin` AS minimo
            FROM productos
            WHERE `$colStock` <= `$colStockMin`
            ORDER BY `$colStock` ASC
            LIMIT 5";

    $res = $conn->query($sql);

    if ($res) {
        while ($row =$res->fetch_assoc()) {
            $notificacionesBajoStock++;
            $notificaciones[] = [
                "tipo" => "stock",
                "titulo" => "Stock bajo",
                "texto" => ($row["nombre"] ?? "Producto") . " tiene " . (int)$row["stock"] . " unidades.",
                "icono" => "bi-exclamation-triangle"
            ];
        }
    }
}

if ($colNombre && $colVencimiento) {$sql = "SELECT `$colNombre` AS nombre, `$colVencimiento` AS vencimiento
            FROM productos
            WHERE `$colVencimiento` IS NOT NULL
              AND `$colVencimiento` <> '0000-00-00'
              AND `$colVencimiento` <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND `$colVencimiento` >= CURDATE()
            ORDER BY `$colVencimiento` ASC
            LIMIT 5";

    $res = $conn->query($sql);

    if ($res) {
        while ($row =$res->fetch_assoc()) {
            $notificacionesVencimiento++;
            $fecha = !empty($row["vencimiento"])
                ? date("d/m/Y", strtotime($row["vencimiento"]))
                : "";

            $notificaciones[] = [
                "tipo" => "vencimiento",
                "titulo" => "Próximo a vencer",
                "texto" => ($row["nombre"] ?? "Producto") . " vence el " . $fecha . ".",
                "icono" => "bi-calendar-event"
            ];
        }
    }
}

$totalNotificaciones = count($notificaciones);

/* Datos para JavaScript */
$datosGrafica = [
    "entradas" => $entradas,
    "salidas" => $salidas,
    "movimientos" => $movimientos,
    "bajoStock" => $notificacionesBajoStock,
    "vencimientos" => $notificacionesVencimiento
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $titulo ?></title>
  <!-- Bootstrap 5 CSS e Iconos -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  
  <style>
    body { 
      background-color: #f8fafc !important; 
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
      color: #334155; 
      overflow-x: hidden; 
    }
    
    .sidebar { 
      width: 240px; 
      position: fixed; 
      top: 0; 
      left: 0; 
      height: 100vh; 
      background: #0b1c3d; 
      border-right: 1px solid #1e293b; 
      z-index: 1000; 
      transition: all 0.3s ease-in-out;
    }

    .main-wrapper { 
      margin-left: 240px; 
      min-height: 100vh; 
      display: flex; 
      flex-direction: column; 
      background-color: #f8fafc !important;
      transition: all 0.3s ease-in-out;
    }

    body.sidebar-collapsed .sidebar { left: -240px; }
    body.sidebar-collapsed .main-wrapper { margin-left: 0; }
    
    .nav-link { 
      color: #94a3b8; 
      font-weight: 500; 
      border-radius: 8px; 
      margin-bottom: 2px; 
      padding: 10px 14px; 
      display: flex; 
      align-items: center; 
      gap: 12px; 
    }
    .nav-link:hover { background-color: rgba(255, 255, 255, 0.08); color: #ffffff; }
    .nav-link.active { background-color: #2563eb; color: #ffffff !important; }

    /* TARJETAS Y PANELES */
    .card-custom { 
      border: 1px solid #e2e8f0; 
      border-radius: 12px; 
      background: #ffffff; 
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.02); 
    }

    .tarjeta-reporte {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      background: #fff;
      box-shadow: 0 2px 4px rgba(0,0,0,0.02);
      text-decoration: none;
      color: #0f172a;
      padding: 16px;
      display: flex;
      align-items: center;
      gap: 14px;
      transition: all 0.2s ease;
    }
    .tarjeta-reporte:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(0,0,0,0.05);
      color: #0f172a;
    }
    .tarjeta-reporte.activa {
      border: 2px solid #2563eb;
    }

    .icono-tarjeta {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      flex-shrink: 0;
    }
    .icono-azul { background: #eff6ff; color: #2563eb; }
    .icono-verde { background: #f0fdf4; color: #16a34a; }
    .icono-rojo { background: #fef2f2; color: #dc2626; }
    .icono-naranja { background: #fffbe3; color: #d97706; }
    .icono-morado { background: #faf5ff; color: #9333ea; }

    /* ESTADOS */
    .estado-ok { background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .estado-bajo { background: #fef3c7; color: #b45309; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .estado-critico { background: #fee2e2; color: #b91c1c; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }

    .table-custom th { 
      font-size: 0.8rem; 
      text-transform: uppercase; 
      letter-spacing: 0.05em; 
      color: #64748b; 
      font-weight: 600; 
      background-color: #f8fafc; 
      border-bottom: 1px solid #e2e8f0; 
      padding: 14px 16px; 
    }
    .table-custom td { 
      font-size: 0.875rem; 
      vertical-align: middle; 
      border-bottom: 1px solid #f1f5f9; 
      padding: 14px 16px; 
    }
  </style>
</head>
<body>

  <!-- NAVEGACIÓN LATERAL UNIFICADA -->
  <aside class="sidebar p-3 d-flex flex-column" id="sidebar">
    <div class="d-flex align-items-center mb-4 ps-2 gap-2">
      <img src="img/logo.png" alt="VitaSoft Logo" style="height: 38px; width: auto; object-fit: contain;">
      <div>
        <h5 class="fw-bold mb-0 text-white" style="letter-spacing: -0.5px;">VitaSoft</h5>
        <small class="text-secondary d-block" style="font-size: 0.7rem;">Sistema de Inventario</small>
      </div>
    </div>

    <nav class="nav flex-column gap-1">
      <a class="nav-link" href="inicio.php"><i class="bi bi-house-door fs-5"></i> Inicio</a>
      <a class="nav-link" href="productos.php"><i class="bi bi-box-seam fs-5"></i> Productos</a>
      <a class="nav-link" href="categorias.php"><i class="bi bi-folder fs-5"></i> Categorías</a>
      <a class="nav-link" href="laboratorios.php"><i class="bi bi-flask fs-5"></i> Laboratorios</a>
      <a class="nav-link" href="entradas.php"><i class="bi bi-download fs-5"></i> Entradas</a>
      <a class="nav-link" href="salidas.php"><i class="bi bi-upload fs-5"></i> Salidas</a>
      <a class="nav-link" href="inventario.php"><i class="bi bi-pie-chart fs-5"></i> Inventario</a>
      <a class="nav-link active" href="reportes.php"><i class="bi bi-file-earmark-text fs-5"></i> Reportes</a>
      <a class="nav-link" href="configuracion.php"><i class="bi bi-gear fs-5"></i> Configuración</a>
    </nav>

    <div class="mt-auto pt-3 border-top border-secondary">
      <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right fs-5"></i> Salir</a>
    </div>
  </aside>

  <!-- CONTENEDOR PRINCIPAL -->
  <div class="main-wrapper">
    
    <!-- BARRA SUPERIOR -->
    <header class="bg-white border-bottom px-4 py-2 d-flex align-items-center justify-content-between sticky-top">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light border-0 shadow-none p-1 fs-4" id="btnToggleSidebar" type="button" title="Ocultar/Mostrar menú">
          <i class="bi bi-list"></i>
        </button>

        <form class="d-flex position-relative flex-grow-1 my-1" style="max-width: 420px;" action="productos.php" method="GET">
          <input class="form-control bg-light border-0 ps-5 rounded-pill" style="font-size: 0.9rem;" type="search" name="b" placeholder="Buscar en el sistema...">
          <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
        </form>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- CAMPANITA NOTIFICACIONES -->
        <div class="dropdown">
          <button class="btn btn-light position-relative rounded-circle p-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bell fs-5 text-secondary"></i>
            <?php if ($totalNotificaciones > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                <?= $totalNotificaciones ?>
              </span>
            <?php endif; ?>
          </button>
          
          <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-3 mt-2" style="width: 320px;">
            <li class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
              <span class="fw-bold text-dark">Notificaciones</span>
              <span class="badge bg-danger rounded-pill"><?= $totalNotificaciones ?> Alertas</span>
            </li>
            <?php if ($totalNotificaciones === 0): ?>
              <li class="text-center text-muted py-3 small"><i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i> Sin alertas pendientes</li>
            <?php else: ?>
              <?php foreach ($notificaciones as $n): ?>
                <li class="border-bottom py-2">
                  <strong class="d-block small text-dark"><?= htmlspecialchars($n["titulo"]) ?></strong>
                  <span class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($n["texto"]) ?></span>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <!-- PERFIL USUARIO -->
        <div class="dropdown">
          <a class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark gap-2" href="#" role="button" data-bs-toggle="dropdown">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
              <i class="bi bi-person-fill fs-5"></i>
            </div>
            <div class="text-start d-none d-sm-block">
              <span class="fw-bold d-block lh-1" style="font-size: 0.85rem;"><?= htmlspecialchars($_SESSION["usuario_nombre"] ?? 'Administrador') ?></span>
              <small class="text-muted" style="font-size: 0.75rem;">Administrador</small>
            </div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
            <li><a class="dropdown-item" href="configuracion.php"><i class="bi bi-person me-2"></i> Mi Perfil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
          </ul>
        </div>
      </div>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="p-4">
      
      <!-- TÍTULO -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h4 class="fw-bold mb-1" style="color: #0f172a;"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Reportes de Inventario</h4>
          <div class="text-muted small">Inicio / <strong>Reportes</strong></div>
        </div>
        <a href="productos.php" class="btn btn-primary fw-semibold px-3 py-2 shadow-sm">
          <i class="bi bi-box-seam me-1"></i> Ir a Productos
        </a>
      </div>

      <!-- TARJETAS ACCESOS DIRECTOS -->
      <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
          <a href="inventario.php" class="tarjeta-reporte activa">
            <div class="icono-tarjeta icono-azul"><i class="bi bi-pie-chart-fill"></i></div>
            <div>
              <strong class="d-block text-dark fw-bold">Inventario General</strong>
              <small class="text-muted d-block">Resumen de stock actual.</small>
            </div>
          </a>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <a href="entradas.php" class="tarjeta-reporte">
            <div class="icono-tarjeta icono-verde"><i class="bi bi-arrow-down-circle-fill"></i></div>
            <div>
              <strong class="d-block text-dark fw-bold">Entradas</strong>
              <small class="text-muted d-block">Histórico de compras.</small>
            </div>
          </a>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <a href="salidas.php" class="tarjeta-reporte">
            <div class="icono-tarjeta icono-rojo"><i class="bi bi-arrow-up-circle-fill"></i></div>
            <div>
              <strong class="d-block text-dark fw-bold">Salidas</strong>
              <small class="text-muted d-block">Histórico de ventas.</small>
            </div>
          </a>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <a href="inventario.php" class="tarjeta-reporte">
            <div class="icono-tarjeta icono-morado"><i class="bi bi-calendar-event-fill"></i></div>
            <div>
              <strong class="d-block text-dark fw-bold">Alertas</strong>
              <small class="text-muted d-block">Control de vencimientos.</small>
            </div>
          </a>
        </div>
      </div>

      <!-- RESUMEN DE ESTADÍSTICAS -->
      <div class="card card-custom p-4 mb-4">
        <h6 class="fw-bold mb-3 text-dark">Resumen del Inventario</h6>
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <div class="border rounded-3 p-3 bg-light">
              <div class="icono-tarjeta icono-azul mb-2"><i class="bi bi-arrow-down"></i></div>
              <h3 class="fw-bold text-dark mb-0"><?= $entradas ?></h3>
              <small class="text-muted">Total Entradas</small>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="border rounded-3 p-3 bg-light">
              <div class="icono-tarjeta icono-verde mb-2"><i class="bi bi-arrow-up"></i></div>
              <h3 class="fw-bold text-dark mb-0"><?= $salidas ?></h3>
              <small class="text-muted">Total Salidas</small>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="border rounded-3 p-3 bg-light">
              <div class="icono-tarjeta icono-naranja mb-2"><i class="bi bi-arrow-repeat"></i></div>
              <h3 class="fw-bold text-dark mb-0"><?= $movimientos ?></h3>
              <small class="text-muted">Movimientos</small>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="border rounded-3 p-3 bg-light">
              <div class="icono-tarjeta icono-rojo mb-2"><i class="bi bi-exclamation-triangle"></i></div>
              <h3 class="fw-bold text-dark mb-0"><?= $notificacionesBajoStock + $notificacionesVencimiento ?></h3>
              <small class="text-muted">Alertas Detectadas</small>
            </div>
          </div>
        </div>
      </div>

      <!-- GRÁFICAS -->
      <div class="row g-4 mb-4">
        <div class="col-lg-7">
          <div class="card card-custom p-4 h-100">
            <h6 class="fw-bold text-dark mb-3">Actividad de Movimientos</h6>
            <div style="height: 280px; position: relative;">
              <canvas id="graficaBarras"></canvas>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card card-custom p-4 h-100">
            <h6 class="fw-bold text-dark mb-3">Distribución de Transacciones</h6>
            <div style="height: 280px; position: relative;" class="d-flex justify-content-center">
              <canvas id="graficaDona"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- DETALLE DEL REPORTE -->
      <div class="card card-custom overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <strong class="text-dark">Detalle Consolidado</strong>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 table-custom" id="tablaReporte">
            <thead>
              <tr>
                <th>Concepto</th>
                <th>Cantidad</th>
                <th>Porcentaje</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="fw-bold text-dark">Entradas de inventario</td>
                <td><?= $entradas ?></td>
                <td><?= ($entradas + $salidas +$movimientos) > 0 ? round(($entradas / ($entradas + $salidas +$movimientos)) * 100, 1) : 0 ?>%</td>
                <td><span class="estado-ok">Registrado</span></td>
              </tr>
              <tr>
                <td class="fw-bold text-dark">Salidas de inventario</td>
                <td><?= $salidas ?></td>
                <td><?= ($entradas + $salidas +$movimientos) > 0 ? round(($salidas / ($entradas + $salidas +$movimientos)) * 100, 1) : 0 ?>%</td>
                <td><span class="estado-ok">Registrado</span></td>
              </tr>
              <tr>
                <td class="fw-bold text-dark">Movimientos totales</td>
                <td><?= $movimientos ?></td>
                <td><?= ($entradas + $salidas +$movimientos) > 0 ? round(($movimientos / ($entradas + $salidas +$movimientos)) * 100, 1) : 0 ?>%</td>
                <td><span class="estado-ok">Registrado</span></td>
              </tr>
              <tr>
                <td class="fw-bold text-dark">Productos con stock bajo</td>
                <td><?= $notificacionesBajoStock ?></td>
                <td>-</td>
                <td><span class="<?= $notificacionesBajoStock > 0 ? 'estado-bajo' : 'estado-ok' ?>"><?= $notificacionesBajoStock > 0 ? 'Revisar' : 'Óptimo' ?></span></td>
              </tr>
              <tr>
                <td class="fw-bold text-dark">Productos próximos a vencer</td>
                <td><?= $notificacionesVencimiento ?></td>
                <td>-</td>
                <td><span class="<?= $notificacionesVencimiento > 0 ? 'estado-critico' : 'estado-ok' ?>"><?= $notificacionesVencimiento > 0 ? 'Revisar' : 'Sin alertas' ?></span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-outline-secondary px-3" id="btnImprimir">
            <i class="bi bi-printer me-1"></i> Imprimir
          </button>
          <button type="button" class="btn btn-danger px-4 fw-semibold" id="btnGenerarPDF">
            <i class="bi bi-file-pdf me-1"></i> Generar PDF
          </button>
        </div>
      </div>

    </main>

    <!-- FOOTER -->
    <footer class="bg-white border-top py-3 px-4 mt-auto">
      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center text-muted small">
        <div>© <?= date('Y') ?> VitaSoft - Sistema de Inventario para Droguerías. Todos los derechos reservados.</div>
        <div><i class="bi bi-geo-alt-fill text-danger me-1"></i> Teruel, Huila - Colombia</div>
      </div>
    </footer>
  </div>

<!-- LIBRERÍAS JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const datos = <?= json_encode($datosGrafica, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const btnToggle = document.getElementById("btnToggleSidebar");
    if (btnToggle) {
        btnToggle.addEventListener("click", function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    // Gráfica de Barras
    const ctxBarras = document.getElementById("graficaBarras");
    new Chart(ctxBarras, {
        type: "bar",
        data: {
            labels: ["Entradas", "Salidas", "Movimientos"],
            datasets: [{
                label: "Cantidad",
                data: [datos.entradas, datos.salidas, datos.movimientos],
                backgroundColor: ["#2563eb", "#16a34a", "#f59e0b"],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // Gráfica de Dona
    const ctxDona = document.getElementById("graficaDona");
    new Chart(ctxDona, {
        type: "doughnut",
        data: {
            labels: ["Entradas", "Salidas", "Movimientos"],
            datasets: [{
                data: [datos.entradas, datos.salidas, datos.movimientos],
                backgroundColor: ["#2563eb", "#16a34a", "#f59e0b"]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: "65%",
            plugins: { legend: { position: "bottom" } }
        }
    });

    // Imprimir
    document.getElementById("btnImprimir").addEventListener("click", function () {
        window.print();
    });

    // Generar PDF
    document.getElementById("btnGenerarPDF").addEventListener("click", function () {
        if (!window.jspdf) {
            alert("No fue posible cargar el generador de PDF. Verifica tu conexión a Internet.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF("p", "mm", "a4");

        pdf.setFontSize(18);
        pdf.setFont("helvetica", "bold");
        pdf.text("VitaSoft - Reporte de Inventario", 14, 18);

        pdf.setFontSize(10);
        pdf.setFont("helvetica", "normal");
        pdf.text("Fecha de generación: " + new Date().toLocaleDateString("es-CO"), 14, 25);

        pdf.setFontSize(11);
        pdf.text("Resumen General", 14, 36);

        pdf.autoTable({
            startY: 40,
            head: [["Concepto", "Cantidad", "Estado"]],
            body: [
                ["Entradas", datos.entradas, "Registrado"],
                ["Salidas", datos.salidas, "Registrado"],
                ["Movimientos", datos.movimientos, "Registrado"],
                ["Stock bajo", datos.bajoStock, datos.bajoStock > 0 ? "Revisar" : "Óptimo"],
                ["Próximos a vencer", datos.vencimientos, datos.vencimientos > 0 ? "Revisar" : "Sin alertas"]
            ],
            theme: "grid",
            headStyles: { fillColor: [37, 99, 235] }
        });

        const tablaY = pdf.lastAutoTable.finalY + 12;

        pdf.setFontSize(11);
        pdf.setFont("helvetica", "bold");
        pdf.text("Detalle Consolidado", 14, tablaY);

        const filasVisibles = [];
        document.querySelectorAll("#tablaReporte tbody tr").forEach(function (fila) {
            const celdas = fila.querySelectorAll("td");
            if (celdas.length >= 4) {
                filasVisibles.push([
                    celdas[0].innerText.trim(),
                    celdas[1].innerText.trim(),
                    celdas[2].innerText.trim(),
                    celdas[3].innerText.trim()
                ]);
            }
        });

        pdf.autoTable({
            startY: tablaY + 5,
            head: [["Concepto", "Cantidad", "Porcentaje", "Estado"]],
            body: filasVisibles,
            theme: "grid",
            headStyles: { fillColor: [37, 99, 235] }
        });

        pdf.save("reporte_vitasoft_" + new Date().toISOString().slice(0, 10) + ".pdf");
    });
});
</script>
</body>
</html>