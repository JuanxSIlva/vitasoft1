<?php
require "config/auth.php";
require "config/conexion.php";

$titulo = "Productos";

$msg = "";
$tipoMsg = "info";

/* =========================================================
   FUNCIÓN PARA MOSTRAR MENSAJES
========================================================= */
function mensaje($texto, $tipo = "info")
{
    global $msg, $tipoMsg;
    $msg = $texto;
    $tipoMsg = $tipo;
}

/* =========================================================
   PROCESAR FORMULARIOS
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = isset($_POST["accion"]) ? $_POST["accion"] : "";

    /* =====================================================
       GUARDAR / EDITAR PRODUCTO
    ===================================================== */
    if ($accion === "guardar") {
        try {
            $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
            $codigo = isset($_POST["codigo"]) ? trim($_POST["codigo"]) : "";
            $nombre = isset($_POST["nombre"]) ? trim($_POST["nombre"]) : "";
            $categoria_id = isset($_POST["categoria_id"]) ? (int)$_POST["categoria_id"] : 0;
            $laboratorio_id = isset($_POST["laboratorio_id"]) ? (int)$_POST["laboratorio_id"] : 0;
            $precio_compra = isset($_POST["precio_compra"]) ? (float)$_POST["precio_compra"] : 0;
            $precio_venta = isset($_POST["precio_venta"]) ? (float)$_POST["precio_venta"] : 0;
            $stock = isset($_POST["stock"]) ? (int)$_POST["stock"] : 0;
            $stock_minimo = isset($_POST["stock_minimo"]) ? (int)$_POST["stock_minimo"] : 10;

            if ($codigo === "" || $nombre === "") {
                throw new Exception("Debe ingresar el código y el nombre del producto.");
            }
            if ($categoria_id <= 0) {
                throw new Exception("Seleccione una categoría.");
            }
            if ($laboratorio_id <= 0) {
                throw new Exception("Seleccione un laboratorio.");
            }
            if ($precio_compra < 0 || $precio_venta < 0) {
                throw new Exception("Los precios no pueden ser negativos.");
            }
            if ($stock < 0) {
                throw new Exception("El stock no puede ser negativo.");
            }
            if ($stock_minimo < 0) {
                throw new Exception("El stock mínimo no puede ser negativo.");
            }

            if ($id > 0) {
                $verificar = $conexion->prepare("SELECT id FROM productos WHERE codigo = ? AND id <> ? LIMIT 1");
                $verificar->bind_param("si", $codigo, $id);
            } else {
                $verificar = $conexion->prepare("SELECT id FROM productos WHERE codigo = ? LIMIT 1");
                $verificar->bind_param("s", $codigo);
            }

            $verificar->execute();
            $resultado = $verificar->get_result();

            if ($resultado->num_rows > 0) {
                throw new Exception("Ya existe un producto registrado con ese código.");
            }

            if ($id > 0) {
                $st = $conexion->prepare(
                    "UPDATE productos SET codigo = ?, nombre = ?, categoria_id = ?, laboratorio_id = ?, precio_compra = ?, precio_venta = ?, stock = ?, stock_minimo = ? WHERE id = ?"
                );
                $st->bind_param("ssiidiiii", $codigo, $nombre, $categoria_id, $laboratorio_id, $precio_compra, $precio_venta, $stock, $stock_minimo, $id);
                $st->execute();

                mensaje("El producto fue actualizado correctamente.", "success");
            } else {
                $st = $conexion->prepare(
                    "INSERT INTO productos (codigo, nombre, categoria_id, laboratorio_id, precio_compra, precio_venta, stock, stock_minimo, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Activo')"
                );
                $st->bind_param("ssiidiii", $codigo, $nombre, $categoria_id, $laboratorio_id, $precio_compra, $precio_venta, $stock, $stock_minimo);
                $st->execute();

                mensaje("El producto fue agregado correctamente.", "success");
            }
        } catch (mysqli_sql_exception $e) {
            mensaje("No fue posible guardar el producto. Revise los datos ingresados e inténtelo nuevamente.", "danger");
        } catch (Exception $e) {
            mensaje($e->getMessage(), "warning");
        }
    }

    if ($accion === "eliminar") {
        try {
            $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
            if ($id <= 0) {
                throw new Exception("Producto no válido.");
            }

            $st = $conexion->prepare("DELETE FROM productos WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();

            if ($st->affected_rows > 0) {
                mensaje("El producto fue eliminado correctamente.", "success");
            } else {
                mensaje("No se encontró el producto que desea eliminar.", "warning");
            }
        } catch (mysqli_sql_exception $e) {
            mensaje("No se puede eliminar este producto porque tiene movimientos, entradas o salidas relacionados.", "danger");
        }
    }
}

