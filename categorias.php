<?php
require "config/auth.php"; 
require "config/conexion.php"; 

$titulo = "Categorías"; 
$msg = "";
$tipo_msg = "info";

// Manejo de Sesión de Notificaciones para la campanita
if (!isset($_SESSION['notificaciones'])) {
    $_SESSION['notificaciones'] = array();
}

// ---------------------------------------------------------
// ACCIONES: GUARDAR (CREAR / EDITAR) Y ELIMINAR
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") { 
    $a = isset($_POST["accion"]) ? $_POST["accion"] : ""; 
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;

    if ($a === "guardar") { 
        $n = isset($_POST["nombre"]) ? trim($_POST["nombre"]) : ""; 
        $d = isset($_POST["descripcion"]) ? trim($_POST["descripcion"]) : ""; 
        $e = isset($_POST["estado"]) ? $_POST["estado"] : "Activo"; 

        if ($id > 0) {
            $s = $conexion->prepare("UPDATE categorias SET nombre=?, descripcion=?, estado=? WHERE id=?");
            $s->bind_param("sssi", $n, $d, $e, $id);
            $texto_notif = "Se actualizó la categoría '$n'";
        } else {
            $s = $conexion->prepare("INSERT INTO categorias(nombre, descripcion, estado) VALUES(?,?,?)");
            $s->bind_param("sss", $n, $d, $e);
            $texto_notif = "Se agregó la categoría '$n'";
        } 
        
        if ($s->execute()) {
            $msg = "Categoría guardada correctamente.";
            $tipo_msg = "success";
            array_unshift($_SESSION['notificaciones'], array(
                "mensaje" => $texto_notif,
                "hora" => date("H:i")
            ));
        } else {
            $msg = "Error al guardar la categoría.";
            $tipo_msg = "danger";
        }
    }

    if ($a === "eliminar") { 
        $stmt_nom = $conexion->prepare("SELECT nombre FROM categorias WHERE id=?");
        $stmt_nom->bind_param("i", $id);
        $stmt_nom->execute();
        $res_nom = $stmt_nom->get_result()->fetch_assoc();
        $nombre_cat = isset($res_nom['nombre']) ? $res_nom['nombre'] : 'Categoría';

        $s = $conexion->prepare("DELETE FROM categorias WHERE id=?");
        $s->bind_param("i", $id);
        
        try {
            if ($s->execute()) {
                $msg = "Categoría eliminada con éxito.";
                $tipo_msg = "success";
                array_unshift($_SESSION['notificaciones'], array(
                    "mensaje" => "Se eliminó la categoría '$nombre_cat'",
                    "hora" => date("H:i")
                ));
            } else {
                $msg = "No se puede eliminar porque tiene productos relacionados.";
                $tipo_msg = "warning";
            }
        } catch (Exception $e) {
            $msg = "No se puede eliminar porque tiene productos relacionados.";
            $tipo_msg = "warning";
        }
    }
}

// ---------------------------------------------------------
// FILTROS, BÚSQUEDA Y PAGINACIÓN
// ---------------------------------------------------------
$buscar = isset($_GET["buscar"]) ? trim($_GET["buscar"]) : "";
$filtro_estado = isset($_GET["estado"]) ? trim($_GET["estado"]) : "";

$where = array("1=1");
$params = array();
$types = "";

if ($buscar !== "") {
    $where[] = "(c.nombre LIKE ? OR c.descripcion LIKE ? OR c.id LIKE ?)";
    $term = "%$buscar%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= "sss";
}

if ($filtro_estado !== "" && $filtro_estado !== "Todos") {
    $where[] = "c.estado = ?";
    $params[] = $filtro_estado;
    $types .= "s";
}

$where_sql = implode(" AND ", $where);

// Paginación
$limite = 7;
$pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina < 1) $pagina = 1;
$inicio = ($pagina - 1) * $limite;

