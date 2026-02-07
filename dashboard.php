<?php
require_once 'config/database.php';
iniciarSesion();
verificarAutenticacion();

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['usuario_id'];

// Obtener filtros
$filtro_tipo = $_GET['filtro'] ?? 'mes_actual';
$mes_seleccionado = $_GET['mes'] ?? date('Y-m');
$fecha_inicio_rango = $_GET['fecha_inicio'] ?? '';
$fecha_fin_rango = $_GET['fecha_fin'] ?? '';

// Determinar fechas según filtro
switch($filtro_tipo) {
    case 'mes_especifico':
        $fecha_inicio = date('Y-m-01', strtotime($mes_seleccionado));
        $fecha_fin = date('Y-m-t', strtotime($mes_seleccionado));
        $titulo_periodo = date('F Y', strtotime($mes_seleccionado));
        break;
    case 'rango':
        if (!empty($fecha_inicio_rango) && !empty($fecha_fin_rango)) {
            $fecha_inicio = $fecha_inicio_rango;
            $fecha_fin = $fecha_fin_rango;
            $titulo_periodo = date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin));
        } else {
            $fecha_inicio = date('Y-m-01');
            $fecha_fin = date('Y-m-t');
            $titulo_periodo = date('F Y');
        }
        break;
    default: // mes_actual
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
        $titulo_periodo = date('F Y');
}

$mes_actual = date('Y-m', strtotime($fecha_inicio));

// Obtener totales ACUMULADOS (históricos totales)
$stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM ingresos WHERE id_usuario = ?");
$stmt->execute([$usuario_id]);
$total_ingresos_acumulado = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM egresos WHERE id_usuario = ?");
$stmt->execute([$usuario_id]);
$total_egresos_acumulado = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(monto_ahorrado), 0) as total FROM ahorros WHERE id_usuario = ?");
$stmt->execute([$usuario_id]);
$total_ahorro_acumulado = $stmt->fetch()['total'];

$balance_acumulado = $total_ingresos_acumulado - $total_egresos_acumulado - $total_ahorro_acumulado;

// Obtener totales del PERIODO FILTRADO
$stmt = $db->prepare("
    SELECT COALESCE(SUM(monto), 0) as total 
    FROM ingresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$total_ingresos = $stmt->fetch()['total'];

$stmt = $db->prepare("
    SELECT COALESCE(SUM(monto), 0) as total 
    FROM egresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$total_egresos = $stmt->fetch()['total'];

// Obtener configuración de ahorro
$stmt = $db->prepare("SELECT porcentaje_ahorro FROM config_ahorro WHERE id_usuario = ? AND activo = 1 ORDER BY id_config DESC LIMIT 1");
$stmt->execute([$usuario_id]);
$config_ahorro = $stmt->fetch();
$porcentaje_ahorro = $config_ahorro ? $config_ahorro['porcentaje_ahorro'] : 10;
$monto_ahorro = ($total_ingresos * $porcentaje_ahorro) / 100;

// Obtener configuración de presupuesto
$stmt = $db->prepare("SELECT * FROM config_presupuesto WHERE id_usuario = ? AND activo = 1 ORDER BY id_config DESC LIMIT 1");
$stmt->execute([$usuario_id]);
$config_presupuesto = $stmt->fetch();

// Calcular gastos del presupuesto
$total_gastado_presupuesto = 0;
$progreso_presupuesto = 0;
$alerta_presupuesto = '';
if ($config_presupuesto) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto_gastado), 0) as total FROM presupuesto_gastos WHERE id_usuario = ?");
    $stmt->execute([$usuario_id]);
    $total_gastado_presupuesto = $stmt->fetch()['total'];
    
    if ($config_presupuesto['monto_presupuesto'] > 0) {
        $progreso_presupuesto = ($total_gastado_presupuesto / $config_presupuesto['monto_presupuesto']) * 100;
        
        if ($progreso_presupuesto >= 100) {
            $alerta_presupuesto = 'danger';
        } elseif ($progreso_presupuesto >= 90) {
            $alerta_presupuesto = 'warning';
        } elseif ($progreso_presupuesto >= 70) {
            $alerta_presupuesto = 'info';
        }
    }
}

