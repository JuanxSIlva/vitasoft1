<?php
require_once "config/auth.php";
require_once "config/conexion.php";

$titulo = "Registrar Salida - VitaSoft";
$msg = "";
$msg_type = "success";

// ---------------------------------------------------------
// 1. ELIMINAR / ANULAR SALIDA
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'eliminar' && isset($_GET['id'])) {
    $id_eliminar = (int)$_GET['id'];
    $conexion->begin_transaction();

    try {
        $stmt_det = $conexion->prepare("SELECT producto_id, cantidad FROM detalle_salidas WHERE salida_id = ?");
        $stmt_det->bind_param("i", $id_eliminar);
        $stmt_det->execute();
        $res_det = $stmt_det->get_result();

        while ($det = $res_det->fetch_assoc()) {
            $pid = (int)$det['producto_id'];
            $cant = (int)$det['cantidad'];

            $stmt_upd = $conexion->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt_upd->bind_param("ii", $cant, $pid);
            $stmt_upd->execute();
        }

        $stmt_del_det = $conexion->prepare("DELETE FROM detalle_salidas WHERE salida_id = ?");
        $stmt_del_det->bind_param("i", $id_eliminar);
        $stmt_del_det->execute();

        $stmt_del_sal = $conexion->prepare("DELETE FROM salidas WHERE id = ?");
        $stmt_del_sal->bind_param("i", $id_eliminar);
        $stmt_del_sal->execute();

        $conexion->commit();
        $msg = "Salida #$id_eliminar eliminada y stock devuelto correctamente.";
        $msg_type = "success";
    } catch (Throwable $e) {
        $conexion->rollback();
        $msg = "Error al eliminar la salida: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// ---------------------------------------------------------
// 2. GUARDAR NUEVA SALIDA
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "guardar_salida") {
    $conexion->begin_transaction();

    try {
        $producto_id = (int)($_POST["producto_id"] ?? 0);
        $cantidad = (int)($_POST["cantidad"] ?? 0);
        $tipo_salida = trim($_POST["tipo_salida"] ?? 'Venta');
        $precio_venta = (float)($_POST["precio_venta"] ?? 0);
        $observacion = trim($_POST["observacion"] ?? '');
        $usuario_id = (int)($_SESSION["usuario_id"] ?? 1);

        if ($producto_id <= 0 || $cantidad <= 0) {
            throw new Exception("Seleccione un producto válido y una cantidad mayor a cero.");
        }

        // Verificar stock disponible con bloqueo
        $s = $conexion->prepare("SELECT stock, precio_venta FROM productos WHERE id = ? FOR UPDATE");
        $s->bind_param("i", $producto_id);
        $s->execute();
        $prod_data = $s->get_result()->fetch_assoc();
        
        if (!$prod_data) {
            throw new Exception("El producto seleccionado no existe.");
        }

        $stock_actual = (int)$prod_data['stock'];
        if ($stock_actual < $cantidad) {
            throw new Exception("Stock insuficiente. Stock disponible actual: " . $stock_actual);
        }

        if ($precio_venta <= 0) {
            $precio_venta = (float)($prod_data['precio_venta'] ?? 0);
        }

        // Insertar cabecera de salida
        $stmt = $conexion->prepare("INSERT INTO salidas (usuario_id, tipo_salida, observacion) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $usuario_id, $tipo_salida, $observacion);
        $stmt->execute();
        $salida_id = $conexion->insert_id;

        // Insertar detalle de salida
        $stmt = $conexion->prepare("INSERT INTO detalle_salidas (salida_id, producto_id, cantidad) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $salida_id, $producto_id, $cantidad);
        $stmt->execute();

        // Descontar stock del producto
        $stmt = $conexion->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
        $stmt->bind_param("ii", $cantidad, $producto_id);
        $stmt->execute();

        // Registrar en movimientos
        $stmt = $conexion->prepare("INSERT INTO movimientos (producto_id, usuario_id, tipo, cantidad, observacion) VALUES (?, ?, 'Salida', ?, ?)");
        $stmt->bind_param("iiis", $producto_id, $usuario_id, $cantidad, $observacion);
        $stmt->execute();

        $conexion->commit();
        $msg = "¡Salida registrada correctamente y stock actualizado!";
        $msg_type = "success";
    } catch (Throwable $e) {
        $conexion->rollback();
        $msg = "Error al registrar la salida: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// ---------------------------------------------------------
// CONSULTAS
// ---------------------------------------------------------
$busqueda = isset($_GET['search']) ? trim($_GET['search']) : '';

$prods = $conexion->query("SELECT id, codigo, nombre, stock, precio_venta FROM productos WHERE estado = 'Activo' ORDER BY nombre ASC");

$sql_historial = "
    SELECT 
        s.id,
        s.fecha,
        s.tipo_salida,
        s.observacion,
        p.id AS producto_id,
        p.nombre AS producto,
        ds.cantidad,
        COALESCE(p.precio_venta, 0) AS precio_unitario,
        u.nombre AS usuario
    FROM salidas s
    INNER JOIN detalle_salidas ds ON s.id = ds.salida_id
    INNER JOIN productos p ON p.id = ds.producto_id
    INNER JOIN usuarios u ON u.id = s.usuario_id
";

if (!empty($busqueda)) {
    $b_esc = $conexion->real_escape_string($busqueda);
    $sql_historial .= " WHERE p.nombre LIKE '%$b_esc%' OR p.codigo LIKE '%$b_esc%' OR s.tipo_salida LIKE '%$b_esc%'";
}

$sql_historial .= " ORDER BY s.id DESC LIMIT 50";
$historial = $conexion->query($sql_historial);
$total_registros = ($historial) ? $historial->num_rows : 0;

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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
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

    .card-custom { 
      border: 1px solid #e2e8f0; 
      border-radius: 12px; 
      background: #ffffff; 
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.02); 
    }
    
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
      <a class="nav-link active" href="salidas.php"><i class="bi bi-upload fs-5"></i> Salidas</a>
      <a class="nav-link" href="inventario.php"><i class="bi bi-pie-chart fs-5"></i> Inventario</a>
      <a class="nav-link" href="reportes.php"><i class="bi bi-file-earmark-text fs-5"></i> Reportes</a>
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

        <form class="d-flex position-relative flex-grow-1 my-1" style="max-width: 420px;" action="salidas.php" method="GET">
          <input class="form-control bg-light border-0 ps-5 rounded-pill" style="font-size: 0.9rem;" type="search" name="search" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar producto, código...">
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
      <?php if (!empty($msg)): ?>
          <div class="alert alert-<?= htmlspecialchars($msg_type) ?> alert-dismissible fade show shadow-sm border-0" role="alert">
              <?= htmlspecialchars($msg) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
              <h4 class="fw-bold mb-1" style="color: #0f172a;"><i class="bi bi-upload me-2 text-primary"></i>Registrar Salida de Inventario</h4>
              <div class="text-muted small">Inicio / Salidas / <strong>Nueva Salida</strong></div>
          </div>
          <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalAyuda">
              <i class="bi bi-question-circle me-1"></i> Ayuda
          </button>
      </div>

      <!-- FORMULARIO DE REGISTRO -->
      <div class="card card-custom p-4 mb-4">
          <h6 class="fw-bold mb-3 text-secondary">Información de la Salida</h6>

          <form method="POST" action="salidas.php" id="formSalida">
              <input type="hidden" name="action" value="guardar_salida">

              <div class="row g-3">
                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Producto <span class="text-danger">*</span></label>
                      <select class="form-select" name="producto_id" id="producto_id" required onchange="actualizarPrecio()">
                          <option value="" selected disabled>Seleccione un producto...</option>
                          <?php 
                          if ($prods) {
                              $prods->data_seek(0);
                              while ($p = $prods->fetch_assoc()): 
                          ?>
                              <option value="<?= $p["id"] ?>" data-precio="<?= $p["precio_venta"] ?? 0 ?>" data-stock="<?= $p["stock"] ?>">
                                  <?= htmlspecialchars($p["codigo"] . " - " . $p["nombre"] . " (Stock: " . $p["stock"] . ")") ?>
                              </option>
                          <?php 
                              endwhile; 
                          }
                          ?>
                      </select>
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Cantidad <span class="text-danger">*</span></label>
                      <input type="number" min="1" name="cantidad" id="cantidad" class="form-control" placeholder="Ingrese cantidad..." required oninput="calcularTotal()">
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Motivo / Tipo</label>
                      <select class="form-select" name="tipo_salida">
                          <option value="Venta">Venta</option>
                          <option value="Vencimiento">Vencimiento</option>
                          <option value="Dañado">Dañado</option>
                          <option value="Ajuste">Ajuste</option>
                          <option value="Otro">Otro</option>
                      </select>
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Precio de Venta ($)</label>
                      <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input type="number" step="0.01" min="0" name="precio_venta" id="precio_venta" class="form-control" placeholder="0.00" oninput="calcularTotal()">
                      </div>
                  </div>

                  <div class="col-md-6">
                      <label class="form-label fw-bold small text-muted">Observación</label>
                      <textarea name="observacion" class="form-control" rows="2" placeholder="Observaciones adicionales..."></textarea>
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Fecha</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                          <input type="text" class="form-control bg-light" value="<?= date('d/m/Y') ?>" readonly>
                      </div>
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Total ($)</label>
                      <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input type="text" id="total_calculado" class="form-control bg-light fw-bold text-success" value="0.00" readonly>
                      </div>
                  </div>
              </div>

              <div class="mt-4 d-flex gap-2">
                  <button type="submit" class="btn btn-success px-4 fw-semibold">
                      <i class="bi bi-floppy2 me-1"></i> Guardar Salida
                  </button>
                  <button type="reset" class="btn btn-outline-secondary px-3">
                      <i class="bi bi-x-lg me-1"></i> Cancelar
                  </button>
              </div>
          </form>
      </div>

      <!-- HISTORIAL DE SALIDAS -->
      <div class="card card-custom overflow-hidden">
          <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
              <strong class="text-dark">Historial de Salidas</strong>
          </div>

          <div class="table-responsive">
              <table class="table table-hover align-middle mb-0 table-custom">
                  <thead>
                      <tr>
                          <th>Fecha</th>
                          <th>Producto</th>
                          <th>Cantidad</th>
                          <th>Precio Unitario</th>
                          <th>Total</th>
                          <th>Motivo</th>
                          <th>Usuario</th>
                          <th class="text-center">Acciones</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if ($historial && $historial->num_rows > 0): ?>
                          <?php while ($row = $historial->fetch_assoc()): 
                              $total_fila = $row['cantidad'] * $row['precio_unitario'];
                          ?>
                              <tr>
                                  <td class="text-muted"><?= date('d/m/Y h:i A', strtotime($row['fecha'])) ?></td>
                                  <td class="fw-bold text-dark"><?= htmlspecialchars($row['producto']) ?></td>
                                  <td><span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 fs-6"><?= $row['cantidad'] ?></span></td>
                                  <td>$ <?= number_format($row['precio_unitario'], 2) ?></td>
                                  <td class="fw-bold text-success">$ <?= number_format($total_fila, 2) ?></td>
                                  <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['tipo_salida']) ?></span></td>
                                  <td class="text-muted"><?= htmlspecialchars($row['usuario']) ?></td>
                                  <td class="text-center">
                                      <div class="d-flex gap-1 justify-content-center">
                                          <button type="button" class="btn btn-sm btn-primary rounded-2 px-2 btn-ver-salida" title="Ver"
                                              data-id="<?= (int)$row['id'] ?>"
                                              data-fecha="<?= htmlspecialchars($row['fecha'], ENT_QUOTES, 'UTF-8') ?>"
                                              data-producto="<?= htmlspecialchars($row['producto'], ENT_QUOTES, 'UTF-8') ?>"
                                              data-cantidad="<?= (int)$row['cantidad'] ?>"
                                              data-precio="<?= htmlspecialchars(number_format($row['precio_unitario'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                              data-total="<?= htmlspecialchars(number_format($total_fila, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                              data-tipo="<?= htmlspecialchars($row['tipo_salida'], ENT_QUOTES, 'UTF-8') ?>"
                                              data-usuario="<?= htmlspecialchars($row['usuario'], ENT_QUOTES, 'UTF-8') ?>"
                                              data-observacion="<?= htmlspecialchars($row['observacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                              <i class="bi bi-eye"></i>
                                          </button>

                                          <a href="salidas.php?action=eliminar&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger rounded-2 px-2" title="Eliminar" onclick="return confirm('¿Está seguro de eliminar esta salida y reponer el stock?')">
                                              <i class="bi bi-trash"></i>
                                          </a>
                                      </div>
                                  </td>
                              </tr>
                          <?php endwhile; ?>
                      <?php else: ?>
                          <tr>
                              <td colspan="8" class="text-center py-5 text-muted">
                                  <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                                  <div>No se encontraron registros de salidas.</div>
                              </td>
                          </tr>
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

<!-- MODAL VER SALIDA -->
<div class="modal fade" id="modalVerSalida" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-file-earmark-text me-2 text-primary"></i> Detalle de Salida <span id="ver-id"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2"><strong>Fecha:</strong> <span id="ver-fecha" class="text-muted"></span></p>
                <p class="mb-2"><strong>Producto:</strong> <span id="ver-producto" class="text-dark fw-semibold"></span></p>
                <p class="mb-2"><strong>Cantidad:</strong> <span id="ver-cantidad" class="badge bg-primary"></span></p>
                <p class="mb-2"><strong>Precio Unitario:</strong> $<span id="ver-precio"></span></p>
                <p class="mb-2"><strong>Total Venta:</strong> $<span id="ver-total" class="fw-bold text-success"></span></p>
                <p class="mb-2"><strong>Motivo:</strong> <span id="ver-tipo" class="badge bg-light text-dark border"></span></p>
                <p class="mb-2"><strong>Registrado por:</strong> <span id="ver-usuario" class="text-muted"></span></p>
                <p class="mb-0"><strong>Observaciones:</strong> <span id="ver-observacion" class="text-muted"></span></p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AYUDA -->
<div class="modal fade" id="modalAyuda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-question-circle me-2 text-primary"></i> Ayuda - Registro de Salidas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-muted">
                <p>Aquí puedes registrar las salidas del inventario por motivo de <strong>Venta</strong>, <strong>Vencimiento</strong>, <strong>Dañado</strong> u otros.</p>
                <p class="mb-0">El sistema descontará automáticamente la cantidad ingresada del stock actual del producto.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const btnToggle = document.getElementById("btnToggleSidebar");
    if (btnToggle) {
        btnToggle.addEventListener("click", function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    // Ver detalle de salida en modal
    document.querySelectorAll(".btn-ver-salida").forEach(function (btn) {
        btn.addEventListener("click", function () {
            document.getElementById("ver-id").textContent = "#" + this.dataset.id;
            document.getElementById("ver-fecha").textContent = this.dataset.fecha || "";
            document.getElementById("ver-producto").textContent = this.dataset.producto || "";
            document.getElementById("ver-cantidad").textContent = this.dataset.cantidad || "0";
            document.getElementById("ver-precio").textContent = this.dataset.precio || "0.00";
            document.getElementById("ver-total").textContent = this.dataset.total || "0.00";
            document.getElementById("ver-tipo").textContent = this.dataset.tipo || "Venta";
            document.getElementById("ver-usuario").textContent = this.dataset.usuario || "Sin información";
            document.getElementById("ver-observacion").textContent = this.dataset.observacion || "Sin observaciones";

            bootstrap.Modal.getOrCreateInstance(document.getElementById("modalVerSalida")).show();
        });
    });
});

function actualizarPrecio() {
    const select = document.getElementById("producto_id");
    const option = select.options[select.selectedIndex];
    const precio = option.getAttribute("data-precio") || 0;
    document.getElementById("precio_venta").value = parseFloat(precio).toFixed(2);
    calcularTotal();
}

function calcularTotal() {
    const cantidad = parseFloat(document.getElementById("cantidad").value) || 0;
    const precio = parseFloat(document.getElementById("precio_venta").value) || 0;
    const total = cantidad * precio;
    document.getElementById("total_calculado").value = total.toFixed(2);
}
</script>
</body>
</html>