// Contar total de registros
$sql_total = "SELECT COUNT(*) total FROM categorias c WHERE $where_sql";
$stmt_t = $conexion->prepare($sql_total);
if (!empty($params)) {
    $p_arr = array_merge(array($types), $params);
    $tmp = array();
    foreach($p_arr as $k => $v) $tmp[$k] = &$p_arr[$k];
    call_user_func_array(array($stmt_t, 'bind_param'), $tmp);
}
$stmt_t->execute();
$total_registros = $stmt_t->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limite);

// Consulta principal
$sql = "SELECT c.*, COUNT(p.id) productos 
        FROM categorias c 
        LEFT JOIN productos p ON p.categoria_id = c.id 
        WHERE $where_sql 
        GROUP BY c.id 
        ORDER BY c.id DESC 
        LIMIT ?, ?";

$stmt = $conexion->prepare($sql);
$types_limit = $types . "ii";
$params_limit = array_merge($params, array($inicio, $limite));
$p_arr2 = array_merge(array($types_limit), $params_limit);
$tmp2 = array();
foreach($p_arr2 as $k => $v) $tmp2[$k] = &$p_arr2[$k];
call_user_func_array(array($stmt, 'bind_param'), $tmp2);

$stmt->execute();
$r = $stmt->get_result();

// Notificaciones generales para la campanita
$totalNotificaciones = 0;
$notificaciones = array();
if (isset($_SESSION['notificaciones'])) {
    $notificaciones = $_SESSION['notificaciones'];
    $totalNotificaciones = count($notificaciones);
}