// Balance del periodo
$balance = $total_ingresos - $total_egresos - $monto_ahorro;

// Obtener resumen detallado de ingresos
$stmt = $db->prepare("
    SELECT descripcion, SUM(monto) as total, frecuencia
    FROM ingresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY descripcion, frecuencia
    ORDER BY frecuencia, total DESC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$detalle_ingresos = $stmt->fetchAll();

// Obtener resumen detallado de egresos
$stmt = $db->prepare("
    SELECT descripcion, SUM(monto) as total, categoria
    FROM egresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY descripcion, categoria
    ORDER BY categoria, total DESC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$detalle_egresos = $stmt->fetchAll();

// Obtener últimas transacciones
$stmt = $db->prepare("
    (SELECT 'ingreso' as tipo, fecha, descripcion, monto FROM ingresos WHERE id_usuario = ? ORDER BY fecha DESC LIMIT 5)
    UNION
    (SELECT 'egreso' as tipo, fecha, descripcion, monto FROM egresos WHERE id_usuario = ? ORDER BY fecha DESC LIMIT 5)
    ORDER BY fecha DESC LIMIT 10
");
$stmt->execute([$usuario_id, $usuario_id]);
$ultimas_transacciones = $stmt->fetchAll();

// Obtener datos para gráfica de los últimos 6 meses
$datos_grafica = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $mes_label = date('M Y', strtotime("-$i months"));
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM ingresos WHERE id_usuario = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
    $stmt->execute([$usuario_id, $mes]);
    $ingresos = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM egresos WHERE id_usuario = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
    $stmt->execute([$usuario_id, $mes]);
    $egresos = $stmt->fetch()['total'];
    
    $datos_grafica[] = [
        'mes' => $mes_label,
        'ingresos' => floatval($ingresos),
        'egresos' => floatval($egresos)
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Finanzas Personales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .bg-gradient-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .bg-gradient-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        
        .bg-gradient-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }

        .bg-gradient-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .transaction-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s;
        }
        
        .transaction-item:hover {
            background-color: #f8f9fa;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }

        .quick-access-btn {
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            border: 2px solid transparent;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        
        .quick-access-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border-color: var(--primary-color);
        }
        
        .quick-access-btn i {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .acumulado-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            background: rgba(255,255,255,0.2);
        }

        .presupuesto-card {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
        }

        .presupuesto-progress {
            height: 40px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            overflow: hidden;
        }

        .presupuesto-info {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
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
                        <a class="nav-link active" href="dashboard.php">
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
                <h2>Dashboard Financiero</h2>
                <p class="text-muted">Gestiona tus finanzas de manera inteligente</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-card">
            <form method="GET" action="dashboard.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="bi bi-funnel"></i> Tipo de Filtro</label>
                    <select name="filtro" id="filtro_tipo" class="form-select" onchange="toggleFiltros()">
                        <option value="mes_actual" <?php echo $filtro_tipo === 'mes_actual' ? 'selected' : ''; ?>>Mes Actual</option>
                        <option value="mes_especifico" <?php echo $filtro_tipo === 'mes_especifico' ? 'selected' : ''; ?>>Mes Específico</option>
                        <option value="rango" <?php echo $filtro_tipo === 'rango' ? 'selected' : ''; ?>>Rango de Fechas</option>
                    </select>
                </div>
                
                <div class="col-md-3" id="filtro_mes" style="display: <?php echo $filtro_tipo === 'mes_especifico' ? 'block' : 'none'; ?>;">
                    <label class="form-label fw-bold"><i class="bi bi-calendar-month"></i> Seleccionar Mes</label>
                    <input type="month" name="mes" class="form-control" value="<?php echo $mes_seleccionado; ?>">
                </div>
                
                <div class="col-md-3" id="filtro_fecha_inicio" style="display: <?php echo $filtro_tipo === 'rango' ? 'block' : 'none'; ?>;">
                    <label class="form-label fw-bold"><i class="bi bi-calendar-check"></i> Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $fecha_inicio_rango; ?>">
                </div>
                
                <div class="col-md-3" id="filtro_fecha_fin" style="display: <?php echo $filtro_tipo === 'rango' ? 'block' : 'none'; ?>;">
                    <label class="form-label fw-bold"><i class="bi bi-calendar-x"></i> Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?php echo $fecha_fin_rango; ?>">
                </div>
                
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Aplicar Filtro
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Alertas de Presupuesto -->
        <?php if (!empty($alerta_presupuesto)): ?>
            <div class="alert alert-<?php echo $alerta_presupuesto; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Alerta de Presupuesto:</strong>
                <?php if ($progreso_presupuesto >= 100): ?>
                    Has excedido tu presupuesto. Gastado: $<?php echo number_format($total_gastado_presupuesto, 2); ?> 
                    de $<?php echo number_format($config_presupuesto['monto_presupuesto'], 2); ?>
                <?php elseif ($progreso_presupuesto >= 90): ?>
                    Estás cerca del límite de tu presupuesto (<?php echo number_format($progreso_presupuesto, 1); ?>%). 
                    Disponible: $<?php echo number_format($config_presupuesto['monto_presupuesto'] - $total_gastado_presupuesto, 2); ?>
                <?php else: ?>
                    Has usado el <?php echo number_format($progreso_presupuesto, 1); ?>% de tu presupuesto.
                <?php endif; ?>
                <a href="presupuesto.php" class="alert-link ms-2">Ver detalles</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- TARJETAS ACUMULADAS (Totales Históricos) -->
        <div class="row mb-3">
            <div class="col-12">
                <h5 class="text-muted"><i class="bi bi-clock-history"></i> Acumulados Históricos</h5>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card" style="border-left: 4px solid #28a745;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="acumulado-badge bg-success text-white">HISTÓRICO</span>
                            <i class="bi bi-arrow-down-circle text-success" style="font-size: 24px;"></i>
                        </div>
                        <p class="text-muted mb-1 small">Total Ingresos</p>
                        <h3 class="mb-0 text-success">$<?php echo number_format($total_ingresos_acumulado, 0, ',', '.'); ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card" style="border-left: 4px solid #dc3545;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="acumulado-badge bg-danger text-white">HISTÓRICO</span>
                            <i class="bi bi-arrow-up-circle text-danger" style="font-size: 24px;"></i>
                        </div>
                        <p class="text-muted mb-1 small">Total Egresos</p>
                        <h3 class="mb-0 text-danger">$<?php echo number_format($total_egresos_acumulado, 0, ',', '.'); ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card" style="border-left: 4px solid #ffc107;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="acumulado-badge bg-warning text-dark">HISTÓRICO</span>
                            <i class="bi bi-piggy-bank text-warning" style="font-size: 24px;"></i>
                        </div>
                        <p class="text-muted mb-1 small">Total Ahorrado</p>
                        <h3 class="mb-0 text-warning">$<?php echo number_format($total_ahorro_acumulado, 0, ',', '.'); ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card" style="border-left: 4px solid <?php echo $balance_acumulado >= 0 ? '#667eea' : '#dc3545'; ?>;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="acumulado-badge <?php echo $balance_acumulado >= 0 ? 'bg-primary' : 'bg-danger'; ?> text-white">HISTÓRICO</span>
                            <i class="bi bi-wallet2 <?php echo $balance_acumulado >= 0 ? 'text-primary' : 'text-danger'; ?>" style="font-size: 24px;"></i>
                        </div>
                        <p class="text-muted mb-1 small">Balance Total</p>
                        <h3 class="mb-0 <?php echo $balance_acumulado >= 0 ? 'text-primary' : 'text-danger'; ?>">
                            $<?php echo number_format($balance_acumulado, 0, ',', '.'); ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- TARJETAS DEL PERIODO ACTUAL/FILTRADO -->
        <div class="row mb-3">
            <div class="col-12">
                <h5 class="text-muted"><i class="bi bi-calendar-range"></i> Periodo: <?php echo $titulo_periodo; ?></h5>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Ingresos</p>
                                <h3 class="mb-0">$<?php echo number_format($total_ingresos, 2); ?></h3>
                            </div>
                            <div class="stat-icon bg-gradient-success text-white">
                                <i class="bi bi-arrow-down-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Egresos</p>
                                <h3 class="mb-0">$<?php echo number_format($total_egresos, 2); ?></h3>
                            </div>
                            <div class="stat-icon bg-gradient-danger text-white">
                                <i class="bi bi-arrow-up-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Balance</p>
                                <h3 class="mb-0 <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    $<?php echo number_format($balance, 2); ?>
                                </h3>
                            </div>
                            <div class="stat-icon bg-gradient-primary text-white">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Ahorro (<?php echo $porcentaje_ahorro; ?>%)</p>
                                <h3 class="mb-0">$<?php echo number_format($monto_ahorro, 2); ?></h3>
                            </div>
                            <div class="stat-icon bg-gradient-warning text-white">
                                <i class="bi bi-piggy-bank"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accesos Rápidos -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="bi bi-lightning-fill"></i> Accesos Rápidos</h5>
            </div>
            <div class="col-md-3">
                <a href="ingresos.php" class="quick-access-btn bg-white">
                    <i class="bi bi-plus-circle text-success"></i>
                    <h6 class="mb-0">Agregar Ingreso</h6>
                </a>
            </div>
            <div class="col-md-3">
                <a href="egresos.php" class="quick-access-btn bg-white">
                    <i class="bi bi-dash-circle text-danger"></i>
                    <h6 class="mb-0">Registrar Egreso</h6>
                </a>
            </div>
            <div class="col-md-3">
                <a href="ahorros.php" class="quick-access-btn bg-white">
                    <i class="bi bi-piggy-bank text-warning"></i>
                    <h6 class="mb-0">Gestionar Ahorros</h6>
                </a>
            </div>
            <div class="col-md-3">
                <a href="presupuesto.php" class="quick-access-btn bg-white">
                    <i class="bi bi-calculator text-info"></i>
                    <h6 class="mb-0">Ver Presupuesto</h6>
                </a>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Gráfica de Ingresos vs Egresos -->
            <div class="col-lg-8">
                <div class="card stat-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Historial de 6 Meses</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartIngresosEgresos"></canvas>
                    </div>
                </div>
            </div>

            <!-- Últimas Transacciones -->
            <div class="col-lg-4">
                <div class="card stat-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Últimas Transacciones</h5>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($ultimas_transacciones)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                <p class="mt-2">No hay transacciones registradas</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($ultimas_transacciones as $trans): ?>
                                <div class="transaction-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <i class="bi <?php echo $trans['tipo'] === 'ingreso' ? 'bi-arrow-down-circle text-success' : 'bi-arrow-up-circle text-danger'; ?> me-2"></i>
                                                <strong><?php echo htmlspecialchars($trans['descripcion']); ?></strong>
                                            </div>
                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($trans['fecha'])); ?></small>
                                        </div>
                                        <span class="fw-bold <?php echo $trans['tipo'] === 'ingreso' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $trans['tipo'] === 'ingreso' ? '+' : '-'; ?>$<?php echo number_format($trans['monto'], 2); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Presupuesto Mejorada -->
        <?php if ($config_presupuesto): ?>
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="presupuesto-card">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h3 class="mb-1"><i class="bi bi-calculator"></i> Control de Presupuesto</h3>
                                    <p class="mb-0 opacity-75"><?php echo htmlspecialchars($config_presupuesto['descripcion_presupuesto']); ?></p>
                                </div>
                                <a href="presupuesto.php" class="btn btn-light">
                                    <i class="bi bi-arrow-right-circle"></i> Gestionar
                                </a>
                            </div>
                            
                            <div class="presupuesto-progress mb-3">
                                <div class="progress-bar <?php echo $progreso_presupuesto >= 90 ? 'bg-danger' : ($progreso_presupuesto >= 70 ? 'bg-warning' : 'bg-light'); ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo min($progreso_presupuesto, 100); ?>%;">
                                    <strong style="font-size: 1.2rem;"><?php echo number_format($progreso_presupuesto, 1); ?>%</strong>
                                </div>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="presupuesto-info text-center">
                                        <i class="bi bi-wallet2 mb-2" style="font-size: 24px;"></i>
                                        <p class="mb-1 small opacity-75">Presupuesto Total</p>
                                        <h4 class="mb-0">$<?php echo number_format($config_presupuesto['monto_presupuesto'], 0, ',', '.'); ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="presupuesto-info text-center">
                                        <i class="bi bi-cash-stack mb-2" style="font-size: 24px;"></i>
                                        <p class="mb-1 small opacity-75">Gastado</p>
                                        <h4 class="mb-0">$<?php echo number_format($total_gastado_presupuesto, 0, ',', '.'); ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="presupuesto-info text-center">
                                        <i class="bi bi-piggy-bank-fill mb-2" style="font-size: 24px;"></i>
                                        <p class="mb-1 small opacity-75">Disponible</p>
                                        <h4 class="mb-0 <?php echo ($config_presupuesto['monto_presupuesto'] - $total_gastado_presupuesto) >= 0 ? '' : 'text-danger'; ?>">
                                            $<?php echo number_format($config_presupuesto['monto_presupuesto'] - $total_gastado_presupuesto, 0, ',', '.'); ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="bi <?php echo $progreso_presupuesto >= 90 ? 'bi-exclamation-triangle-fill' : 'bi-graph-up-arrow'; ?>" 
                                       style="font-size: 80px; opacity: 0.8;"></i>
                                </div>
                                <?php if ($progreso_presupuesto >= 100): ?>
                                    <div class="alert alert-danger mb-0">
                                        <strong>¡Presupuesto Excedido!</strong><br>
                                        <small>Has superado tu límite en $<?php echo number_format($total_gastado_presupuesto - $config_presupuesto['monto_presupuesto'], 0, ',', '.'); ?></small>
                                    </div>
                                <?php elseif ($progreso_presupuesto >= 90): ?>
                                    <div class="alert alert-warning mb-0">
                                        <strong>¡Alerta!</strong><br>
                                        <small>Estás cerca del límite de tu presupuesto</small>
                                    </div>
                                <?php elseif ($progreso_presupuesto >= 70): ?>
                                    <div class="alert alert-info mb-0">
                                        <strong>Atención</strong><br>
                                        <small>Has usado más del 70% de tu presupuesto</small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-light mb-0">
                                        <strong>¡Bien!</strong><br>
                                        <small>Tu presupuesto está bajo control</small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($config_presupuesto['fecha_fin']): ?>
                                <div class="mt-3">
                                    <small class="opacity-75">
                                        <i class="bi bi-calendar-event"></i> 
                                        Vigente hasta: <?php echo date('d/m/Y', strtotime($config_presupuesto['fecha_fin'])); ?>
                                        <?php
                                        $dias_restantes = (strtotime($config_presupuesto['fecha_fin']) - time()) / 86400;
                                        if ($dias_restantes > 0) {
                                            echo " (" . ceil($dias_restantes) . " días)";
                                        }
                                        ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabla de Resumen Financiero del Mes -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card stat-card">
                    <div class="card-header text-white text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <h5 class="mb-0"><i class="bi bi-table"></i> RESUMEN O EPITOME FINANCIERO</h5>
                        <small class="opacity-75"><?php echo $titulo_periodo; ?></small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="table-dark text-center">
                                    <tr>
                                        <th style="width: 50px;">ITEM</th>
                                        <th>DESCRIPCION</th>
                                        <th style="width: 150px;">INGRESOS</th>
                                        <th style="width: 150px;">EGRESOS</th>
                                        <th style="width: 100px;">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $item = 1;
                                    $total_ingresos_detalle = 0;
                                    $total_egresos_detalle = 0;
                                    
                                    // Mostrar Ingresos
                                    foreach($detalle_ingresos as $ingreso): 
                                        $total_ingresos_detalle += $ingreso['total'];
                                        $porcentaje = $total_ingresos > 0 ? ($ingreso['total'] / $total_ingresos * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><?php echo htmlspecialchars($ingreso['descripcion']); ?></td>
                                        <td class="text-end text-success fw-bold">$<?php echo number_format($ingreso['total'], 0, ',', '.'); ?></td>
                                        <td class="text-end"></td>
                                        <td class="text-center"><?php echo number_format($porcentaje, 2); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- Línea vacía separadora -->
                                    <?php if (!empty($detalle_ingresos) && !empty($detalle_egresos)): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td colspan="4"></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <!-- Mostrar Egresos -->
                                    <?php foreach($detalle_egresos as $egreso): 
                                        $total_egresos_detalle += $egreso['total'];
                                        $porcentaje = $total_ingresos > 0 ? ($egreso['total'] / $total_ingresos * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><?php echo htmlspecialchars($egreso['descripcion']); ?></td>
                                        <td class="text-end"></td>
                                        <td class="text-end text-danger fw-bold">$<?php echo number_format($egreso['total'], 0, ',', '.'); ?></td>
                                        <td class="text-center"><?php echo number_format($porcentaje, 2); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- Línea vacía antes del ahorro -->
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td colspan="4"></td>
                                    </tr>
                                    
                                    <!-- Ahorro Programado -->
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><strong>Ahorro programado</strong></td>
                                        <td class="text-end"></td>
                                        <td class="text-end text-warning fw-bold">$<?php echo number_format($monto_ahorro, 0, ',', '.'); ?></td>
                                        <td class="text-center"><?php echo number_format($monto_ahorro / max($total_ingresos, 1) * 100, 2); ?>%</td>
                                    </tr>
                                    
                                    <!-- Líneas vacías finales -->
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td colspan="4"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td colspan="4"></td>
                                    </tr>
                                    
                                    <!-- Subtotales -->
                                    <tr class="table-warning">
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><strong>Subtotales</strong></td>
                                        <td class="text-end fw-bold">$<?php echo number_format($total_ingresos, 0, ',', '.'); ?></td>
                                        <td class="text-end fw-bold">$<?php echo number_format($total_egresos + $monto_ahorro, 0, ',', '.'); ?></td>
                                        <td class="text-center fw-bold"><?php echo number_format(($total_egresos + $monto_ahorro) / max($total_ingresos, 1) * 100, 2); ?>%</td>
                                    </tr>
                                    
                                    <!-- Balance General -->
                                    <tr class="table-success">
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><strong>Balance General</strong></td>
                                        <td class="text-end fw-bold fs-5 <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            $<?php echo number_format($balance, 0, ',', '.'); ?>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($balance < 0): ?>
                            <div class="alert alert-danger m-3 mb-0">
                                <i class="bi bi-exclamation-triangle-fill"></i> 
                                <strong>Atención:</strong> Tus egresos y ahorros superan tus ingresos. Considera ajustar tu presupuesto.
                            </div>
                        <?php elseif ($balance > 0 && $balance < ($total_ingresos * 0.1)): ?>
                            <div class="alert alert-warning m-3 mb-0">
                                <i class="bi bi-info-circle-fill"></i> 
                                Tu balance es bajo. Considera reducir gastos o aumentar tu porcentaje de ahorro.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para mostrar/ocultar filtros
        function toggleFiltros() {
            const tipo = document.getElementById('filtro_tipo').value;
            document.getElementById('filtro_mes').style.display = tipo === 'mes_especifico' ? 'block' : 'none';
            document.getElementById('filtro_fecha_inicio').style.display = tipo === 'rango' ? 'block' : 'none';
            document.getElementById('filtro_fecha_fin').style.display = tipo === 'rango' ? 'block' : 'none';
        }

        // Datos para la gráfica
        const datosGrafica = <?php echo json_encode($datos_grafica); ?>;
        
        const ctx = document.getElementById('chartIngresosEgresos').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: datosGrafica.map(d => d.mes),
                datasets: [
                    {
                        label: 'Ingresos',
                        data: datosGrafica.map(d => d.ingresos),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Egresos',
                        data: datosGrafica.map(d => d.egresos),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 