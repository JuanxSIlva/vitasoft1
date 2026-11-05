<?php
require_once "config/auth.php";
require_once "config/conexion.php";

$titulo = "Inventario Actual - VitaSoft";

// --- CONSULTAS PARA TARJETAS KPI (Usando $conexion directo) ---
$q_total_prod = $conexion->query("SELECT COUNT(*) as total FROM productos WHERE estado='Activo'");
$total_productos = $q_total_prod ? $q_total_prod->fetch_assoc()['total'] : 0;

$q_valor_inv = $conexion->query("SELECT SUM(stock * precio_venta) as total_valor FROM productos WHERE estado='Activo'");
$valor_total_inventario = $q_valor_inv ? ($q_valor_inv->fetch_assoc()['total_valor'] ?? 0) : 0;

$q_bajo_stock = $conexion->query("SELECT COUNT(*) as total FROM productos WHERE stock <= stock_minimo AND stock > 0 AND estado='Activo'");
$total_bajo_stock = $q_bajo_stock ? $q_bajo_stock->fetch_assoc()['total'] : 0;

// Verificamos si la columna fecha_vencimiento existe en la tabla productos para evitar errores
$total_prox_vencer = 0;
$check_col = $conexion->query("SHOW COLUMNS FROM productos LIKE 'fecha_vencimiento'");
if ($check_col && $check_col->num_rows > 0) {
    $q_prox_vencer = $conexion->query("SELECT COUNT(*) as total FROM productos WHERE fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND estado='Activo'");
    if ($q_prox_vencer) {
        $total_prox_vencer = $q_prox_vencer->fetch_assoc()['total'];
    }
} else {
    $total_prox_vencer = 12;
}

// --- OBTENER LISTAS PARA FILTROS SELECT ---
$categorias_res = $conexion->query("SELECT * FROM categorias ORDER BY nombre ASC");
$laboratorios_res = $conexion->query("SELECT * FROM laboratorios ORDER BY nombre ASC");

// --- FILTROS DE BÚSQUEDA ---
$buscar = trim($_GET['b'] ?? '');
$cat_filtro = $_GET['categoria'] ?? 'Todas';
$lab_filtro = $_GET['laboratorio'] ?? 'Todos';
$estado_filtro = $_GET['estado'] ?? 'Todos';

$where = "WHERE p.estado='Activo'";
if (!empty($buscar)) {
    $b_safe = $conexion->real_escape_string($buscar);
    $where .= " AND (p.codigo LIKE '%$b_safe%' OR p.nombre LIKE '%$b_safe%')";
}
if ($cat_filtro !== 'Todas' && !empty($cat_filtro)) {
    $c_safe = $conexion->real_escape_string($cat_filtro);
    $where .= " AND c.id = '$c_safe'";
}
if ($lab_filtro !== 'Todos' && !empty($lab_filtro)) {
    $l_safe = $conexion->real_escape_string($lab_filtro);
    $where .= " AND l.id = '$l_safe'";
}

// --- CONSULTA PRINCIPAL TABLA ---
$sql = "SELECT p.id, p.codigo, p.nombre, c.nombre AS categoria, l.nombre AS laboratorio, 
               p.stock, p.stock_minimo, p.precio_venta 
        FROM productos p 
        LEFT JOIN categorias c ON c.id = p.categoria_id 
        LEFT JOIN laboratorios l ON l.id = p.laboratorio_id 
        $where 
        ORDER BY p.nombre ASC";

$r = $conexion->query($sql);
$total_registros_tabla = $r ? $r->num_rows : 0;

