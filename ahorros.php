<?php
require_once 'config/database.php';
iniciarSesion();
verificarAutenticacion();

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['usuario_id'];

$mensaje = '';
$tipo_mensaje = '';

// Procesar configuración de ahorro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'configurar') {
        $porcentaje = floatval($_POST['porcentaje_ahorro']);
        $meta_ahorro = !empty($_POST['meta_ahorro']) ? floatval($_POST['meta_ahorro']) : null;
        $descripcion_meta = Security::sanitize($_POST['descripcion_meta'] ?? '');
        $fecha_meta = !empty($_POST['fecha_meta']) ? $_POST['fecha_meta'] : null;
        
        if ($porcentaje < 0 || $porcentaje > 100) {
            $mensaje = "El porcentaje debe estar entre 0 y 100.";
            $tipo_mensaje = "danger";
        } else {
            $stmt = $db->prepare("UPDATE config_ahorro SET activo = 0 WHERE id_usuario = ?");
            $stmt->execute([$usuario_id]);
            
            $stmt = $db->prepare("INSERT INTO config_ahorro (id_usuario, porcentaje_ahorro, meta_ahorro, descripcion_meta, fecha_inicio, fecha_meta) VALUES (?, ?, ?, ?, CURDATE(), ?)");
            if ($stmt->execute([$usuario_id, $porcentaje, $meta_ahorro, $descripcion_meta, $fecha_meta])) {
                $mensaje = "Configuración de ahorro actualizada exitosamente.";
                $tipo_mensaje = "success";
                registrarLog('config_ahorro', "Configuración de ahorro: $porcentaje%", $usuario_id);
            }
        }
    } elseif ($accion === 'registrar') {
        $fecha = Security::sanitize($_POST['fecha']);
        $monto_base = floatval($_POST['monto_base']);
        $porcentaje = floatval($_POST['porcentaje_aplicado']);
        $monto_ahorrado = ($monto_base * $porcentaje) / 100;
        $notas = Security::sanitize($_POST['notas'] ?? '');
        $mes = date('F Y', strtotime($fecha));
        
        if ($monto_base <= 0) {
            $mensaje = "El monto base debe ser mayor a cero.";
            $tipo_mensaje = "danger";
        } else {
            $stmt = $db->prepare("INSERT INTO ahorros (id_usuario, fecha, mes, monto_ahorrado, monto_base, porcentaje_aplicado, notas) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$usuario_id, $fecha, $mes, $monto_ahorrado, $monto_base, $porcentaje, $notas])) {
                $mensaje = "Ahorro registrado exitosamente: $" . number_format($monto_ahorrado, 2);
                $tipo_mensaje = "success";
                registrarLog('ahorro_registrado', "Ahorro: $$monto_ahorrado", $usuario_id);
            }
        }
    } elseif ($accion === 'editar') {
        $id_ahorro = intval($_POST['id_ahorro']);
        $fecha = Security::sanitize($_POST['fecha']);
        $monto_base = floatval($_POST['monto_base']);
        $porcentaje = floatval($_POST['porcentaje_aplicado']);
        $monto_ahorrado = ($monto_base * $porcentaje) / 100;
        $notas = Security::sanitize($_POST['notas'] ?? '');
        $mes = date('F Y', strtotime($fecha));
        
        if ($monto_base <= 0) {
            $mensaje = "El monto base debe ser mayor a cero.";
            $tipo_mensaje = "danger";
        } else {
            $stmt = $db->prepare("UPDATE ahorros SET fecha = ?, mes = ?, monto_ahorrado = ?, monto_base = ?, porcentaje_aplicado = ?, notas = ? WHERE id_ahorro = ? AND id_usuario = ?");
            if ($stmt->execute([$fecha, $mes, $monto_ahorrado, $monto_base, $porcentaje, $notas, $id_ahorro, $usuario_id])) {
                $mensaje = "Ahorro actualizado exitosamente.";
                $tipo_mensaje = "success";
                registrarLog('ahorro_editado', "Ahorro editado ID: $id_ahorro", $usuario_id);
            }
        }
    } elseif ($accion === 'eliminar') {
        $id_ahorro = intval($_POST['id_ahorro']);
        $stmt = $db->prepare("DELETE FROM ahorros WHERE id_ahorro = ? AND id_usuario = ?");
        if ($stmt->execute([$id_ahorro, $usuario_id])) {
            $mensaje = "Ahorro eliminado exitosamente.";
            $tipo_mensaje = "success";
            registrarLog('ahorro_eliminado', "Ahorro eliminado ID: $id_ahorro", $usuario_id);
        }
    }
}

