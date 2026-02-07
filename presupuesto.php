<?php
require_once 'config/database.php';
iniciarSesion();
verificarAutenticacion();

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['usuario_id'];

$mensaje = '';
$tipo_mensaje = '';

// Procesar configuración de presupuesto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'configurar') {
        $monto_presupuesto = floatval($_POST['monto_presupuesto']);
        $descripcion_presupuesto = Security::sanitize($_POST['descripcion_presupuesto'] ?? '');
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-01');
        $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : date('Y-m-t');
        
        if ($monto_presupuesto <= 0) {
            $mensaje = "El monto del presupuesto debe ser mayor a cero.";
            $tipo_mensaje = "danger";
        } else {
            // Desactivar configuraciones anteriores
            $stmt = $db->prepare("UPDATE config_presupuesto SET activo = 0 WHERE id_usuario = ?");
            $stmt->execute([$usuario_id]);
            
            // Insertar nueva configuración
            $stmt = $db->prepare("INSERT INTO config_presupuesto (id_usuario, monto_presupuesto, descripcion_presupuesto, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$usuario_id, $monto_presupuesto, $descripcion_presupuesto, $fecha_inicio, $fecha_fin])) {
                $mensaje = "Configuración de presupuesto actualizada exitosamente.";
                $tipo_mensaje = "success";
                registrarLog('config_presupuesto', "Configuración de presupuesto: $monto_presupuesto", $usuario_id);
            }
        }
    } elseif ($accion === 'registrar') {
        $fecha = Security::sanitize($_POST['fecha']);
        $descripcion = Security::sanitize($_POST['descripcion']);
        $categoria = Security::sanitize($_POST['categoria']);
        $monto_gastado = floatval($_POST['monto_gastado']);
        $notas = Security::sanitize($_POST['notas'] ?? '');
        $mes = date('F Y', strtotime($fecha));
        
        if ($monto_gastado <= 0) {
            $mensaje = "El monto gastado debe ser mayor a cero.";
            $tipo_mensaje = "danger";
        } else {
            $stmt = $db->prepare("INSERT INTO presupuesto_gastos (id_usuario, fecha, mes, descripcion, categoria, monto_gastado, notas) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$usuario_id, $fecha, $mes, $descripcion, $categoria, $monto_gastado, $notas])) {
                $mensaje = "Gasto registrado exitosamente: $" . number_format($monto_gastado, 2);
                $tipo_mensaje = "success";
                registrarLog('gasto_presupuesto_registrado', "Gasto: $$monto_gastado", $usuario_id);
            }
        }
    }
}

// Obtener configuración activa
$stmt = $db->prepare("SELECT * FROM config_presupuesto WHERE id_usuario = ? AND activo = 1 ORDER BY id_config DESC LIMIT 1");
$stmt->execute([$usuario_id]);
$config = $stmt->fetch();

// Obtener historial de gastos del presupuesto
$stmt = $db->prepare("SELECT * FROM presupuesto_gastos WHERE id_usuario = ? ORDER BY fecha DESC");
$stmt->execute([$usuario_id]);
$historial_gastos = $stmt->fetchAll();

// Calcular totales
$total_gastado = 0;
foreach ($historial_gastos as $gasto) {
    $total_gastado += $gasto['monto_gastado'];
}

// Calcular gastos del mes actual basado en egresos
$mes_actual = date('Y-m');
$stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM egresos WHERE id_usuario = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
$stmt->execute([$usuario_id, $mes_actual]);
$gastos_mes_actual = $stmt->fetch()['total'];

