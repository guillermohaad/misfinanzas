<?php
require_once 'config/database.php';
iniciarSesion();
verificarAutenticacion();

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['usuario_id'];

// Obtener configuración de ahorro
$stmt = $db->prepare("SELECT porcentaje_ahorro FROM config_ahorro WHERE id_usuario = ? AND activo = 1 ORDER BY id_config DESC LIMIT 1");
$stmt->execute([$usuario_id]);
$config_ahorro = $stmt->fetch();
$porcentaje_ahorro = $config_ahorro ? $config_ahorro['porcentaje_ahorro'] : 10;

// Obtener configuración de presupuesto
$stmt = $db->prepare("SELECT * FROM config_presupuesto WHERE id_usuario = ? AND activo = 1 ORDER BY id_config DESC LIMIT 1");
$stmt->execute([$usuario_id]);
$config_presupuesto = $stmt->fetch();

// Obtener parámetros
$tipo_reporte = $_GET['tipo'] ?? 'trimestral';
$fecha_fin = date('Y-m-d');

// Calcular fecha de inicio según tipo de reporte
switch($tipo_reporte) {
    case 'trimestral':
        $fecha_inicio = date('Y-m-d', strtotime('-3 months'));
        $titulo_periodo = 'Últimos 3 Meses';
        break;
    case 'semestral':
        $fecha_inicio = date('Y-m-d', strtotime('-6 months'));
        $titulo_periodo = 'Últimos 6 Meses';
        break;
    case 'anual':
        $fecha_inicio = date('Y-m-d', strtotime('-12 months'));
        $titulo_periodo = 'Último Año';
        break;
    default:
        $fecha_inicio = date('Y-m-d', strtotime('-3 months'));
        $titulo_periodo = 'Últimos 3 Meses';
}

