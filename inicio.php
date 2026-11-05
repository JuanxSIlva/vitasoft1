<?php
require "config/auth.php"; 
require "config/conexion.php";

$titulo = "Inicio - VitaSoft"; 

// --- FUNCIÓN DE CONSULTA SEGURA ---
function consultarValor($conexion, $sql, $columna = 'c') {
    try {
        $res = $conexion->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            return (int)($row[$columna] ?? 0);
        }
    } catch (Throwable $e) {
        return 0;
    }
    return 0;
}

// --- CONSULTAS DASHBOARD ---
$productos       = consultarValor($conexion, "SELECT COUNT(*) c FROM productos WHERE estado='Activo'");
$bajo            = consultarValor($conexion, "SELECT COUNT(*) c FROM productos WHERE estado='Activo' AND stock <= stock_minimo");
$proximos_vencer = consultarValor($conexion, "SELECT COUNT(*) c FROM productos WHERE estado='Activo' AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$entradas_dia    = consultarValor($conexion, "SELECT COALESCE(SUM(cantidad), 0) c FROM movimientos WHERE tipo='Entrada' AND DATE(fecha) = CURDATE()");
$vencidos        = consultarValor($conexion, "SELECT COUNT(*) c FROM productos WHERE estado='Activo' AND fecha_vencimiento < CURDATE()");

// ÚLTIMOS MOVIMIENTOS
$movimientos = false;
try {
    $query_movimientos = "SELECT m.fecha, m.tipo, p.nombre AS producto, m.cantidad, u.nombre AS usuario, m.observacion 
                          FROM movimientos m
                          INNER JOIN productos p ON m.producto_id = p.id
                          LEFT JOIN usuarios u ON m.usuario_id = u.id
                          ORDER BY m.fecha DESC LIMIT 5";
    $movimientos = $conexion->query($query_movimientos);
} catch (Throwable $e) {
    $movimientos = false;
}

// DATOS DE LA GRÁFICA
$dias_rango = isset($_GET['rango']) ? (int)$_GET['rango'] : 7;
if (!in_array($dias_rango, [7, 30, 90])) { $dias_rango = 7; }

$dias_grafica = [];
$entradas_grafica = [];
$salidas_grafica = [];

for ($i = ($dias_rango - 1); $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $dias_grafica[] = date('d M', strtotime($fecha));

    $entradas_grafica[] = consultarValor($conexion, "SELECT COALESCE(SUM(cantidad), 0) total FROM movimientos WHERE tipo='Entrada' AND DATE(fecha) = '$fecha'", 'total');
    $salidas_grafica[]  = consultarValor($conexion, "SELECT COALESCE(SUM(cantidad), 0) total FROM movimientos WHERE tipo='Salida' AND DATE(fecha) = '$fecha'", 'total');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $titulo ?></title>
  <!-- Bootstrap 5 CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  
  <style>
    body { background-color: #f8fafc; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: #334155; overflow-x: hidden; }
    
    .sidebar { 
      width: 240px; 
      position: fixed; 
      top: 0; 
      left: 0; 
      height: 100vh; 
      background: #ffffff; 
      border-right: 1px solid #e2e8f0; 
      z-index: 1000; 
      transition: all 0.3s ease-in-out;
    }
    .main-wrapper { 
      margin-left: 240px; 
      min-height: 100vh; 
      display: flex; 
      flex-direction: column; 
      transition: all 0.3s ease-in-out;
    }

    body.sidebar-collapsed .sidebar { left: -240px; }
    body.sidebar-collapsed .main-wrapper { margin-left: 0; }
    
    .nav-link { color: #64748b; font-weight: 500; border-radius: 8px; margin-bottom: 2px; padding: 10px 14px; display: flex; align-items: center; gap: 12px; }
    .nav-link:hover { background-color: #f1f5f9; color: #0f172a; }
    .nav-link.active { background-color: #0d6efd; color: #ffffff !important; }
    
    .stat-card { border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; padding: 20px; transition: all 0.2s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
    .icon-box { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
    
    .bg-icon-blue { background-color: #eff6ff; color: #2563eb; }
    .bg-icon-warning { background-color: #fffbe3; color: #d97706; }
    .bg-icon-success { background-color: #f0fdf4; color: #16a34a; }
    .bg-icon-purple { background-color: #f3e8ff; color: #9333ea; }

    .alert-box { border-radius: 10px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; text-decoration: none; margin-bottom: 10px; border: 1px solid transparent; transition: all 0.2s; }
    .alert-box:hover { opacity: 0.9; }
    .alert-warning-custom { background-color: #fffbeb; border-color: #fef3c7; color: #92400e; }
    .alert-info-custom { background-color: #eff6ff; border-color: #dbeafe; color: #1e40af; }
    .alert-danger-custom { background-color: #fef2f2; border-color: #fee2e2; color: #991b1b; }

    .table-custom th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .table-custom td { font-size: 0.875rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; padding: 12px 16px; }
    .badge-entrada { background-color: #dcfce7; color: #15803d; border-radius: 20px; padding: 4px 10px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
    .badge-salida { background-color: #dbeafe; color: #1d4ed8; border-radius: 20px; padding: 4px 10px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
  </style>
</head>
<body>

  <!-- NAVEGACIÓN LATERAL -->
  <aside class="sidebar p-3 d-flex flex-column" id="sidebar">
    <div class="d-flex align-items-center mb-4 ps-2 gap-2">
      <img src="img/logo.png" alt="VitaSoft Logo" style="height: 38px; width: auto; object-fit: contain;">
      <div>
        <h5 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.5px;">VitaSoft</h5>
        <small class="text-muted d-block" style="font-size: 0.7rem;">Sistema de Inventario</small>
      </div>
    </div>

    <nav class="nav flex-column gap-1">
      <a class="nav-link active" href="inicio.php"><i class="bi bi-house-door fs-5"></i> Inicio</a>
      <a class="nav-link" href="productos.php"><i class="bi bi-box-seam fs-5"></i> Productos</a>
      <a class="nav-link" href="categorias.php"><i class="bi bi-folder fs-5"></i> Categorías</a>
      <a class="nav-link" href="laboratorios.php"><i class="bi bi-flask fs-5"></i> Laboratorios</a>
      <a class="nav-link" href="entradas.php"><i class="bi bi-download fs-5"></i> Entradas</a>
      <a class="nav-link" href="salidas.php"><i class="bi bi-upload fs-5"></i> Salidas</a>
      <a class="nav-link" href="inventario.php"><i class="bi bi-pie-chart fs-5"></i> Inventario</a>
      <a class="nav-link" href="reportes.php"><i class="bi bi-file-earmark-text fs-5"></i> Reportes</a>
      <a class="nav-link" href="configuracion.php"><i class="bi bi-gear fs-5"></i> Configuración</a>
    </nav>

    <!-- BOTÓN SALIR DIRIGIDO A LOGOUT.PHP -->
    <div class="mt-auto pt-3 border-top">
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
          <input class="form-control bg-light border-0 ps-5 rounded-pill" style="font-size: 0.9rem;" type="search" name="q" placeholder="Buscar producto, código o categoría...">
          <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
        </form>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- CAMPANITA -->
        <div class="dropdown">
          <button class="btn btn-light position-relative rounded-circle p-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bell fs-5 text-secondary"></i>
            <?php if(($bajo + $vencidos + $proximos_vencer) > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                <?= $bajo + $vencidos + $proximos_vencer ?>
              </span>
            <?php endif; ?>
          </button>
          
          <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-3 mt-2" style="width: 320px;">
            <li class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
              <span class="fw-bold text-dark">Notificaciones</span>
              <span class="badge bg-danger rounded-pill"><?= $bajo + $vencidos + $proximos_vencer ?> Alertas</span>
            </li>
            
            <?php if($bajo > 0): ?>
              <li>
                <a class="dropdown-item rounded p-2 d-flex align-items-start gap-2 text-wrap" href="productos.php?filtro=bajo_stock">
                  <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                  <div>
                    <strong class="d-block small text-dark">Stock Bajo</strong>
                    <span class="text-muted" style="font-size: 0.75rem;"><?= $bajo ?> productos requieren reposición.</span>
                  </div>
                </a>
              </li>
            <?php endif; ?>

            <?php if($proximos_vencer > 0): ?>
              <li>
                <a class="dropdown-item rounded p-2 d-flex align-items-start gap-2 text-wrap" href="alertas.php?filtro=vencimiento">
                  <i class="bi bi-calendar-event-fill text-primary fs-5"></i>
                  <div>
                    <strong class="d-block small text-dark">Próximos a Vencer</strong>
                    <span class="text-muted" style="font-size: 0.75rem;"><?= $proximos_vencer ?> productos vencen este mes.</span>
                  </div>
                </a>
              </li>
            <?php endif; ?>

            <?php if($vencidos > 0): ?>
              <li>
                <a class="dropdown-item rounded p-2 d-flex align-items-start gap-2 text-wrap" href="alertas.php?filtro=vencidos">
                  <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                  <div>
                    <strong class="d-block small text-dark">Productos Vencidos</strong>
                    <span class="text-muted" style="font-size: 0.75rem;"><?= $vencidos ?> productos han caducado.</span>
                  </div>
                </a>
              </li>
            <?php endif; ?>

            <?php if(($bajo + $vencidos + $proximos_vencer) == 0): ?>
              <li class="text-center text-muted py-3 small"><i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i> Sin alertas pendientes</li>
            <?php endif; ?>

            <li class="pt-2 mt-2 border-top text-center">
              <a href="alertas.php" class="text-primary small text-decoration-none fw-semibold">Ver todas las alertas</a>
            </li>
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
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Salir</a></li>
          </ul>
        </div>
      </div>
    </header>

    <!-- CONTENIDO DEL DASHBOARD -->
    <main class="p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h4 class="fw-bold mb-1" style="color: #0f172a;">Bienvenido, <span class="text-primary"><?= htmlspecialchars($_SESSION["usuario_nombre"] ?? 'Administrador') ?></span></h4>
          <p class="text-muted small mb-0">Resumen general de la droguería</p>
        </div>
        <div class="text-muted bg-white px-3 py-2 rounded-3 border shadow-sm small fw-medium">
          <i class="bi bi-calendar-event me-2 text-primary"></i><?= date('d \d\e F \d\e Y') ?>
        </div>
      </div>

      <!-- TARJETAS DE ESTADÍSTICAS -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-semibold d-block mb-1">Total de Productos</span>
              <h2 class="fw-bold mb-0 text-primary"><?= number_format($productos) ?></h2>
              <small class="text-muted" style="font-size: 0.75rem;">Productos registrados</small>
            </div>
            <div class="icon-box bg-icon-blue"><i class="bi bi-box-seam"></i></div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-semibold d-block mb-1">Bajo Stock</span>
              <h2 class="fw-bold mb-0 text-warning"><?= number_format($bajo) ?></h2>
              <small class="text-muted" style="font-size: 0.75rem;">Requieren reposición</small>
            </div>
            <div class="icon-box bg-icon-warning"><i class="bi bi-exclamation-triangle"></i></div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-semibold d-block mb-1">Próximos a Vencer</span>
              <h2 class="fw-bold mb-0 text-success"><?= number_format($proximos_vencer) ?></h2>
              <small class="text-muted" style="font-size: 0.75rem;">Vencen este mes</small>
            </div>
            <div class="icon-box bg-icon-success"><i class="bi bi-calendar-check"></i></div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-semibold d-block mb-1">Entradas del Día</span>
              <h2 class="fw-bold mb-0" style="color: #9333ea;"><?= number_format($entradas_dia) ?></h2>
              <small class="text-muted" style="font-size: 0.75rem;">Productos recibidos</small>
            </div>
            <div class="icon-box bg-icon-purple"><i class="bi bi-arrow-down-circle"></i></div>
          </div>
        </div>
      </div>

      <!-- GRÁFICA Y ALERTAS -->
      <div class="row g-4 mb-4">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">Entradas vs. Salidas <span class="text-muted fw-normal fs-7">(Últimos <?= $dias_rango ?> días)</span></h6>
              <select class="form-select form-select-sm w-auto border-light-subtle fw-medium" onchange="location = this.value;">
                <option value="inicio.php?rango=7" <?= $dias_rango == 7 ? 'selected' : '' ?>>Últimos 7 días</option>
                <option value="inicio.php?rango=30" <?= $dias_rango == 30 ? 'selected' : '' ?>>Últimos 30 días</option>
                <option value="inicio.php?rango=90" <?= $dias_rango == 90 ? 'selected' : '' ?>>Últimos 90 días</option>
              </select>
            </div>
            <div style="height: 250px;">
              <canvas id="chartMovimientos"></canvas>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">Alertas Importantes</h6>
              <a href="alertas.php" class="text-primary small text-decoration-none fw-semibold">Ver todas</a>
            </div>
            
            <a href="productos.php?filtro=bajo_stock" class="alert-box alert-warning-custom">
              <div class="d-flex align-items-center gap-3">
                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                <div>
                  <strong class="d-block" style="font-size: 0.85rem;">Productos con bajo stock</strong>
                  <small><?= $bajo ?> productos con stock mínimo o menos.</small>
                </div>
              </div>
              <i class="bi bi-chevron-right"></i>
            </a>

            <a href="alertas.php?filtro=vencimiento" class="alert-box alert-info-custom">
              <div class="d-flex align-items-center gap-3">
                <i class="bi bi-calendar-event-fill fs-4"></i>
                <div>
                  <strong class="d-block" style="font-size: 0.85rem;">Próximos a vencer</strong>
                  <small><?= $proximos_vencer ?> productos vencen en los próximos 30 días.</small>
                </div>
              </div>
              <i class="bi bi-chevron-right"></i>
            </a>

            <a href="alertas.php?filtro=vencidos" class="alert-box alert-danger-custom">
              <div class="d-flex align-items-center gap-3">
                <i class="bi bi-x-circle-fill fs-4"></i>
                <div>
                  <strong class="d-block" style="font-size: 0.85rem;">Productos vencidos</strong>
                  <small><?= $vencidos ?> productos ya han vencido.</small>
                </div>
              </div>
              <i class="bi bi-chevron-right"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- TABLA ÚLTIMOS MOVIMIENTOS -->
      <div class="card border-0 shadow-sm rounded-3 p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">Últimos Movimientos</h6>
          <a href="inventario.php" class="btn btn-sm btn-outline-primary rounded-2 px-3"><i class="bi bi-list-ul me-1"></i> Ver todos los movimientos</a>
        </div>
        <div class="table-responsive">
          <table class="table table-custom align-middle mb-0">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Usuario</th>
                <th>Observación</th>
              </tr>
            </thead>
            <tbody>
              <?php if($movimientos && $movimientos->num_rows > 0): ?>
                <?php while($row = $movimientos->fetch_assoc()): ?>
                  <tr>
                    <td><?= date('d/m/Y h:i A', strtotime($row['fecha'])) ?></td>
                    <td>
                      <?php if($row['tipo'] == 'Entrada'): ?>
                        <span class="badge-entrada"><i class="bi bi-arrow-down-short fs-6"></i> Entrada</span>
                      <?php else: ?>
                        <span class="badge-salida"><i class="bi bi-arrow-up-short fs-6"></i> Salida</span>
                      <?php endif; ?>
                    </td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($row['producto']) ?></td>
                    <td class="fw-semibold"><?= $row['cantidad'] ?></td>
                    <td><?= htmlspecialchars($row['usuario'] ?? 'Administrador') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['observacion']) ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay movimientos recientes registrados.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
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

  <!-- Scripts: Bootstrap JS & Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    document.getElementById('btnToggleSidebar').addEventListener('click', function() {
      document.body.classList.toggle('sidebar-collapsed');
    });

    const ctx = document.getElementById('chartMovimientos').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($dias_grafica) ?>,
        datasets: [
          {
            label: 'Entradas',
            data: <?= json_encode($entradas_grafica) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.08)',
            borderWidth: 2,
            tension: 0.35,
            fill: true,
            pointRadius: 3
          },
          {
            label: 'Salidas',
            data: <?= json_encode($salidas_grafica) ?>,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            borderWidth: 2,
            tension: 0.35,
            fill: true,
            pointRadius: 3
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } }
        },
        scales: {
          y: { beginAtZero: true, grid: { borderDash: [3, 3] } },
          x: { grid: { display: false } }
        }
      }
    });
  </script>
</body>
</html>