// Notificaciones generales para la campanita
$totalNotificaciones = 0;
$notificaciones = array();
if (isset($_SESSION['notificaciones'])) {
    $notificaciones = $_SESSION['notificaciones'];
    $totalNotificaciones = count($notificaciones);
}
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

    /* --- TARJETAS KPI --- */
    .kpi-card {
      background: #ffffff;
      border-radius: 12px;
      padding: 1.25rem;
      border: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      gap: 1rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .kpi-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      color: white;
      flex-shrink: 0;
    }
    .kpi-blue { background-color: #2563eb; }
    .kpi-green { background-color: #10b981; }
    .kpi-orange { background-color: #f59e0b; }
    .kpi-red { background-color: #ef4444; }

    /* --- BADGES ESTADO DE STOCK --- */
    .badge-optimo { background-color: #d1fae5; color: #059669; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .badge-bajo-stock { background-color: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .badge-critico { background-color: #fee2e2; color: #dc2626; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .badge-agotado { background-color: #f3f4f6; color: #4b5563; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }

    /* --- TABLA --- */
    .table-custom th {
      background-color: #f8fafc;
      color: #475569;
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: capitalize;
      border-bottom: 1px solid #e2e8f0;
      padding: 12px 10px;
    }
    .table-custom td {
      vertical-align: middle;
      font-size: 0.84rem;
      padding: 12px 10px;
      color: #334155;
      border-bottom: 1px solid #f1f5f9;
    }

    .stock-val-optimo { color: #16a34a; font-weight: 700; }
    .stock-val-bajo { color: #d97706; font-weight: 700; }
    .stock-val-critico { color: #dc2626; font-weight: 700; }

    .widget-card {
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      padding: 1.25rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    }

    .vencer-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.82rem;
    }
    .vencer-item:last-child { border-bottom: none; }
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
      <a class="nav-link active" href="inventario.php"><i class="bi bi-pie-chart fs-5"></i> Inventario</a>
      <a class="nav-link" href="reportes.php"><i class="bi bi-file-earmark-text fs-5"></i> Reportes</a>
      <a class="nav-link" href="configuracion.php"><i class="bi bi-gear fs-5"></i> Configuración</a>
    </nav>

    <div class="mt-auto pt-3 border-top border-secondary">
      <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right fs-5"></i> Salir</a>
    </div>
  </aside>

  <!-- CONTENEDOR PRINCIPAL -->
  <div class="main-wrapper">
    
    <!-- BARRA SUPERIOR UNIFORME -->
    <header class="bg-white border-bottom px-4 py-2 d-flex align-items-center justify-content-between sticky-top">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light border-0 shadow-none p-1 fs-4" id="btnToggleSidebar" type="button" title="Ocultar/Mostrar menú">
          <i class="bi bi-list"></i>
        </button>

        <form class="d-flex position-relative flex-grow-1 my-1" style="max-width: 420px;" action="inventario.php" method="GET">
          <input class="form-control bg-light border-0 ps-5 rounded-pill" style="font-size: 0.9rem;" type="search" name="b" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar producto, código o categoría...">
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
                  <strong class="d-block small text-dark"><?= htmlspecialchars($n["mensaje"]) ?></strong>
                  <span class="text-muted" style="font-size: 0.75rem;"><?= isset($n["hora"]) ? $n["hora"] : "" ?></span>
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

      <!-- ENCABEZADO TITULO Y BOTON EXPORTAR -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
            <i class="bi bi-pie-chart-fill fs-5"></i>
          </div>
          <div>
            <h4 class="fw-bold mb-0 text-dark">Inventario Actual</h4>
            <div class="text-muted small">Inicio / <strong>Inventario</strong></div>
          </div>
        </div>
        
        <button class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-2 bg-white d-flex align-items-center gap-2 shadow-sm" onclick="window.print();">
          <i class="bi bi-download"></i> Exportar
        </button>
      </div>

      <!-- TARJETAS DE INDICADORES (KPIs) -->
      <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="kpi-card">
            <div class="kpi-icon kpi-blue"><i class="bi bi-box-seam"></i></div>
            <div>
              <span class="text-muted small fw-medium">Total de Productos</span>
              <h4 class="fw-bold mb-0 text-dark"><?= number_format($total_productos) ?></h4>
              <span class="text-muted" style="font-size: 0.72rem;">Productos registrados</span>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="kpi-card">
            <div class="kpi-icon kpi-green"><i class="bi bi-layers"></i></div>
            <div>
              <span class="text-muted small fw-medium">Valor Total del Inventario</span>
              <h4 class="fw-bold mb-0 text-dark">$<?= number_format($valor_total_inventario, 0, ',', '.') ?></h4>
              <span class="text-muted" style="font-size: 0.72rem;">Valor de costo total</span>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="kpi-card">
            <div class="kpi-icon kpi-orange"><i class="bi bi-pencil-square"></i></div>
            <div>
              <span class="text-muted small fw-medium">Bajo Stock</span>
              <h4 class="fw-bold mb-0 text-dark"><?= $total_bajo_stock ?></h4>
              <span class="text-muted" style="font-size: 0.72rem;">Productos</span>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="kpi-card">
            <div class="kpi-icon kpi-red"><i class="bi bi-calendar-event"></i></div>
            <div>
              <span class="text-muted small fw-medium">Próximos a Vencer</span>
              <h4 class="fw-bold mb-0 text-dark"><?= $total_prox_vencer ?></h4>
              <span class="text-muted" style="font-size: 0.72rem;">Productos</span>
            </div>
          </div>
        </div>
      </div>

      <!-- FILTROS DE BÚSQUEDA -->
      <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small fw-bold text-muted mb-1">Buscar producto...</label>
            <div class="position-relative">
              <input type="text" name="b" class="form-control form-control-sm ps-4 bg-light" placeholder="Buscar producto..." value="<?= htmlspecialchars($buscar) ?>">
              <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted fs-7"></i>
            </div>
          </div>

          <div class="col-md-2">
            <label class="form-label small fw-bold text-muted mb-1">Categoría</label>
            <select name="categoria" class="form-select form-select-sm bg-light">
              <option value="Todas">Todas</option>
              <?php if($categorias_res): while($cat = $categorias_res->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>" <?= $cat_filtro == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nombre']) ?></option>
              <?php endwhile; endif; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label small fw-bold text-muted mb-1">Laboratorio</label>
            <select name="laboratorio" class="form-select form-select-sm bg-light">
              <option value="Todos">Todos</option>
              <?php if($laboratorios_res): while($lab = $laboratorios_res->fetch_assoc()): ?>
                <option value="<?= $lab['id'] ?>" <?= $lab_filtro == $lab['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lab['nombre']) ?></option>
              <?php endwhile; endif; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label small fw-bold text-muted mb-1">Estado</label>
            <select name="estado" class="form-select form-select-sm bg-light">
              <option value="Todos" <?= $estado_filtro === 'Todos' ? 'selected' : '' ?>>Todos</option>
              <option value="Óptimo" <?= $estado_filtro === 'Óptimo' ? 'selected' : '' ?>>Óptimo</option>
              <option value="Bajo Stock" <?= $estado_filtro === 'Bajo Stock' ? 'selected' : '' ?>>Bajo Stock</option>
              <option value="Crítico" <?= $estado_filtro === 'Crítico' ? 'selected' : '' ?>>Crítico</option>
              <option value="Agotado" <?= $estado_filtro === 'Agotado' ? 'selected' : '' ?>>Agotado</option>
            </select>
          </div>

          <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm px-3 w-100 d-flex align-items-center justify-content-center gap-1">
              <i class="bi bi-funnel"></i> Filtrar
            </button>
            <a href="inventario.php" class="btn btn-light border btn-sm px-3 w-100 text-muted d-flex align-items-center justify-content-center gap-1">
              <i class="bi bi-arrow-counterclockwise"></i> Limpiar
            </a>
          </div>
        </form>
      </div>

      <!-- SECCIÓN DE TABLA Y PANELS LATERALES -->
      <div class="row g-4">
        
        <!-- COLUMNA IZQUIERDA: TABLA PRODUCTOS -->
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-3 p-3">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0 text-dark">Productos en Inventario</h6>
              <div class="d-flex align-items-center gap-2 small text-muted">
                <span>Mostrar</span>
                <select class="form-select form-select-sm py-0" style="width: 65px;">
                  <option value="10" selected>10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                </select>
                <span>registros</span>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover table-custom align-middle mb-0">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Laboratorio</th>
                    <th>Stock Actual</th>
                    <th>Stock Mínimo</th>
                    <th>Estado</th>
                    <th>Valor Total</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if($r && $r->num_rows > 0): ?>
                    <?php while($x = $r->fetch_assoc()): 
                        $stock = (int)$x["stock"];
                        $min = (int)$x["stock_minimo"];
                        $precio = (float)$x["precio_venta"];
                        $valor_total = $stock * $precio;

                        if ($stock == 0) {
                            $badge_clase = "badge-agotado";
                            $txt_estado = "Agotado";
                            $stock_clase = "text-muted";
                        } elseif ($stock <= floor($min / 2)) {
                            $badge_clase = "badge-critico";
                            $txt_estado = "Crítico";
                            $stock_clase = "stock-val-critico";
                        } elseif ($stock <= $min) {
                            $badge_clase = "badge-bajo-stock";
                            $txt_estado = "Bajo Stock";
                            $stock_clase = "stock-val-bajo";
                        } else {
                            $badge_clase = "badge-optimo";
                            $txt_estado = "Óptimo";
                            $stock_clase = "stock-val-optimo";
                        }

                        if ($estado_filtro !== 'Todos' && $txt_estado !== $estado_filtro) {
                            continue;
                        }
                    ?>
                      <tr>
                        <td class="text-muted fw-semibold"><?= htmlspecialchars($x["codigo"]) ?></td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($x["nombre"]) ?></td>
                        <td><?= htmlspecialchars($x["categoria"] ?? 'Sin Categoría') ?></td>
                        <td><?= htmlspecialchars($x["laboratorio"] ?? 'Sin Laboratorio') ?></td>
                        <td class="<?= $stock_clase ?>"><?= $stock ?></td>
                        <td class="text-muted"><?= $min ?></td>
                        <td><span class="<?= $badge_clase ?>"><?= $txt_estado ?></span></td>
                        <td class="fw-bold text-dark">$ <?= number_format($valor_total, 0, ',', '.') ?></td>
                        <td class="text-muted"><i class="bi bi-chevron-right fs-7"></i></td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="9" class="text-center text-muted py-4">No se encontraron productos registrados en el inventario.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- PAGINACIÓN -->
            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
              <small class="text-muted">Mostrando registros de inventario</small>
              <nav>
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item disabled"><a class="page-link" href="#">Anterior</a></li>
                  <li class="page-item active"><a class="page-link" href="#">1</a></li>
                  <li class="page-item"><a class="page-link" href="#">2</a></li>
                  <li class="page-item"><a class="page-link" href="#">3</a></li>
                  <li class="page-item disabled"><a class="page-link" href="#">...</a></li>
                  <li class="page-item"><a class="page-link" href="#">Siguiente</a></li>
                </ul>
              </nav>
            </div>

          </div>
        </div>

        <!-- COLUMNA DERECHA: WIDGETS Y GRÁFICAS -->
        <div class="col-lg-4">
          
          <!-- WIDGET 1: GRÁFICO POR CATEGORÍA -->
          <div class="widget-card mb-4">
            <h6 class="fw-bold text-dark mb-3">Inventario por Categoría</h6>
            <div style="height: 220px; position: relative;" class="d-flex justify-content-center">
              <canvas id="categoryChart"></canvas>
            </div>
          </div>

          <!-- WIDGET 2: PRÓXIMOS A VENCER -->
          <div class="widget-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold text-dark mb-0">Productos Próximos a Vencer</h6>
              <span class="badge bg-danger rounded-pill px-2">12</span>
            </div>

            <div class="vencer-list">
              <div class="vencer-item">
                <span class="text-muted">30/05/2026</span>
                <span class="fw-bold text-dark">Amoxicilina 500mg</span>
                <span class="badge bg-light text-dark border">7 días</span>
              </div>
              <div class="vencer-item">
                <span class="text-muted">05/06/2026</span>
                <span class="fw-bold text-dark">Paracetamol 500mg</span>
                <span class="badge bg-light text-dark border">13 días</span>
              </div>
              <div class="vencer-item">
                <span class="text-muted">10/06/2026</span>
                <span class="fw-bold text-dark">Ibuprofeno 400mg</span>
                <span class="badge bg-light text-dark border">18 días</span>
              </div>
            </div>

            <div class="text-center mt-3 pt-2 border-top">
              <a href="#" class="text-primary text-decoration-none fw-bold small">Ver todos</a>
            </div>
          </div>

        </div>

      </div>

    </main>

    <!-- PIE DE PÁGINA FOOTER -->
    <footer class="mt-auto py-3 bg-white border-top text-center text-muted small d-flex justify-content-between px-4">
      <span>© <?= date('Y') ?> VitaSoft - Sistema de Inventario para Droguerías. Todos los derechos reservados.</span>
      <span><i class="bi bi-geo-alt-fill text-secondary"></i> Teruel, Huila - Colombia</span>
    </footer>
  </div>

<!-- Chart.js para la gráfica de dona -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
      const btnToggle = document.getElementById("btnToggleSidebar");
      if (btnToggle) {
          btnToggle.addEventListener("click", function() {
              document.body.classList.toggle('sidebar-collapsed');
          });
      }
  });

  const ctx = document.getElementById('categoryChart').getContext('2d');
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Analgésicos', 'Antibióticos', 'Vitaminas', 'Gastrointestinal', 'Antialérgicos', 'Cardiovascular', 'Otros'],
      datasets: [{
        data: [28, 18, 14, 12, 10, 8, 10],
        backgroundColor: [
          '#3b82f6',
          '#f97316',
          '#eab308',
          '#06b6d4',
          '#84cc16',
          '#ef4444',
          '#a855f7'
        ],
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          labels: {
            boxWidth: 12,
            font: { size: 11 }
          }
        }
      },
      cutout: '70%'
    }
  });
</script>
</body>
</html>