// Calcular progreso de presupuesto
$progreso_presupuesto = 0;
$disponible = 0;
if ($config && $config['monto_presupuesto'] > 0) {
    $progreso_presupuesto = ($total_gastado / $config['monto_presupuesto']) * 100;
    $progreso_presupuesto = min($progreso_presupuesto, 100);
    $disponible = $config['monto_presupuesto'] - $total_gastado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto - Sistema de Finanzas Personales</title>
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
                        <a class="nav-link" href="ahorros.php">
                            <i class="bi bi-piggy-bank"></i> Ahorros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="presupuesto.php">
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
                <h2><i class="bi bi-calculator"></i> Gestión de Presupuesto</h2>
                <p class="text-muted">Controla tus gastos y mantén tu presupuesto bajo control</p>
            </div>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <!-- Configuración de Presupuesto -->
            <div class="col-lg-6">
                <div class="card stat-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Configuración de Presupuesto</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="configurar">
                            
                            <div class="mb-3">
                                <label for="monto_presupuesto" class="form-label">Monto del Presupuesto ($)</label>
                                <input type="number" class="form-control form-control-lg" name="monto_presupuesto" 
                                       id="monto_presupuesto" step="0.01" min="0.01" 
                                       value="<?php echo $config ? $config['monto_presupuesto'] : ''; ?>" required>
                                <small class="text-muted">Monto máximo que planeas gastar en el periodo</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion_presupuesto" class="form-label">Descripción del Presupuesto</label>
                                <input type="text" class="form-control" name="descripcion_presupuesto" 
                                       id="descripcion_presupuesto" value="<?php echo $config ? htmlspecialchars($config['descripcion_presupuesto']) : ''; ?>" 
                                       placeholder="Ej: Presupuesto mensual, Gastos del hogar">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                                    <input type="date" class="form-control" name="fecha_inicio" id="fecha_inicio" 
                                           value="<?php echo $config ? $config['fecha_inicio'] : date('Y-m-01'); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                                    <input type="date" class="form-control" name="fecha_fin" id="fecha_fin" 
                                           value="<?php echo $config ? $config['fecha_fin'] : date('Y-m-t'); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-info w-100">
                                <i class="bi bi-save"></i> Guardar Configuración
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Resumen y Progreso -->
            <div class="col-lg-6">
                <div class="card stat-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-clipboard-data"></i> Resumen de Presupuesto</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h1 class="display-4 text-danger">$<?php echo number_format($total_gastado, 2); ?></h1>
                            <p class="text-muted">Total Gastado</p>
                        </div>
                        
                        <?php if ($config && $config['monto_presupuesto']): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>Presupuesto: <?php echo htmlspecialchars($config['descripcion_presupuesto']); ?></strong>
                                    <span>$<?php echo number_format($config['monto_presupuesto'], 2); ?></span>
                                </div>
                                <div class="progress progress-large">
                                    <div class="progress-bar <?php echo $progreso_presupuesto >= 90 ? 'bg-danger' : ($progreso_presupuesto >= 70 ? 'bg-warning' : 'bg-primary'); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $progreso_presupuesto; ?>%" 
                                         aria-valuenow="<?php echo $progreso_presupuesto; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($progreso_presupuesto, 1); ?>%
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <?php if ($disponible > 0): ?>
                                        <small class="text-success">
                                            <i class="bi bi-check-circle"></i>
                                            Disponible: $<?php echo number_format($disponible, 2); ?>
                                        </small>
                                    <?php elseif ($disponible == 0): ?>
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Presupuesto alcanzado
                                        </small>
                                    <?php else: ?>
                                        <small class="text-danger">
                                            <i class="bi bi-x-circle"></i>
                                            Excedido por: $<?php echo number_format(abs($disponible), 2); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="alert <?php echo $progreso_presupuesto >= 90 ? 'alert-danger' : ($progreso_presupuesto >= 70 ? 'alert-warning' : 'alert-info'); ?>">
                                <i class="bi bi-calendar-event"></i> 
                                Periodo: <?php echo date('d/m/Y', strtotime($config['fecha_inicio'])); ?> 
                                - <?php echo date('d/m/Y', strtotime($config['fecha_fin'])); ?>
                                <?php
                                $dias_restantes = (strtotime($config['fecha_fin']) - time()) / 86400;
                                if ($dias_restantes > 0) {
                                    echo " (" . ceil($dias_restantes) . " días restantes)";
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> 
                                No has establecido un presupuesto. Configura uno para controlar tus gastos.
                            </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalRegistrarGasto">
                            <i class="bi bi-plus-circle"></i> Registrar Gasto
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <i class="bi bi-cash-stack text-info" style="font-size: 36px;"></i>
                                <h4 class="mt-2">$<?php echo $config ? number_format($config['monto_presupuesto'], 0) : 0; ?></h4>
                                <small class="text-muted">Presupuesto Total</small>
                            </div>
                            <div class="col-6 mb-3">
                                <i class="bi bi-receipt text-danger" style="font-size: 36px;"></i>
                                <h4 class="mt-2"><?php echo count($historial_gastos); ?></h4>
                                <small class="text-muted">Gastos Registrados</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de Gastos del Presupuesto -->
        <div class="card stat-card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Historial de Gastos del Presupuesto</h5>
            </div>
            <div class="card-body">
                <?php if (empty($historial_gastos)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 64px; color: #ccc;"></i>
                        <p class="text-muted mt-3">No hay gastos registrados en el presupuesto</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Mes</th>
                                    <th>Descripción</th>
                                    <th>Categoría</th>
                                    <th>Monto Gastado</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($historial_gastos as $gasto): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($gasto['fecha'])); ?></td>
                                        <td><?php echo htmlspecialchars($gasto['mes']); ?></td>
                                        <td><?php echo htmlspecialchars($gasto['descripcion']); ?></td>
                                        <td>
                                            <?php
                                            $badge_color = 'secondary';
                                            if ($gasto['categoria'] === 'fijo') $badge_color = 'danger';
                                            elseif ($gasto['categoria'] === 'ocasional') $badge_color = 'warning';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?>">
                                                <?php echo ucfirst($gasto['categoria']); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-danger">$<?php echo number_format($gasto['monto_gastado'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($gasto['notas']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-danger fw-bold">
                                    <td colspan="4" class="text-end">TOTAL GASTADO:</td>
                                    <td>$<?php echo number_format($total_gastado, 2); ?></td>
                                    <td></td>
                                </tr>
                                <?php if ($config && $config['monto_presupuesto']): ?>
                                <tr class="table-<?php echo $disponible >= 0 ? 'success' : 'warning'; ?> fw-bold">
                                    <td colspan="4" class="text-end">DISPONIBLE:</td>
                                    <td class="<?php echo $disponible >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        $<?php echo number_format($disponible, 2); ?>
                                    </td>
                                    <td></td>
                                </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Registrar Gasto -->
    <div class="modal fade" id="modalRegistrarGasto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Registrar Gasto del Presupuesto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="registrar">
                        
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" id="fecha" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <input type="text" class="form-control" name="descripcion" id="descripcion" 
                                   placeholder="Ej: Supermercado, Gasolina, Servicios" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <select class="form-select" name="categoria" id="categoria" required>
                                <option value="">Seleccione...</option>
                                <option value="fijo">Fijo (Servicios, renta, etc.)</option>
                                <option value="ocasional">Ocasional (Compras grandes)</option>
                                <option value="hormiga">Hormiga (Gastos pequeños)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="monto_gastado" class="form-label">Monto Gastado *</label>
                            <input type="number" class="form-control form-control-lg" name="monto_gastado" 
                                   id="monto_gastado" step="0.01" min="0.01" required>
                        </div>
                        
                        <?php if ($config && $config['monto_presupuesto']): ?>
                        <div class="alert alert-info">
                            <small>
                                <i class="bi bi-info-circle"></i> 
                                Presupuesto disponible: $<?php echo number_format($disponible, 2); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="notas" class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" id="notas" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Gasto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Establecer fecha actual
        document.getElementById('fecha').valueAsDate = new Date();
        
        // Establecer fechas por defecto si están vacías
        if (!document.getElementById('fecha_inicio').value) {
            document.getElementById('fecha_inicio').valueAsDate = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
        }
        if (!document.getElementById('fecha_fin').value) {
            const lastDay = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0);
            document.getElementById('fecha_fin').valueAsDate = lastDay;
        }
    </script>
</body>
</html>