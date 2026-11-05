<?php
require_once "config/auth.php";
require_once "config/conexion.php";

$titulo = "Registrar Entrada de Inventario - VitaSoft";
$msg = "";
$msg_type = "success";

// ---------------------------------------------------------
// 1. ELIMINAR / ANULAR ENTRADA
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'eliminar' && isset($_GET['id'])) {
    $id_eliminar = (int)$_GET['id'];
    $conexion->begin_transaction();

    try {
        $stmt_det = $conexion->prepare("SELECT producto_id, cantidad FROM detalle_entradas WHERE entrada_id = ?");
        $stmt_det->bind_param("i", $id_eliminar);
        $stmt_det->execute();
        $res_det = $stmt_det->get_result();

        while ($det = $res_det->fetch_assoc()) {
            $pid = (int)$det['producto_id'];
            $cant = (int)$det['cantidad'];

            $stmt_upd = $conexion->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmt_upd->bind_param("ii", $cant, $pid);
            $stmt_upd->execute();
        }

        $stmt_del_det = $conexion->prepare("DELETE FROM detalle_entradas WHERE entrada_id = ?");
        $stmt_del_det->bind_param("i", $id_eliminar);
        $stmt_del_det->execute();

        $stmt_del_ent = $conexion->prepare("DELETE FROM entradas WHERE id = ?");
        $stmt_del_ent->bind_param("i", $id_eliminar);
        $stmt_del_ent->execute();

        $conexion->commit();
        $msg = "Entrada #$id_eliminar eliminada y stock revertido correctamente.";
        $msg_type = "success";
    } catch (Throwable $e) {
        $conexion->rollback();
        $msg = "Error al eliminar la entrada: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// ---------------------------------------------------------
// 2. EDITAR ENTRADA
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "editar_entrada") {
    $conexion->begin_transaction();

    try {
        $entrada_id = (int)$_POST["entrada_id"];
        $nuevo_producto_id = (int)$_POST["producto_id"];
        $nuevo_proveedor_id = (int)$_POST["proveedor_id"];
        $nueva_cantidad = (int)$_POST["cantidad"];
        $nuevo_precio_compra = (float)$_POST["precio_compra"];
        $factura = trim($_POST["factura"]);
        $observacion = trim($_POST["observacion"]);

        if ($entrada_id <= 0 || $nuevo_producto_id <= 0 || $nuevo_proveedor_id <= 0 || $nueva_cantidad <= 0 || $nuevo_precio_compra <= 0) {
            throw new Exception("Por favor proporcione valores válidos para todos los campos.");
        }

        $stmt_old = $conexion->prepare("SELECT producto_id, cantidad FROM detalle_entradas WHERE entrada_id = ?");
        $stmt_old->bind_param("i", $entrada_id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result()->fetch_assoc();

        if (!$res_old) {
            throw new Exception("No se encontró el detalle de la entrada especificada.");
        }

        $viejo_producto_id = (int)$res_old['producto_id'];
        $vieja_cantidad = (int)$res_old['cantidad'];

        if ($viejo_producto_id === $nuevo_producto_id) {
            $diferencia = $nueva_cantidad - $vieja_cantidad;
            if ($diferencia != 0) {
                $stmt_adj = $conexion->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                $stmt_adj->bind_param("ii", $diferencia, $nuevo_producto_id);
                $stmt_adj->execute();
            }
        } else {
            $stmt_rev = $conexion->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmt_rev->bind_param("ii", $vieja_cantidad, $viejo_producto_id);
            $stmt_rev->execute();

            $stmt_add = $conexion->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt_add->bind_param("ii", $nueva_cantidad, $nuevo_producto_id);
            $stmt_add->execute();
        }

        $stmt_prc = $conexion->prepare("UPDATE productos SET precio_compra = ? WHERE id = ?");
        $stmt_prc->bind_param("di", $nuevo_precio_compra, $nuevo_producto_id);
        $stmt_prc->execute();

        $stmt_ent = $conexion->prepare("UPDATE entradas SET proveedor_id = ?, numero_factura = ?, observacion = ? WHERE id = ?");
        $stmt_ent->bind_param("issi", $nuevo_proveedor_id, $factura, $observacion, $entrada_id);
        $stmt_ent->execute();

        $stmt_det = $conexion->prepare("UPDATE detalle_entradas SET producto_id = ?, cantidad = ?, precio_compra = ? WHERE entrada_id = ?");
        $stmt_det->bind_param("iidi", $nuevo_producto_id, $nueva_cantidad, $nuevo_precio_compra, $entrada_id);
        $stmt_det->execute();

        $conexion->commit();
        $msg = "Entrada #$entrada_id actualizada con éxito y stock reajustado.";
        $msg_type = "success";
    } catch (Throwable $e) {
        $conexion->rollback();
        $msg = "Error al editar la entrada: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// ---------------------------------------------------------
// 3. GUARDAR NUEVA ENTRADA
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "guardar_entrada") {
    $conexion->begin_transaction();

    try {
        $producto_id = (int)($_POST["producto_id"] ?? 0);
        $cantidad = (int)($_POST["cantidad"] ?? 0);
        $proveedor_id = (int)($_POST["proveedor_id"] ?? 0);
        $lote_factura = trim($_POST["lote_factura"] ?? '');
        $precio_compra = (float)($_POST["precio_compra"] ?? 0);
        $observacion = trim($_POST["observacion"] ?? '');
        $usuario_id = (int)($_SESSION["usuario_id"] ?? 1);

        if ($producto_id <= 0 || $cantidad <= 0 || $proveedor_id <= 0 || $precio_compra <= 0) {
            throw new Exception("Complete los campos obligatorios con valores válidos.");
        }

        $stmt = $conexion->prepare("INSERT INTO entradas (proveedor_id, usuario_id, numero_factura, observacion) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $proveedor_id, $usuario_id, $lote_factura, $observacion);
        $stmt->execute();
        $entrada_id = $conexion->insert_id;

        $stmt = $conexion->prepare("INSERT INTO detalle_entradas (entrada_id, producto_id, cantidad, precio_compra) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $entrada_id, $producto_id, $cantidad, $precio_compra);
        $stmt->execute();

        $stmt = $conexion->prepare("UPDATE productos SET stock = stock + ?, precio_compra = ? WHERE id = ?");
        $stmt->bind_param("idi", $cantidad, $precio_compra, $producto_id);
        $stmt->execute();

        $stmt = $conexion->prepare("INSERT INTO movimientos (producto_id, usuario_id, tipo, cantidad, observacion) VALUES (?, ?, 'Entrada', ?, ?)");
        $stmt->bind_param("iiis", $producto_id, $usuario_id, $cantidad, $observacion);
        $stmt->execute();

        $conexion->commit();
        $msg = "¡Entrada registrada correctamente y stock actualizado!";
        $msg_type = "success";
    } catch (Throwable $e) {
        $conexion->rollback();
        $msg = "Error al registrar la entrada: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// ---------------------------------------------------------
// CONSULTAS
// ---------------------------------------------------------
$busqueda = isset($_GET['search']) ? trim($_GET['search']) : '';

$provs = $conexion->query("SELECT id, nombre FROM proveedores WHERE estado = 'Activo' ORDER BY nombre ASC");
$prods = $conexion->query("SELECT id, codigo, nombre FROM productos WHERE estado = 'Activo' ORDER BY nombre ASC");
$sql_historial = "
    SELECT 
        e.id,
        e.fecha,
        e.numero_factura,
        e.observacion,
        e.proveedor_id,
        p.id AS producto_id,
        p.nombre AS producto,
        de.cantidad,
        de.precio_compra,
        pr.nombre AS proveedor,
        u.nombre AS usuario
    FROM entradas e
    INNER JOIN detalle_entradas de ON e.id = de.entrada_id
    INNER JOIN productos p ON p.id = de.producto_id
    INNER JOIN proveedores pr ON pr.id = e.proveedor_id
    INNER JOIN usuarios u ON u.id = e.usuario_id
";

if (!empty($busqueda)) {
    $b_esc = $conexion->real_escape_string($busqueda);
    $sql_historial .= " WHERE p.nombre LIKE '%$b_esc%' OR p.codigo LIKE '%$b_esc%' OR pr.nombre LIKE '%$b_esc%' OR e.numero_factura LIKE '%$b_esc%'";
}

$sql_historial .= " ORDER BY e.id DESC LIMIT 50";
$historial = $conexion->query($sql_historial);

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
      <a class="nav-link active" href="entradas.php"><i class="bi bi-download fs-5"></i> Entradas</a>
      <a class="nav-link" href="salidas.php"><i class="bi bi-upload fs-5"></i> Salidas</a>
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

        <form class="d-flex position-relative flex-grow-1 my-1" style="max-width: 420px;" action="entradas.php" method="GET">
          <input class="form-control bg-light border-0 ps-5 rounded-pill" style="font-size: 0.9rem;" type="search" name="search" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar producto, código o proveedor...">
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
              <h4 class="fw-bold mb-1" style="color: #0f172a;"><i class="fa-solid fa-circle-down me-2 text-primary"></i>Registrar Entrada de Inventario</h4>
              <div class="text-muted small">Inicio / Entradas / <strong>Nueva Entrada</strong></div>
          </div>
      </div>

      <!-- FORMULARIO NUEVA ENTRADA -->
      <div class="card card-custom p-4 mb-4">
          <h6 class="fw-bold mb-3 text-secondary">Información de la Entrada</h6>

          <form method="POST" action="entradas.php">
              <input type="hidden" name="action" value="guardar_entrada">

              <div class="row g-3">
                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Producto <span class="text-danger">*</span></label>
                      <select class="form-select" name="producto_id" required>
                          <option value="" selected disabled>Seleccione un producto...</option>
                          <?php 
                          if ($prods) {
                              $prods->data_seek(0);
                              while ($p = $prods->fetch_assoc()): 
                          ?>
                              <option value="<?= $p["id"] ?>">
                                  <?= htmlspecialchars($p["codigo"] . " - " . $p["nombre"]) ?>
                              </option>
                          <?php 
                              endwhile; 
                          }
                          ?>
                      </select>
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Cantidad <span class="text-danger">*</span></label>
                      <input type="number" min="1" name="cantidad" class="form-control" placeholder="Ingrese cantidad..." required>
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Proveedor <span class="text-danger">*</span></label>
                      <select class="form-select" name="proveedor_id" required>
                          <option value="" selected disabled>Seleccione proveedor...</option>
                          <?php 
                          if ($provs) {
                              $provs->data_seek(0);
                              while ($pr = $provs->fetch_assoc()): 
                          ?>
                              <option value="<?= $pr["id"] ?>">
                                  <?= htmlspecialchars($pr["nombre"]) ?>
                              </option>
                          <?php 
                              endwhile; 
                          }
                          ?>
                      </select>
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Lote / Nº Factura</label>
                      <input type="text" name="lote_factura" class="form-control" placeholder="Nº Factura...">
                  </div>

                  <div class="col-md-3">
                      <label class="form-label fw-bold small text-muted">Precio de Compra <span class="text-danger">*</span></label>
                      <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input type="number" step="0.01" min="0" name="precio_compra" class="form-control" placeholder="0.00" required>
                      </div>
                  </div>

                  <div class="col-md-9">
                      <label class="form-label fw-bold small text-muted">Observación</label>
                      <input type="text" name="observacion" class="form-control" placeholder="Observaciones adicionales...">
                  </div>
              </div>

              <div class="mt-4 d-flex gap-2">
                  <button type="submit" class="btn btn-success px-4 fw-semibold">
                      <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Entrada
                  </button>
                  <button type="reset" class="btn btn-outline-secondary px-3">
                      <i class="fa-solid fa-xmark me-1"></i> Cancelar
                  </button>
              </div>
          </form>
      </div>

      <!-- HISTORIAL -->
      <div class="card card-custom overflow-hidden">
          <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
              <strong class="text-dark">Historial de Entradas</strong>
          </div>

          <div class="table-responsive">
              <table class="table table-hover align-middle mb-0 table-custom" id="tabla-entradas">
                  <thead>
                      <tr>
                          <th>Fecha</th>
                          <th>Producto</th>
                          <th>Factura</th>
                          <th>Proveedor</th>
                          <th>Cantidad</th>
                          <th>Precio Compra</th>
                          <th>Usuario</th>
                          <th class="text-center">Acciones</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if ($historial && $historial->num_rows > 0): ?>
                          <?php while ($row = $historial->fetch_assoc()): ?>
                              <tr>
                                  <td class="text-muted"><?= date('d/m/Y h:i A', strtotime($row['fecha'])) ?></td>
                                  <td class="fw-bold text-dark"><?= htmlspecialchars($row['producto']) ?></td>
                                  <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['numero_factura'] ?: 'N/A') ?></span></td>
                                  <td><?= htmlspecialchars($row['proveedor']) ?></td>
                                  <td><span class="badge bg-success bg-opacity-10 text-success px-2 py-1 fs-6"><?= $row['cantidad'] ?></span></td>
                                  <td class="fw-bold">$ <?= number_format($row['precio_compra'], 2) ?></td>
                                  <td class="text-muted"><?= htmlspecialchars($row['usuario']) ?></td>
                                  <td class="text-center">
                                      <div class="d-flex gap-1 justify-content-center">
                                          <button type="button" class="btn btn-sm btn-primary btn-ver-entrada rounded-2 px-2" title="Ver entrada"
                                              data-id="<?= (int)$row['id'] ?>"
                                              data-fecha="<?= htmlspecialchars($row['fecha'], ENT_QUOTES, 'UTF-8') ?>"
                                              data-producto="<?= htmlspecialchars($row['producto'], ENT_QUOTES, 'UTF-8') ?>"
                                              data-proveedor="<?= htmlspecialchars($row['proveedor'], ENT_QUOTES, 'UTF-8') ?>"
                                              data-cantidad="<?= (int)$row['cantidad'] ?>"
                                              data-precio="<?= htmlspecialchars(number_format($row['precio_compra'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                              data-factura="<?= htmlspecialchars($row['numero_factura'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                              data-usuario="<?= htmlspecialchars($row['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                              data-observacion="<?= htmlspecialchars($row['observacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                              <i class="fa-solid fa-eye"></i>
                                          </button>

                                          <button type="button" class="btn btn-sm btn-success btn-editar-entrada rounded-2 px-2" title="Editar entrada"
                                              data-id="<?= (int)$row['id'] ?>"
                                              data-producto-id="<?= (int)$row['producto_id'] ?>"
                                              data-proveedor-id="<?= (int)$row['proveedor_id'] ?>"
                                              data-cantidad="<?= (int)$row['cantidad'] ?>"
                                              data-precio="<?= htmlspecialchars(number_format($row['precio_compra'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                              data-factura="<?= htmlspecialchars($row['numero_factura'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                              data-observacion="<?= htmlspecialchars($row['observacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                              <i class="fa-solid fa-pen"></i>
                                          </button>

                                          <button type="button" class="btn btn-sm btn-danger btn-eliminar-entrada rounded-2 px-2" title="Eliminar entrada"
                                              data-id="<?= (int)$row['id'] ?>">
                                              <i class="fa-solid fa-trash"></i>
                                          </button>
                                      </div>
                                  </td>
                              </tr>
                          <?php endwhile; ?>
                      <?php else: ?>
                          <tr>
                              <td colspan="8" class="text-center py-5 text-muted">
                                  <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                                  <div>No se encontraron registros de entradas.</div>
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

<!-- MODAL VER -->
<div class="modal fade" id="modalVer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-file-invoice me-2 text-primary"></i> Detalle de Entrada <span id="ver-id"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2"><strong>Fecha:</strong> <span id="ver-fecha" class="text-muted"></span></p>
                <p class="mb-2"><strong>Producto:</strong> <span id="ver-producto" class="text-dark fw-semibold"></span></p>
                <p class="mb-2"><strong>Proveedor:</strong> <span id="ver-proveedor" class="text-muted"></span></p>
                <p class="mb-2"><strong>Cantidad:</strong> <span id="ver-cantidad" class="badge bg-success"></span></p>
                <p class="mb-2"><strong>Precio Compra:</strong> $<span id="ver-precio" class="fw-semibold"></span></p>
                <p class="mb-2"><strong>Factura / Lote:</strong> <span id="ver-factura" class="text-muted"></span></p>
                <p class="mb-2"><strong>Registrado por:</strong> <span id="ver-usuario" class="text-muted"></span></p>
                <p class="mb-0"><strong>Observaciones:</strong> <span id="ver-observacion" class="text-muted"></span></p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form method="POST" action="entradas.php">
                <input type="hidden" name="action" value="editar_entrada">
                <input type="hidden" name="entrada_id" id="edit-id">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="fa-solid fa-pen-to-square me-2 text-success"></i> Editar Entrada # <span id="edit-id-label"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Producto <span class="text-danger">*</span></label>
                            <select class="form-select" name="producto_id" id="edit-producto-id" required>
                                <?php 
                                if ($prods) {
                                    $prods->data_seek(0);
                                    while ($p = $prods->fetch_assoc()): 
                                ?>
                                    <option value="<?= $p["id"] ?>">
                                        <?= htmlspecialchars($p["codigo"] . " - " . $p["nombre"]) ?>
                                    </option>
                                <?php 
                                    endwhile; 
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Proveedor <span class="text-danger">*</span></label>
                            <select class="form-select" name="proveedor_id" id="edit-proveedor-id" required>
                                <?php 
                                if ($provs) {
                                    $provs->data_seek(0);
                                    while ($pr = $provs->fetch_assoc()): 
                                ?>
                                    <option value="<?= $pr["id"] ?>">
                                        <?= htmlspecialchars($pr["nombre"]) ?>
                                    </option>
                                <?php 
                                    endwhile; 
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Cantidad / Stock <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="cantidad" id="edit-cantidad" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Precio de Compra <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" name="precio_compra" id="edit-precio-compra" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Número de Factura / Lote</label>
                            <input type="text" name="factura" id="edit-factura" class="form-control">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Observación</label>
                            <textarea name="observacion" id="edit-observacion" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
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

    // Ver entrada
    document.querySelectorAll(".btn-ver-entrada").forEach(function (btn) {
        btn.addEventListener("click", function () {
            document.getElementById("ver-id").textContent = "#" + this.dataset.id;
            document.getElementById("ver-fecha").textContent = this.dataset.fecha || "";
            document.getElementById("ver-producto").textContent = this.dataset.producto || "";
            document.getElementById("ver-proveedor").textContent = this.dataset.proveedor || "";
            document.getElementById("ver-cantidad").textContent = this.dataset.cantidad || "0";
            document.getElementById("ver-precio").textContent = this.dataset.precio || "0.00";
            document.getElementById("ver-factura").textContent = this.dataset.factura || "N/A";
            document.getElementById("ver-usuario").textContent = this.dataset.usuario || "Sin información";
            document.getElementById("ver-observacion").textContent = this.dataset.observacion || "Sin observaciones";

            bootstrap.Modal.getOrCreateInstance(document.getElementById("modalVer")).show();
        });
    });

    // Editar entrada
    document.querySelectorAll(".btn-editar-entrada").forEach(function (btn) {
        btn.addEventListener("click", function () {
            document.getElementById("edit-id").value = this.dataset.id;
            document.getElementById("edit-id-label").textContent = this.dataset.id;
            document.getElementById("edit-producto-id").value = this.dataset.productoId;
            document.getElementById("edit-proveedor-id").value = this.dataset.proveedorId;
            document.getElementById("edit-cantidad").value = this.dataset.cantidad;
            document.getElementById("edit-precio-compra").value = this.dataset.precio;
            document.getElementById("edit-factura").value = this.dataset.factura || "";
            document.getElementById("edit-observacion").value = this.dataset.observacion || "";

            bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEditar")).show();
        });
    });

    // Eliminar entrada
    document.querySelectorAll(".btn-eliminar-entrada").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const id = this.dataset.id;
            if (confirm("¿Desea eliminar la Entrada #" + id + " y descontar la cantidad del stock?")) {
                window.location.href = "entradas.php?action=eliminar&id=" + encodeURIComponent(id);
            }
        });
    });
});
</script>
</body>
</html>