// Obtener configuración activa
$stmt = $db->prepare("SELECT * FROM config_ahorro WHERE id_usuario = ? AND activo = 1 ORDER BY id_config DESC LIMIT 1");
$stmt->execute([$usuario_id]);
$config = $stmt->fetch();

// Obtener historial de ahorros
$stmt = $db->prepare("SELECT * FROM ahorros WHERE id_usuario = ? ORDER BY fecha DESC");
$stmt->execute([$usuario_id]);
$historial_ahorros = $stmt->fetchAll();

// Calcular totales
$total_ahorrado = 0;
foreach ($historial_ahorros as $ahorro) {
    $total_ahorrado += $ahorro['monto_ahorrado'];
}

// Calcular ahorro sugerido del mes actual basado en ingresos - CORREGIDO
$mes_actual = date('Y-m');
$stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM ingresos WHERE id_usuario = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
$stmt->execute([$usuario_id, $mes_actual]);
$ingresos_mes_actual = $stmt->fetch()['total'];

$ahorro_sugerido_mes = 0;
if ($config && $ingresos_mes_actual > 0) {
    $ahorro_sugerido_mes = ($ingresos_mes_actual * $config['porcentaje_ahorro']) / 100;
}

// Calcular progreso de meta
$progreso_meta = 0;
if ($config && $config['meta_ahorro'] > 0) {
    $progreso_meta = ($total_ahorrado / $config['meta_ahorro']) * 100;
    $progreso_meta = min($progreso_meta, 100);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ahorros - Sistema de Finanzas Personales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .progress-large {
            height: 30px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-wallet2"></i> Finanzas Personales
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ingresos.php">
                            <i class="bi bi-cash-coin"></i> Ingresos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="egresos.php">
                            <i class="bi bi-cart"></i> Egresos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="ahorros.php">
                            <i class="bi bi-piggy-bank"></i> Ahorros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="presupuesto.php">
                            <i class="bi bi-calculator"></i> Presupuesto
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reportes.php">
                            <i class="bi bi-graph-up"></i> Reportes
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center text-white">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="me-3"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-piggy-bank"></i> Gestión de Ahorros</h2>
                <p class="text-muted">Planifica y alcanza tus metas financieras</p>
            </div>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Alerta de ingresos del mes -->
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle"></i> 
            <strong>Ingresos del mes actual (<?php echo date('F Y'); ?>):</strong> 
            $<?php echo number_format($ingresos_mes_actual, 2); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="row g-4 mb-4">
            <!-- Configuración de Ahorro -->
            <div class="col-lg-6">
                <div class="card stat-card">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Configuración de Ahorro</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="configurar">
                            
                            <div class="mb-3">
                                <label for="porcentaje_ahorro" class="form-label">Porcentaje de Ahorro (%)</label>
                                <input type="number" class="form-control form-control-lg" name="porcentaje_ahorro" 
                                       id="porcentaje_ahorro" step="0.01" min="0" max="100" 
                                       value="<?php echo $config ? $config['porcentaje_ahorro'] : 10; ?>" required>
                                <small class="text-muted">Se aplicará sobre tus ingresos mensuales</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="meta_ahorro" class="form-label">Meta de Ahorro ($)</label>
                                <input type="number" class="form-control" name="meta_ahorro" id="meta_ahorro" 
                                       step="0.01" min="0" value="<?php echo $config ? $config['meta_ahorro'] : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion_meta" class="form-label">Descripción de la Meta</label>
                                <input type="text" class="form-control" name="descripcion_meta" 
                                       id="descripcion_meta" value="<?php echo $config ? htmlspecialchars($config['descripcion_meta']) : ''; ?>" 
                                       placeholder="Ej: Viaje a Europa, Auto nuevo, Fondo de emergencia">
                            </div>
                            
                            <div class="mb-3">
                                <label for="fecha_meta" class="form-label">Fecha Límite</label>
                                <input type="date" class="form-control" name="fecha_meta" id="fecha_meta" 
                                       value="<?php echo $config ? $config['fecha_meta'] : ''; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-save"></i> Guardar Configuración
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Resumen y Meta -->
            <div class="col-lg-6">
                <div class="card stat-card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-trophy"></i> Resumen de Ahorros</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h1 class="display-4 text-success">$<?php echo number_format($total_ahorrado, 2); ?></h1>
                            <p class="text-muted">Total Ahorrado</p>
                        </div>
                        
                        <?php if ($config && $config['meta_ahorro']): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>Meta: <?php echo htmlspecialchars($config['descripcion_meta']); ?></strong>
                                    <span>$<?php echo number_format($config['meta_ahorro'], 2); ?></span>
                                </div>
                                <div class="progress progress-large">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $progreso_meta; ?>%" 
                                         aria-valuenow="<?php echo $progreso_meta; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($progreso_meta, 1); ?>%
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        Faltan $<?php echo number_format($config['meta_ahorro'] - $total_ahorrado, 2); ?> para alcanzar tu meta
                                    </small>
                                </div>
                            </div>
                            
                            <?php if ($config['fecha_meta']): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-calendar-event"></i> 
                                    Fecha límite: <?php echo date('d/m/Y', strtotime($config['fecha_meta'])); ?>
                                    <?php
                                    $dias_restantes = (strtotime($config['fecha_meta']) - time()) / 86400;
                                    if ($dias_restantes > 0) {
                                        echo " (" . ceil($dias_restantes) . " días restantes)";
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> 
                                No has establecido una meta de ahorro. Configura una meta para visualizar tu progreso.
                            </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#modalRegistrarAhorro">
                            <i class="bi bi-plus-circle"></i> Registrar Ahorro
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <i class="bi bi-percent text-warning" style="font-size: 36px;"></i>
                                <h4 class="mt-2"><?php echo $config ? $config['porcentaje_ahorro'] : 0; ?>%</h4>
                                <small class="text-muted">Porcentaje Actual</small>
                            </div>
                            <div class="col-6 mb-3">
                                <i class="bi bi-clock-history text-info" style="font-size: 36px;"></i>
                                <h4 class="mt-2"><?php echo count($historial_ahorros); ?></h4>
                                <small class="text-muted">Registros de Ahorro</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de Ahorros -->
        <div class="card stat-card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Historial de Ahorros</h5>
            </div>
            <div class="card-body">
                <?php if (empty($historial_ahorros)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 64px; color: #ccc;"></i>
                        <p class="text-muted mt-3">No hay registros de ahorro todavía</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Mes</th>
                                    <th>Monto Base</th>
                                    <th>Porcentaje</th>
                                    <th>Monto Ahorrado</th>
                                    <th>Notas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($historial_ahorros as $ahorro): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($ahorro['fecha'])); ?></td>
                                        <td><?php echo htmlspecialchars($ahorro['mes']); ?></td>
                                        <td>$<?php echo number_format($ahorro['monto_base'], 2); ?></td>
                                        <td><?php echo $ahorro['porcentaje_aplicado']; ?>%</td>
                                        <td class="fw-bold text-success">$<?php echo number_format($ahorro['monto_ahorrado'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($ahorro['notas']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editarAhorro(<?php echo htmlspecialchars(json_encode($ahorro)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="eliminarAhorro(<?php echo $ahorro['id_ahorro']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-success fw-bold">
                                    <td colspan="4" class="text-end">TOTAL AHORRADO:</td>
                                    <td>$<?php echo number_format($total_ahorrado, 2); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Registrar/Editar Ahorro -->
    <div class="modal fade" id="modalRegistrarAhorro" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="tituloModalAhorro">Registrar Ahorro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formAhorro">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion_ahorro" value="registrar">
                        <input type="hidden" name="id_ahorro" id="id_ahorro">
                        
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" id="fecha" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="monto_base" class="form-label">Monto Base (Ingresos del periodo) *</label>
                            <input type="number" class="form-control" name="monto_base" id="monto_base" 
                                    step="0.01" min="0.01" value="<?php echo $ingresos_mes_actual; ?>" required>
                            <small class="text-muted">Ingresos del mes detectados: $<?php echo number_format($ingresos_mes_actual, 2); ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="porcentaje_aplicado" class="form-label">Porcentaje a Ahorrar *</label>
                            <input type="number" class="form-control" name="porcentaje_aplicado" 
                                   id="porcentaje_aplicado" step="0.01" min="0" max="100" 
                                   value="<?php echo $config ? $config['porcentaje_ahorro'] : 10; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Monto que se Ahorrará</label>
                            <input type="text" class="form-control" id="monto_calculado" readonly 
                                   value="$0.00" style="font-size: 1.2em; font-weight: bold; color: #28a745;">
                        </div>
                        
                        <div class="mb-3">
                            <label for="notas" class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" id="notas" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Guardar Ahorro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Establecer fecha actual
        document.getElementById('fecha').valueAsDate = new Date();
        
        // Calcular monto de ahorro automáticamente
        function calcularAhorro() {
            const montoBase = parseFloat(document.getElementById('monto_base').value) || 0;
            const porcentaje = parseFloat(document.getElementById('porcentaje_aplicado').value) || 0;
            const montoAhorro = (montoBase * porcentaje) / 100;
            
            document.getElementById('monto_calculado').value = '$' + montoAhorro.toFixed(2);
        }
        
        document.getElementById('monto_base').addEventListener('input', calcularAhorro);
        document.getElementById('porcentaje_aplicado').addEventListener('input', calcularAhorro);
        
        // Calcular al cargar
        calcularAhorro();
        
        function limpiarFormularioAhorro() {
            document.getElementById('formAhorro').reset();
            document.getElementById('accion_ahorro').value = 'registrar';
            document.getElementById('tituloModalAhorro').textContent = 'Registrar Ahorro';
            document.getElementById('fecha').valueAsDate = new Date();
            document.getElementById('monto_base').value = <?php echo $ingresos_mes_actual; ?>;
            calcularAhorro();
        }
        
        function editarAhorro(ahorro) {
            document.getElementById('accion_ahorro').value = 'editar';
            document.getElementById('id_ahorro').value = ahorro.id_ahorro;
            document.getElementById('fecha').value = ahorro.fecha;
            document.getElementById('monto_base').value = ahorro.monto_base;
            document.getElementById('porcentaje_aplicado').value = ahorro.porcentaje_aplicado;
            document.getElementById('notas').value = ahorro.notas;
            document.getElementById('tituloModalAhorro').textContent = 'Editar Ahorro';
            
            calcularAhorro();
            
            var modal = new bootstrap.Modal(document.getElementById('modalRegistrarAhorro'));
            modal.show();
        }
        
        function eliminarAhorro(id) {
            if (confirm('¿Está seguro de eliminar este registro de ahorro? Esto recalculará todos los balances.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="eliminar">' +
                                '<input type="hidden" name="id_ahorro" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Limpiar formulario al abrir modal para nuevo registro
        document.getElementById('modalRegistrarAhorro').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget || !event.relatedTarget.onclick) {
                limpiarFormularioAhorro();
            }
        });
    </script>
</body>
</html>