// Obtener datos de ingresos por mes
$stmt = $db->prepare("
    SELECT DATE_FORMAT(fecha, '%Y-%m') as periodo, 
           DATE_FORMAT(fecha, '%M %Y') as mes_nombre,
           SUM(monto) as total,
           frecuencia
    FROM ingresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY periodo, frecuencia
    ORDER BY periodo ASC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$ingresos_detalle = $stmt->fetchAll();

// Obtener datos de egresos por mes y categoría
$stmt = $db->prepare("
    SELECT DATE_FORMAT(fecha, '%Y-%m') as periodo,
           DATE_FORMAT(fecha, '%M %Y') as mes_nombre,
           SUM(monto) as total,
           categoria
    FROM egresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY periodo, categoria
    ORDER BY periodo ASC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$egresos_detalle = $stmt->fetchAll();

// Obtener gastos del presupuesto por categoría
$stmt = $db->prepare("
    SELECT categoria, SUM(monto_gastado) as total
    FROM presupuesto_gastos
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY categoria
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$gastos_presupuesto_cat = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Organizar datos por periodo
$periodos = [];
$stmt = $db->prepare("
    SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m') as periodo,
           DATE_FORMAT(fecha, '%M %Y') as mes_nombre
    FROM (
        SELECT fecha FROM ingresos WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
        UNION
        SELECT fecha FROM egresos WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    ) as fechas
    ORDER BY periodo ASC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin, $usuario_id, $fecha_inicio, $fecha_fin]);
$periodos_lista = $stmt->fetchAll();

// Calcular totales
$total_ingresos = 0;
$total_egresos = 0;
$total_gastado_presupuesto = 0;

foreach($ingresos_detalle as $ingreso) {
    $total_ingresos += $ingreso['total'];
}

foreach($egresos_detalle as $egreso) {
    $total_egresos += $egreso['total'];
}

// Calcular total gastado en presupuesto
$stmt = $db->prepare("SELECT COALESCE(SUM(monto_gastado), 0) as total FROM presupuesto_gastos WHERE id_usuario = ? AND fecha BETWEEN ? AND ?");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$total_gastado_presupuesto = $stmt->fetch()['total'];

// Calcular ahorro total
$total_ahorro = ($total_ingresos * $porcentaje_ahorro) / 100;
$balance_total = $total_ingresos - $total_egresos - $total_ahorro;

// Preparar datos para gráficas
$datos_mensuales = [];
foreach($periodos_lista as $periodo) {
    $per = $periodo['periodo'];
    
    $ingresos_mes = 0;
    foreach($ingresos_detalle as $ing) {
        if ($ing['periodo'] == $per) {
            $ingresos_mes += $ing['total'];
        }
    }
    
    $egresos_mes = 0;
    foreach($egresos_detalle as $egr) {
        if ($egr['periodo'] == $per) {
            $egresos_mes += $egr['total'];
        }
    }
    
    // Calcular ahorro del mes
    $ahorro_mes = ($ingresos_mes * $porcentaje_ahorro) / 100;
    
    $datos_mensuales[] = [
        'periodo' => $per,
        'mes' => $periodo['mes_nombre'],
        'ingresos' => $ingresos_mes,
        'egresos' => $egresos_mes,
        'ahorro' => $ahorro_mes,
        'balance' => $ingresos_mes - $egresos_mes - $ahorro_mes
    ];
}

// Datos para gráfica de presupuesto vs gastos reales por categoría
$categorias_gastos = ['fijo' => 0, 'ocasional' => 0, 'hormiga' => 0];
foreach($egresos_detalle as $egr) {
    if (isset($categorias_gastos[$egr['categoria']])) {
        $categorias_gastos[$egr['categoria']] += $egr['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Finanzas Personales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
        @media print {
            .no-print {
                display: none !important;
            }
            .card {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark no-print">
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
                        <a class="nav-link" href="presupuesto.php">
                            <i class="bi bi-calculator"></i> Presupuesto
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reportes.php">
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
    <div class="container-fluid mt-4" id="contenidoReporte">
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="bi bi-graph-up"></i> Reportes Financieros</h2>
                <p class="text-muted"><?php echo $titulo_periodo; ?></p>
            </div>
            <div class="col-md-6 text-end no-print">
                <div class="btn-group me-2">
                    <a href="?tipo=trimestral" class="btn btn-<?php echo $tipo_reporte === 'trimestral' ? 'primary' : 'outline-primary'; ?>">
                        Trimestral
                    </a>
                    <a href="?tipo=semestral" class="btn btn-<?php echo $tipo_reporte === 'semestral' ? 'primary' : 'outline-primary'; ?>">
                        Semestral
                    </a>
                    <a href="?tipo=anual" class="btn btn-<?php echo $tipo_reporte === 'anual' ? 'primary' : 'outline-primary'; ?>">
                        Anual
                    </a>
                </div>
                <button class="btn btn-danger" onclick="exportarPDF()">
                    <i class="bi bi-file-pdf"></i> Exportar PDF
                </button>
                <button class="btn btn-success" onclick="exportarExcel()">
                    <i class="bi bi-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>

        <!-- Resumen Ejecutivo -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body text-center">
                        <h6>Total Ingresos</h6>
                        <h2>$<?php echo number_format($total_ingresos, 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body text-center">
                        <h6>Total Egresos</h6>
                        <h2>$<?php echo number_format($total_egresos, 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body text-center">
                        <h6>Total Ahorros</h6>
                        <h2>$<?php echo number_format($total_ahorro, 2); ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card bg-<?php echo $balance_total >= 0 ? 'primary' : 'warning'; ?> text-white">
                    <div class="card-body text-center">
                        <h6>Balance</h6>
                        <h2>$<?php echo number_format($balance_total, 2); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficas -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card stat-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Evolución Mensual</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartEvolucion"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card stat-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Distribución de Gastos por Categoría</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartCategorias"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Análisis de Presupuesto vs Gastos Reales -->
        <?php if ($config_presupuesto): ?>
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card stat-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-calculator"></i> Análisis de Presupuesto vs Gastos Reales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h6 class="text-muted">Presupuesto Total</h6>
                                    <h3 class="text-primary">$<?php echo number_format($config_presupuesto['monto_presupuesto'], 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h6 class="text-muted">Total Gastado</h6>
                                    <h3 class="text-danger">$<?php echo number_format($total_gastado_presupuesto, 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h6 class="text-muted">Diferencia</h6>
                                    <h3 class="<?php echo ($config_presupuesto['monto_presupuesto'] - $total_gastado_presupuesto) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        $<?php echo number_format($config_presupuesto['monto_presupuesto'] - $total_gastado_presupuesto, 2); ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <canvas id="chartPresupuestoVsReal"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabla Detallada -->
        <div class="card stat-card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Detalle Mensual</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaDetalle">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Ingresos</th>
                                <th>Egresos</th>
                                <th>Ahorros</th>
                                <th>Balance</th>
                                <th>% Ahorro</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($datos_mensuales as $dato): ?>
                                <tr>
                                    <td><?php echo $dato['mes']; ?></td>
                                    <td class="text-success">$<?php echo number_format($dato['ingresos'], 2); ?></td>
                                    <td class="text-danger">$<?php echo number_format($dato['egresos'], 2); ?></td>
                                    <td class="text-warning">$<?php echo number_format($dato['ahorro'], 2); ?></td>
                                    <td class="fw-bold <?php echo $dato['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        $<?php echo number_format($dato['balance'], 2); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $porcentaje_ahorro_real = $dato['ingresos'] > 0 ? (($dato['balance'] / $dato['ingresos']) * 100) : 0;
                                        echo number_format($porcentaje_ahorro_real, 1) . '%';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>TOTAL</td>
                                <td class="text-success">$<?php echo number_format($total_ingresos, 2); ?></td>
                                <td class="text-danger">$<?php echo number_format($total_egresos, 2); ?></td>
                                <td class="text-warning">$<?php echo number_format($total_ahorro, 2); ?></td>
                                <td class="<?php echo $balance_total >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    $<?php echo number_format($balance_total, 2); ?>
                                </td>
                                <td>
                                    <?php 
                                    $porcentaje_total = $total_ingresos > 0 ? (($balance_total / $total_ingresos) * 100) : 0;
                                    echo number_format($porcentaje_total, 1) . '%';
                                    ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Datos para gráficas
        const datosMensuales = <?php echo json_encode($datos_mensuales); ?>;
        const categoriasGastos = <?php echo json_encode($categorias_gastos); ?>;
        
        // Gráfica de Evolución Mensual
        const ctxEvolucion = document.getElementById('chartEvolucion').getContext('2d');
        new Chart(ctxEvolucion, {
            type: 'bar',
            data: {
                labels: datosMensuales.map(d => d.mes),
                datasets: [
                    {
                        label: 'Ingresos',
                        data: datosMensuales.map(d => d.ingresos),
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Egresos',
                        data: datosMensuales.map(d => d.egresos),
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Balance',
                        data: datosMensuales.map(d => d.balance),
                        type: 'line',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
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
                                return '$' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfica de Categorías
        const ctxCategorias = document.getElementById('chartCategorias').getContext('2d');
        new Chart(ctxCategorias, {
            type: 'doughnut',
            data: {
                labels: ['Gastos Fijos', 'Gastos Ocasionales', 'Gastos Hormiga'],
                datasets: [{
                    data: [
                        categoriasGastos.fijo,
                        categoriasGastos.ocasional,
                        categoriasGastos.hormiga
                    ],
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(108, 117, 125, 0.8)'
                    ],
                    borderColor: [
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ':  + value.toFixed(2) + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        <?php if ($config_presupuesto): ?>
        // Gráfica de Presupuesto vs Gastos Reales
        const ctxPresupuesto = document.getElementById('chartPresupuestoVsReal').getContext('2d');
        new Chart(ctxPresupuesto, {
            type: 'bar',
            data: {
                labels: ['Presupuestado', 'Gastado'],
                datasets: [{
                    label: 'Comparación',
                    data: [
                        <?php echo $config_presupuesto['monto_presupuesto']; ?>,
                        <?php echo $total_gastado_presupuesto; ?>
                    ],
                    backgroundColor: [
                        'rgba(23, 162, 184, 0.7)',
                        'rgba(220, 53, 69, 0.7)'
                    ],
                    borderColor: [
                        'rgba(23, 162, 184, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return ' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Función para exportar a PDF
        function exportarPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('REPORTE FINANCIERO COMPLETO', 105, 20, { align: 'center' });
                
                doc.setFontSize(11);
                doc.setFont(undefined, 'normal');
                doc.text('<?php echo $titulo_periodo; ?>', 105, 28, { align: 'center' });
                doc.text('Fecha: <?php echo date("d/m/Y"); ?>', 105, 34, { align: 'center' });
                
                doc.setLineWidth(0.5);
                doc.line(20, 38, 190, 38);
                
                let yPos = 45;
                
                // Resumen Ejecutivo
                doc.setFontSize(12);
                doc.setFont(undefined, 'bold');
                doc.text('RESUMEN EJECUTIVO', 20, yPos);
                yPos += 8;
                
                doc.setFontSize(10);
                doc.setFont(undefined, 'normal');
                doc.text('Total Ingresos:', 25, yPos);
                doc.setTextColor(40, 167, 69);
                doc.text('$<?php echo number_format($total_ingresos, 2); ?>', 70, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 6;
                
                doc.text('Total Egresos:', 25, yPos);
                doc.setTextColor(220, 53, 69);
                doc.text('$<?php echo number_format($total_egresos, 2); ?>', 70, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 6;
                
                doc.text('Total Ahorros:', 25, yPos);
                doc.setTextColor(255, 193, 7);
                doc.text('$<?php echo number_format($total_ahorro, 2); ?>', 70, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 6;
                
                doc.text('Balance Total:', 25, yPos);
                <?php if ($balance_total >= 0): ?>
                doc.setTextColor(40, 167, 69);
                <?php else: ?>
                doc.setTextColor(220, 53, 69);
                <?php endif; ?>
                doc.text('$<?php echo number_format($balance_total, 2); ?>', 70, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 10;
                
                <?php if ($config_presupuesto): ?>
                // Análisis de Presupuesto
                doc.setFontSize(12);
                doc.setFont(undefined, 'bold');
                doc.text('ANÁLISIS DE PRESUPUESTO', 20, yPos);
                yPos += 8;
                
                doc.setFontSize(10);
                doc.setFont(undefined, 'normal');
                doc.text('Presupuesto Total:', 25, yPos);
                doc.text('$<?php echo number_format($config_presupuesto['monto_presupuesto'], 2); ?>', 70, yPos);
                yPos += 6;
                
                doc.text('Total Gastado:', 25, yPos);
                doc.setTextColor(220, 53, 69);
                doc.text('$<?php echo number_format($total_gastado_presupuesto, 2); ?>', 70, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 6;
                
                doc.text('Disponible:', 25, yPos);
                <?php if (($config_presupuesto['monto_presupuesto'] - $total_gastado_presupuesto) >= 0): ?>
                doc.setTextColor(40, 167, 69);
                <?php else: ?>
                doc.setTextColor(220, 53, 69);
                <?php endif; ?>
                doc.text('$<?php echo number_format($config_presupuesto['monto_presupuesto'] - $total_gastado_presupuesto, 2); ?>', 70, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 10;
                <?php endif; ?>
                
                // Tabla Detallada
                doc.setFontSize(12);
                doc.setFont(undefined, 'bold');
                doc.text('DETALLE MENSUAL', 20, yPos);
                yPos += 8;
                
                doc.setFontSize(9);
                doc.setFont(undefined, 'bold');
                doc.text('Mes', 25, yPos);
                doc.text('Ingresos', 65, yPos);
                doc.text('Egresos', 95, yPos);
                doc.text('Ahorros', 125, yPos);
                doc.text('Balance', 155, yPos);
                
                yPos += 2;
                doc.line(20, yPos, 190, yPos);
                yPos += 5;
                
                doc.setFont(undefined, 'normal');
                <?php foreach($datos_mensuales as $dato): ?>
                if (yPos > 270) {
                    doc.addPage();
                    yPos = 20;
                }
                doc.text('<?php echo substr($dato['mes'], 0, 8); ?>', 25, yPos);
                doc.setTextColor(40, 167, 69);
                doc.text('$<?php echo number_format($dato['ingresos'], 0); ?>', 65, yPos);
                doc.setTextColor(220, 53, 69);
                doc.text('$<?php echo number_format($dato['egresos'], 0); ?>', 95, yPos);
                doc.setTextColor(255, 193, 7);
                doc.text('$<?php echo number_format($dato['ahorro'], 0); ?>', 125, yPos);
                <?php if ($dato['balance'] >= 0): ?>
                doc.setTextColor(40, 167, 69);
                <?php else: ?>
                doc.setTextColor(220, 53, 69);
                <?php endif; ?>
                doc.text('$<?php echo number_format($dato['balance'], 0); ?>', 155, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 6;
                <?php endforeach; ?>
                
                yPos += 2;
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                
                doc.save('reporte_financiero_completo_<?php echo date("Y-m-d"); ?>.pdf');
                alert('PDF generado exitosamente');
            } catch (error) {
                console.error('Error al generar PDF:', error);
                alert('Error al generar PDF: ' + error.message);
            }
        }
        
        // Función para exportar a Excel
        function exportarExcel() {
            try {
                const datos = [
                    ['REPORTE FINANCIERO COMPLETO'],
                    ['<?php echo $titulo_periodo; ?>'],
                    ['Fecha: <?php echo date("d/m/Y"); ?>'],
                    [],
                    ['RESUMEN EJECUTIVO'],
                    ['Concepto', 'Monto'],
                    ['Total Ingresos', <?php echo $total_ingresos; ?>],
                    ['Total Egresos', <?php echo $total_egresos; ?>],
                    ['Total Ahorros', <?php echo $total_ahorro; ?>],
                    ['Balance Total', <?php echo $balance_total; ?>],
                    []
                ];
                
                <?php if ($config_presupuesto): ?>
                datos.push(['ANÁLISIS DE PRESUPUESTO']);
                datos.push(['Concepto', 'Monto']);
                datos.push(['Presupuesto Total', <?php echo $config_presupuesto['monto_presupuesto']; ?>]);
                datos.push(['Total Gastado', <?php echo $total_gastado_presupuesto; ?>]);
                datos.push(['Disponible', <?php echo $config_presupuesto['monto_presupuesto'] - $total_gastado_presupuesto; ?>]);
                datos.push([]);
                <?php endif; ?>
                
                datos.push(['DETALLE MENSUAL']);
                datos.push(['Mes', 'Ingresos', 'Egresos', 'Ahorros', 'Balance', '% Ahorro']);
                
                <?php foreach($datos_mensuales as $dato): 
                    $porcentaje_ahorro_real = $dato['ingresos'] > 0 ? (($dato['balance'] / $dato['ingresos']) * 100) : 0;
                ?>
                datos.push([
                    '<?php echo $dato['mes']; ?>',
                    <?php echo $dato['ingresos']; ?>,
                    <?php echo $dato['egresos']; ?>,
                    <?php echo $dato['ahorro']; ?>,
                    <?php echo $dato['balance']; ?>,
                    <?php echo number_format($porcentaje_ahorro_real, 2, '.', ''); ?>
                ]);
                <?php endforeach; ?>
                
                datos.push([]);
                datos.push([
                    'TOTAL',
                    <?php echo $total_ingresos; ?>,
                    <?php echo $total_egresos; ?>,
                    <?php echo $total_ahorro; ?>,
                    <?php echo $balance_total; ?>,
                    <?php 
                    $porcentaje_total = $total_ingresos > 0 ? (($balance_total / $total_ingresos) * 100) : 0;
                    echo number_format($porcentaje_total, 2, '.', ''); 
                    ?>
                ]);
                
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(datos);
                
                ws['!cols'] = [
                    { wch: 20 },
                    { wch: 15 },
                    { wch: 15 },
                    { wch: 15 },
                    { wch: 15 },
                    { wch: 10 }
                ];
                
                XLSX.utils.book_append_sheet(wb, ws, 'Reporte Completo');
                XLSX.writeFile(wb, 'reporte_financiero_completo_<?php echo date("Y-m-d"); ?>.xlsx');
                alert('Excel generado exitosamente');
            } catch (error) {
                console.error('Error al generar Excel:', error);
                alert('Error al generar Excel: ' + error.message);
            }
        }
    </script>
</body>
</html>