/* =========================================================
   FUNCIÓN PARA ASIGNAR EMOJI Y COLOR POR CATEGORÍA
========================================================= */
function obtenerEmojiCategoria($nombre) {
    $n = mb_strtolower($nombre, 'UTF-8');
    if (strpos($n, 'medicament') !== false || strpos($n, 'analgé') !== false || strpos($n, 'antibió') !== false || strpos($n, 'cardio') !== false || strpos($n, 'diabét') !== false) {
        return ['emoji' => '💊', 'bg' => '#e0f2fe'];
    } elseif (strpos($n, 'vitamina') !== false || strpos($n, 'suplement') !== false) {
        return ['emoji' => '🌿', 'bg' => '#dcfce7'];
    } elseif (strpos($n, 'aseo') !== false || strpos($n, 'personal') !== false || strpos($n, 'higiene') !== false) {
        return ['emoji' => '🧴', 'bg' => '#fef3c7'];
    } elseif (strpos($n, 'hogar') !== false || strpos($n, 'limpieza') !== false) {
        return ['emoji' => '🧹', 'bg' => '#f3e8ff'];
    } elseif (strpos($n, 'inyectable') !== false) {
        return ['emoji' => '💉', 'bg' => '#ffe4e6'];
    } elseif (strpos($n, 'bebé') !== false || strpos($n, 'pañal') !== false || strpos($n, 'infantil') !== false) {
        return ['emoji' => '👶', 'bg' => '#e0e7ff'];
    } elseif (strpos($n, 'primeros') !== false || strpos($n, 'auxilio') !== false || strpos($n, 'curaci') !== false) {
        return ['emoji' => '🩹', 'bg' => '#ffedd5'];
    } elseif (strpos($n, 'respira') !== false || strpos($n, 'alérg') !== false || strpos($n, 'cortico') !== false) {
        return ['emoji' => '🩺', 'bg' => '#e0f2fe'];
    } elseif (strpos($n, 'gastro') !== false) {
        return ['emoji' => '🫁', 'bg' => '#fef3c7'];
    } else {
        return ['emoji' => '🏷️', 'bg' => '#e0e7ff'];
    }
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
    /* Estilos globales unificados */
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

    /* Tarjetas personalizadas */
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
    
    /* Badges de estado */
    .badge-soft-success { background-color: #dcfce7; color: #15803d; border-radius: 20px; padding: 5px 12px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .badge-soft-danger { background-color: #fee2e2; color: #b91c1c; border-radius: 20px; padding: 5px 12px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .badge-soft-primary { background-color: #e0f2fe; color: #0369a1; border-radius: 8px; padding: 4px 10px; font-weight: 600; font-size: 0.8rem; }

    /* Círculo decorativo con Emojis */
    .cat-icon-circle {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      margin-right: 12px;
      flex-shrink: 0;
      box-shadow: inset 0 0 0 1px rgba(0,0,0,0.05);
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
      <a class="nav-link active" href="categorias.php"><i class="bi bi-folder fs-5"></i> Categorías</a>
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

        <form class="d-flex position-relative flex-grow-1 my-1" style="max-width: 420px;" action="categorias.php" method="GET">
          <input class="form-control bg-light border-0 ps-5 rounded-pill" style="font-size: 0.9rem;" type="search" name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar categoría...">
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
                  <span class="text-muted" style="font-size: 0.75rem;"><?= $n["hora"] ?></span>
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
      <?php if ($msg !== ""): ?>
          <div class="alert alert-<?= htmlspecialchars($tipo_msg) ?> alert-dismissible fade show shadow-sm border-0" role="alert">
              <?= htmlspecialchars($msg) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
              <h4 class="fw-bold mb-1" style="color: #0f172a;"><i class="bi bi-folder-fill me-2 text-primary"></i>Categorías</h4>
              <div class="text-muted small">Inicio / <strong>Categorías</strong></div>
          </div>
          <button type="button" class="btn btn-success px-3 py-2 fw-semibold shadow-sm" onclick="limpiarModal()" data-bs-toggle="modal" data-bs-target="#m">
              <i class="bi bi-plus-lg me-1"></i> Nueva Categoría
          </button>
      </div>

      <!-- FILTROS DE BÚSQUEDA -->
      <div class="card card-custom p-3 mb-4">
          <form method="get" action="categorias.php">
              <div class="row g-3 align-items-end">
                  <div class="col-md-5">
                      <label class="form-label small fw-semibold text-muted">Buscar</label>
                      <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" class="form-control" placeholder="Buscar categoría...">
                  </div>
                  <div class="col-md-3">
                      <label class="form-label small fw-semibold text-muted">Estado</label>
                      <select name="estado" class="form-select">
                          <option value="Todos" <?= $filtro_estado==='Todos'||$filtro_estado===''?'selected':'' ?>>Todos</option>
                          <option value="Activo" <?= $filtro_estado==='Activo'?'selected':'' ?>>Activo</option>
                          <option value="Inactivo" <?= $filtro_estado==='Inactivo'?'selected':'' ?>>Inactivo</option>
                      </select>
                  </div>
                  <div class="col-md-4 d-flex gap-2">
                      <button type="submit" class="btn btn-primary w-50 fw-medium"><i class="bi bi-search me-1"></i> Buscar</button>
                      <a href="categorias.php" class="btn btn-outline-secondary w-50 fw-medium"><i class="bi bi-arrow-clockwise me-1"></i> Limpiar</a>
                  </div>
              </div>
          </form>
      </div>

      <!-- TABLA DE CATEGORÍAS -->
      <div class="card card-custom overflow-hidden">
          <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
              <strong class="text-dark">Categorías registradas</strong>
              <span class="badge bg-light text-secondary border px-2 py-1"><?= $total_registros ?> registro(s)</span>
          </div>
          <div class="table-responsive">
              <table class="table table-hover align-middle mb-0 table-custom">
                  <thead>
                      <tr>
                          <th>ID</th>
                          <th>Nombre de la categoría</th>
                          <th>Descripción</th>
                          <th>Cantidad de productos</th>
                          <th>Estado</th>
                          <th class="text-end">Acciones</th>
                      </tr>
                  </thead>
                  <tbody>
                  <?php if ($r->num_rows === 0): ?>
                      <tr>
                          <td colspan="6" class="text-center py-5 text-muted">
                              <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                              <div>No se encontraron categorías registradas.</div>
                          </td>
                      </tr>
                  <?php endif; ?>

                  <?php while ($x = $r->fetch_assoc()): 
                      $iconoEmoji = obtenerEmojiCategoria($x["nombre"]);
                  ?>
                      <tr>
                          <td class="fw-semibold text-secondary"><?= $x["id"] ?></td>
                          <td>
                              <div class="d-flex align-items-center">
                                  <div class="cat-icon-circle" style="background-color: <?= $iconoEmoji['bg'] ?>;">
                                      <span><?= $iconoEmoji['emoji'] ?></span>
                                  </div>
                                  <strong class="text-dark"><?= htmlspecialchars($x["nombre"]) ?></strong>
                              </div>
                          </td>
                          <td class="text-muted"><?= $x["descripcion"] ? htmlspecialchars($x["descripcion"]) : '-' ?></td>
                          <td>
                              <span class="badge-soft-primary"><?= (int)$x["productos"] ?></span>
                          </td>
                          <td>
                              <?php if ($x["estado"] === 'Activo'): ?>
                                  <span class="badge-soft-success">Activo</span>
                              <?php else: ?>
                                  <span class="badge-soft-danger">Inactivo</span>
                              <?php endif; ?>
                          </td>
                          <td class="text-end">
                              <div class="d-flex gap-1 justify-content-end">
                                  <button type="button" class="btn btn-sm btn-primary rounded-2 px-2" title="Editar" onclick='ed(<?= json_encode($x, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' data-bs-toggle="modal" data-bs-target="#m">
                                      <i class="bi bi-pencil"></i>
                                  </button>
                                  <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta categoría?');">
                                      <input type="hidden" name="accion" value="eliminar">
                                      <input type="hidden" name="id" value="<?= (int)$x["id"] ?>">
                                      <button type="submit" class="btn btn-sm btn-danger rounded-2 px-2" title="Eliminar"><i class="bi bi-trash"></i></button>
                                  </form>
                              </div>
                          </td>
                      </tr>
                  <?php endwhile; ?>
                  </tbody>
              </table>
          </div>

          <!-- FOOTER DE PAGINACIÓN -->
          <div class="card-footer bg-white py-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
              <small class="text-muted">
                  Mostrando <?= $total_registros > 0 ? $inicio + 1 : 0 ?> a <?= min($inicio + $limite, $total_registros) ?> de <?= $total_registros ?> categorías
              </small>
              
              <?php if ($total_paginas > 1): ?>
                  <nav>
                      <ul class="pagination pagination-sm mb-0">
                          <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                              <a class="page-link" href="?p=<?= $pagina - 1 ?>&buscar=<?= urlencode($buscar) ?>&estado=<?= urlencode($filtro_estado) ?>">Anterior</a>
                          </li>
                          
                          <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                              <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>">
                                  <a class="page-link" href="?p=<?= $i ?>&buscar=<?= urlencode($buscar) ?>&estado=<?= urlencode($filtro_estado) ?>"><?= $i ?></a>
                              </li>
                          <?php endfor; ?>

                          <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                              <a class="page-link" href="?p=<?= $pagina + 1 ?>&buscar=<?= urlencode($buscar) ?>&estado=<?= urlencode($filtro_estado) ?>">Siguiente</a>
                          </li>
                      </ul>
                  </nav>
              <?php endif; ?>
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

<!-- MODAL PARA CREAR / EDITAR CATEGORÍA -->
<div class="modal fade" id="m" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form method="post">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="modalTitulo">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="id">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre de la categoría *</label>
                        <input class="form-control" name="nombre" id="nombre" placeholder="Ej. Medicamentos" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="descripcion" rows="3" placeholder="Ingrese una descripción (opcional)"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Estado</label>
                        <select class="form-select" name="estado" id="estado">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-semibold"><i class="bi bi-save me-1"></i> Guardar</button>
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

function ed(x) {
    document.getElementById('modalTitulo').innerText = "Editar Categoría";
    document.getElementById('id').value = x.id;
    document.getElementById('nombre').value = x.nombre;
    document.getElementById('descripcion').value = x.descripcion || '';
    document.getElementById('estado').value = x.estado;
}

function limpiarModal() {
    document.getElementById('modalTitulo').innerText = "Nueva Categoría";
    document.getElementById('id').value = '';
    document.getElementById('nombre').value = '';
    document.getElementById('descripcion').value = '';
    document.getElementById('estado').value = 'Activo';
}
</script>
</body>
</html>