/* =========================================================
   CONSULTAS DE DATOS
========================================================= */
$cats = $conexion->query("SELECT id, nombre FROM categorias WHERE estado = 'Activo' ORDER BY nombre");
$labs = $conexion->query("SELECT id, nombre FROM laboratorios WHERE estado = 'Activo' ORDER BY nombre");

$buscar = isset($_GET["buscar"]) ? trim($_GET["buscar"]) : "";
$categoriaFiltro = isset($_GET["categoria"]) ? (int)$_GET["categoria"] : 0;
$laboratorioFiltro = isset($_GET["laboratorio"]) ? (int)$_GET["laboratorio"] : 0;
$estadoFiltro = isset($_GET["estado"]) ? trim($_GET["estado"]) : "";

$sql = "
    SELECT p.*, c.nombre AS categoria, l.nombre AS laboratorio 
    FROM productos p 
    INNER JOIN categorias c ON c.id = p.categoria_id 
    INNER JOIN laboratorios l ON l.id = p.laboratorio_id 
    WHERE 1 = 1
";

$parametros = array();
$tipos = "";

if ($buscar !== "") {
    $sql .= " AND (p.codigo LIKE ? OR p.nombre LIKE ? OR c.nombre LIKE ? OR l.nombre LIKE ?) ";
    $textoBuscar = "%" . $buscar . "%";
    $parametros[] = $textoBuscar;
    $parametros[] = $textoBuscar;
    $parametros[] = $textoBuscar;
    $parametros[] = $textoBuscar;
    $tipos .= "ssss";
}

if ($categoriaFiltro > 0) {
    $sql .= " AND p.categoria_id = ? ";
    $parametros[] = $categoriaFiltro;
    $tipos .= "i";
}

if ($laboratorioFiltro > 0) {
    $sql .= " AND p.laboratorio_id = ? ";
    $parametros[] = $laboratorioFiltro;
    $tipos .= "i";
}

if ($estadoFiltro !== "") {
    if ($estadoFiltro === "Disponible") {
        $sql .= " AND p.stock > p.stock_minimo ";
    } elseif ($estadoFiltro === "Bajo Stock") {
        $sql .= " AND p.stock > 0 AND p.stock <= p.stock_minimo ";
    } elseif ($estadoFiltro === "Agotado") {
        $sql .= " AND p.stock = 0 ";
    }
}

$sql .= " ORDER BY p.id DESC";
$stmtProductos = $conexion->prepare($sql);
if (!empty($parametros)) {
    $params = array_merge(array($tipos), $parametros);
    $tmp = array();
    foreach($params as $key => $value) $tmp[$key] = &$params[$key];
    call_user_func_array(array($stmtProductos, 'bind_param'), $tmp);
}
$stmtProductos->execute();
$rows = $stmtProductos->get_result();

/* NOTIFICACIONES */
$notificaciones = array();
$consultaNotificaciones = $conexion->query(
    "SELECT id, codigo, nombre, stock, stock_minimo FROM productos WHERE estado = 'Activo' AND stock <= stock_minimo ORDER BY stock ASC, nombre ASC LIMIT 10"
);
while ($n = $consultaNotificaciones->fetch_assoc()) {
    $notificaciones[] = $n;
}
$totalNotificaciones = count($notificaciones);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $titulo ?></title>
  <!-- Bootstrap 5 CSS e Iconos -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  
  <style>
    /* Estilos idénticos a la imagen de referencia */
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
      background: #0b1c3d; /* Azul oscuro corporativo como la imagen */
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
      color: #94a3b8; /* Tono gris claro para el menú inactivo */
      font-weight: 500; 
      border-radius: 8px; 
      margin-bottom: 2px; 
      padding: 10px 14px; 
      display: flex; 
      align-items: center; 
      gap: 12px; 
    }
    .nav-link:hover { background-color: rgba(255, 255, 255, 0.08); color: #ffffff; }
    .nav-link.active { background-color: #2563eb; color: #ffffff !important; } /* Azul vibrante activo */

    /* Tarjetas blancas estilizadas sobre fondo suave */
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
    
    /* Badges suaves estilo moderno idénticos a la imagen */
    .badge-disponible { background-color: #dcfce7; color: #15803d; border-radius: 20px; padding: 5px 12px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .badge-bajo { background-color: #fef9c3; color: #a16207; border-radius: 20px; padding: 5px 12px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .badge-agotado { background-color: #fee2e2; color: #b91c1c; border-radius: 20px; padding: 5px 12px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
  </style>
</head>
<body>

  <!-- NAVEGACIÓN LATERAL UNIFICADA CON LOGO Y PRODUCTOS ACTIVO -->
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
      <a class="nav-link active" href="productos.php"><i class="bi bi-box-seam fs-5"></i> Productos</a>
      <a class="nav-link" href="categorias.php"><i class="bi bi-folder fs-5"></i> Categorías</a>
      <a class="nav-link" href="laboratorios.php"><i class="bi bi-flask fs-5"></i> Laboratorios</a>
      <a class="nav-link" href="entradas.php"><i class="bi bi-download fs-5"></i> Entradas</a>
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

        <form class="d-flex position-relative flex-grow-1 my-1" style="max-width: 420px;" action="productos.php" method="GET">
          <input class="form-control bg-light border-0 ps-5 rounded-pill" style="font-size: 0.9rem;" type="search" name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar producto, código o categoría...">
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
                <li>
                  <a class="dropdown-item rounded p-2 text-wrap" href="productos.php?buscar=<?= urlencode($n["codigo"]) ?>">
                    <strong class="d-block small text-dark"><?= htmlspecialchars($n["nombre"]) ?></strong>
                    <span class="text-muted" style="font-size: 0.75rem;">Stock actual: <?= (int)$n["stock"] ?> / Mínimo: <?= (int)$n["stock_minimo"] ?></span>
                  </a>
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

    <!-- CONTENIDO DE PRODUCTOS -->
    <main class="p-4">
      <?php if ($msg !== ""): ?>
          <div class="alert alert-<?= htmlspecialchars($tipoMsg) ?> alert-dismissible fade show shadow-sm border-0" role="alert">
              <?= htmlspecialchars($msg) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
              <h4 class="fw-bold mb-1" style="color: #0f172a;">Productos</h4>
              <div class="text-muted small">Inicio / <strong>Productos</strong></div>
          </div>
          <button type="button" class="btn btn-success px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="nuevoProducto()">
              <i class="bi bi-plus-lg me-1"></i> Nuevo Producto
          </button>
      </div>

      <!-- FILTROS DE BÚSQUEDA -->
      <div class="card card-custom p-3 mb-4">
          <form method="GET" action="productos.php">
              <div class="row g-3 align-items-end">
                  <div class="col-md-4">
                      <label class="form-label small fw-semibold text-muted">Buscar</label>
                      <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" class="form-control" placeholder="Buscar por código o nombre...">
                  </div>
                  <div class="col-md-2">
                      <label class="form-label small fw-semibold text-muted">Categoría</label>
                      <select name="categoria" class="form-select">
                          <option value="0">Todas</option>
                          <?php 
                          $catsFiltro = $conexion->query("SELECT id, nombre FROM categorias WHERE estado='Activo' ORDER BY nombre");
                          while ($c = $catsFiltro->fetch_assoc()): 
                          ?>
                              <option value="<?= $c["id"] ?>" <?= $categoriaFiltro == $c["id"] ? "selected" : "" ?>><?= htmlspecialchars($c["nombre"]) ?></option>
                          <?php endwhile; ?>
                      </select>
                  </div>
                  <div class="col-md-2">
                      <label class="form-label small fw-semibold text-muted">Laboratorio</label>
                      <select name="laboratorio" class="form-select">
                          <option value="0">Todos</option>
                          <?php 
                          $labsFiltro = $conexion->query("SELECT id, nombre FROM laboratorios WHERE estado='Activo' ORDER BY nombre");
                          while ($l = $labsFiltro->fetch_assoc()): 
                          ?>
                              <option value="<?= $l["id"] ?>" <?= $laboratorioFiltro == $l["id"] ? "selected" : "" ?>><?= htmlspecialchars($l["nombre"]) ?></option>
                          <?php endwhile; ?>
                      </select>
                  </div>
                  <div class="col-md-2">
                      <label class="form-label small fw-semibold text-muted">Estado</label>
                      <select name="estado" class="form-select">
                          <option value="">Todos</option>
                          <option value="Disponible" <?= $estadoFiltro === "Disponible" ? "selected" : "" ?>>Disponible</option>
                          <option value="Bajo Stock" <?= $estadoFiltro === "Bajo Stock" ? "selected" : "" ?>>Bajo Stock</option>
                          <option value="Agotado" <?= $estadoFiltro === "Agotado" ? "selected" : "" ?>>Agotado</option>
                      </select>
                  </div>
                  <div class="col-md-2 d-flex gap-2">
                      <button type="submit" class="btn btn-primary w-50 fw-medium"><i class="bi bi-search me-1"></i> Buscar</button>
                      <a href="productos.php" class="btn btn-outline-secondary w-50 fw-medium"><i class="bi bi-arrow-clockwise me-1"></i> Limpiar</a>
                  </div>
              </div>
          </form>
      </div>

      <!-- TABLA ESTILIZADA SOBRE TARJETA FLOTANTE -->
      <div class="card card-custom overflow-hidden">
          <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
              <strong class="text-dark">Productos registrados</strong>
              <span class="badge bg-light text-secondary border px-2 py-1"><?= $rows->num_rows ?> resultado(s)</span>
          </div>
          <div class="table-responsive">
              <table class="table table-hover align-middle mb-0 table-custom">
                  <thead>
                      <tr>
                          <th>Código</th>
                          <th>Nombre</th>
                          <th>Categoría</th>
                          <th>Laboratorio</th>
                          <th>Precio Compra</th>
                          <th>Precio Venta</th>
                          <th>Stock</th>
                          <th>Estado</th>
                          <th class="text-end">Acciones</th>
                      </tr>
                  </thead>
                  <tbody>
                  <?php if ($rows->num_rows === 0): ?>
                      <tr>
                          <td colspan="9" class="text-center py-5 text-muted">
                              <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                              <div>No se encontraron productos registrados.</div>
                          </td>
                      </tr>
                  <?php endif; ?>

                  <?php while ($p = $rows->fetch_assoc()): ?>
                      <tr>
                          <td class="fw-semibold text-secondary"><?= htmlspecialchars($p["codigo"]) ?></td>
                          <td><strong class="text-dark"><?= htmlspecialchars($p["nombre"]) ?></strong></td>
                          <td><?= htmlspecialchars($p["categoria"]) ?></td>
                          <td><?= htmlspecialchars($p["laboratorio"]) ?></td>
                          <td>$<?= number_format($p["precio_compra"], 0, ",", ".") ?></td>
                          <td>$<?= number_format($p["precio_venta"], 0, ",", ".") ?></td>
                          <td><strong><?= (int)$p["stock"] ?></strong></td>
                          <td>
                              <?php 
                              $stock = (int)$p["stock"];
                              $minimo = (int)$p["stock_minimo"];
                              if ($stock <= 0) {
                                  echo '<span class="badge-agotado">Agotado</span>';
                              } elseif ($stock <= $minimo) {
                                  echo '<span class="badge-bajo">Bajo Stock</span>';
                              } else {
                                  echo '<span class="badge-disponible">Disponible</span>';
                              }
                              ?>
                          </td>
                          <td class="text-end">
                              <div class="d-flex gap-1 justify-content-end">
                                  <button type="button" class="btn btn-sm btn-primary rounded-2 px-2" title="Editar" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick='editarProducto(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                      <i class="bi bi-pencil"></i>
                                  </button>
                                  <form method="POST" class="d-inline" onsubmit="return confirmarEliminar();">
                                      <input type="hidden" name="accion" value="eliminar">
                                      <input type="hidden" name="id" value="<?= (int)$p["id"] ?>">
                                      <button type="submit" class="btn btn-sm btn-danger rounded-2 px-2" title="Eliminar"><i class="bi bi-trash"></i></button>
                                  </form>
                              </div>
                          </td>
                      </tr>
                  <?php endwhile; ?>
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

<!-- MODAL PRODUCTO -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form method="POST" id="formProducto">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="tituloModal">Nuevo producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="id">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Código</label>
                            <input type="text" class="form-control" name="codigo" id="codigo" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Nombre del producto</label>
                            <input type="text" class="form-control" name="nombre" id="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Categoría</label>
                            <select class="form-select" name="categoria_id" id="categoria_id" required>
                                <option value="">Seleccione una categoría</option>
                                <?php 
                                $catsModal = $conexion->query("SELECT id, nombre FROM categorias WHERE estado = 'Activo' ORDER BY nombre");
                                while ($c = $catsModal->fetch_assoc()): 
                                ?>
                                    <option value="<?= $c["id"] ?>"><?= htmlspecialchars($c["nombre"]) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Laboratorio</label>
                            <select class="form-select" name="laboratorio_id" id="laboratorio_id" required>
                                <option value="">Seleccione un laboratorio</option>
                                <?php 
                                $labsModal = $conexion->query("SELECT id, nombre FROM laboratorios WHERE estado = 'Activo' ORDER BY nombre");
                                while ($l = $labsModal->fetch_assoc()): 
                                ?>
                                    <option value="<?= $l["id"] ?>"><?= htmlspecialchars($l["nombre"]) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Precio de compra</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="precio_compra" id="precio_compra" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Precio de venta</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="precio_venta" id="precio_venta" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Stock</label>
                            <input type="number" min="0" max="2147483647" class="form-control" name="stock" id="stock" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Stock mínimo</label>
                            <input type="number" min="0" max="2147483647" class="form-control" name="stock_minimo" id="stock_minimo" value="10" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4"><i class="bi bi-save me-1"></i> Guardar producto</button>
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
});

function nuevoProducto() {
    document.getElementById("tituloModal").innerText = "Nuevo producto";
    document.getElementById("formProducto").reset();
    document.getElementById("id").value = "";
    document.getElementById("stock_minimo").value = "10";
}

function editarProducto(producto) {
    document.getElementById("tituloModal").innerText = "Editar producto";
    document.getElementById("id").value = producto.id;
    document.getElementById("codigo").value = producto.codigo;
    document.getElementById("nombre").value = producto.nombre;
    document.getElementById("categoria_id").value = producto.categoria_id;
    document.getElementById("laboratorio_id").value = producto.laboratorio_id;
    document.getElementById("precio_compra").value = producto.precio_compra;
    document.getElementById("precio_venta").value = producto.precio_venta;
    document.getElementById("stock").value = producto.stock;
    document.getElementById("stock_minimo").value = producto.stock_minimo;
}

function confirmarEliminar() {
    return confirm("¿Está seguro de que desea eliminar este producto?");
}
</script>
</body>
</html>