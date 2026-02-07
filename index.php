<?php
// Si el usuario ya está autenticado, redirigir al dashboard
session_start();
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanzas Personales - Controla tu dinero, alcanza tus metas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Hero Section */
        .hero-section {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            color: rgba(255,255,255,0.95);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .hero-buttons .btn {
            padding: 15px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            font-weight: 600;
            margin: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .btn-light-custom {
            background: white;
            color: #667eea;
            border: none;
        }
        
        .btn-light-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            background: #f8f9fa;
            color: #667eea;
        }
        
        .btn-outline-light-custom {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline-light-custom:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        /* Hero Image */
        .hero-image {
            position: relative;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .dashboard-preview {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: #f8f9fa;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
            color: #2d3748;
        }
        
        .section-subtitle {
            text-align: center;
            color: #718096;
            font-size: 1.2rem;
            margin-bottom: 4rem;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
            color: white;
        }
        
        .feature-icon.bg-primary-gradient {
            background: var(--primary-gradient);
        }
        
        .feature-icon.bg-secondary-gradient {
            background: var(--secondary-gradient);
        }
        
        .feature-icon.bg-success-gradient {
            background: var(--success-gradient);
        }
        
        .feature-card h4 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2d3748;
        }
        
        .feature-card p {
            color: #718096;
            line-height: 1.7;
            margin: 0;
        }
        
        /* Stats Section */
        .stats-section {
            background: var(--primary-gradient);
            padding: 80px 0;
            color: white;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        /* CTA Section */
        .cta-section {
            padding: 100px 0;
            background: white;
        }
        
        .cta-card {
            background: var(--primary-gradient);
            border-radius: 30px;
            padding: 60px 40px;
            text-align: center;
            color: white;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .cta-text {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        
        /* Footer */
        .footer {
            background: #2d3748;
            color: white;
            padding: 40px 0 20px;
        }
        
        .footer-links a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            margin: 0 15px;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .hero-image {
                margin-top: 3rem;
            }
        }
    </style>
</head>
<body>
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark position-absolute w-100" style="z-index: 1000;">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-wallet2 me-2"></i>Finanzas Personales
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#caracteristicas">Características</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#estadisticas">Estadísticas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm px-4 ms-2" href="registro.php">Registrarse</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header con imagen de fondo -->
    <header style="background-image: url('images/imagen.png'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 600px; position: relative;">
        <!-- Capa de opacidad -->
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.3); z-index: 1;"></div>
        
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark" style="background: rgba(102, 126, 234, 0.9); position: relative; z-index: 2;">
            <div class="container">
                <a class="navbar-brand fw-bold" href="#">
                    <i class="bi bi-wallet2 me-2"></i>Finanzas Personales
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="#caracteristicas">Características</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#estadisticas">Estadísticas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Iniciar Sesión</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-light btn-sm px-4 ms-2" href="registro.php">Registrarse</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="hero-title">Controla tu dinero, alcanza tus metas</h1>
                    <p class="hero-subtitle">
                        Lleva el control total de tus finanzas personales. Registra ingresos, controla gastos y 
                        alcanza tus objetivos de ahorro con nuestra plataforma intuitiva.
                    </p>
                    <div class="hero-buttons">
                        <a href="registro.php" class="btn btn-light-custom">
                            <i class="bi bi-rocket-takeoff me-2"></i>Comenzar Gratis
                        </a>
                        <a href="login.php" class="btn btn-outline-light-custom">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                        </a>
                    </div>
                    <div class="mt-4 text-white">
                        <small class="opacity-75">
                            <i class="bi bi-check-circle-fill me-1"></i> Sin tarjeta de crédito
                            <span class="mx-2">•</span>
                            <i class="bi bi-check-circle-fill me-1"></i> 100% Gratis
                            <span class="mx-2">•</span>
                            <i class="bi bi-check-circle-fill me-1"></i> Seguro
                        </small>
                    </div>
                </div>
                <div class="col-lg-6 hero-image">
                    <div class="dashboard-preview">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 600'%3E%3Crect fill='%23f8f9fa' width='800' height='600'/%3E%3Ctext x='400' y='250' font-family='Arial' font-size='24' fill='%23667eea' text-anchor='middle'%3EPanel Financiero%3C/text%3E%3Crect x='50' y='300' width='150' height='100' rx='10' fill='%2328a745' opacity='0.2'/%3E%3Ctext x='125' y='340' font-family='Arial' font-size='16' fill='%2328a745' text-anchor='middle'%3EIngresos%3C/text%3E%3Ctext x='125' y='370' font-family='Arial' font-size='28' fill='%2328a745' text-anchor='middle' font-weight='bold'%3E$5,000%3C/text%3E%3Crect x='225' y='300' width='150' height='100' rx='10' fill='%23dc3545' opacity='0.2'/%3E%3Ctext x='300' y='340' font-family='Arial' font-size='16' fill='%23dc3545' text-anchor='middle'%3EEgresos%3C/text%3E%3Ctext x='300' y='370' font-family='Arial' font-size='28' fill='%23dc3545' text-anchor='middle' font-weight='bold'%3E$3,500%3C/text%3E%3Crect x='400' y='300' width='150' height='100' rx='10' fill='%23667eea' opacity='0.2'/%3E%3Ctext x='475' y='340' font-family='Arial' font-size='16' fill='%23667eea' text-anchor='middle'%3EBalance%3C/text%3E%3Ctext x='475' y='370' font-family='Arial' font-size='28' fill='%23667eea' text-anchor='middle' font-weight='bold'%3E$1,500%3C/text%3E%3Cpath d='M 100 500 Q 200 450, 300 480 T 500 450 T 700 480' stroke='%23667eea' fill='none' stroke-width='3'/%3E%3C/svg%3E" 
                             alt="Dashboard Preview" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="caracteristicas">
        <div class="container">
            <h2 class="section-title">Todo lo que necesitas para tus finanzas</h2>
            <p class="section-subtitle">Herramientas poderosas diseñadas para tu éxito financiero</p>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card">
                        <div class="feature-icon bg-primary-gradient">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <h4>Control de Ingresos</h4>
                        <p>Registra y categoriza todos tus ingresos. Identifica tus fuentes de dinero y planifica mejor tu futuro.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card">
                        <div class="feature-icon bg-secondary-gradient">
                            <i class="bi bi-cart"></i>
                        </div>
                        <h4>Gestión de Gastos</h4>
                        <p>Controla cada peso gastado. Identifica gastos hormiga y reduce gastos innecesarios fácilmente.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card">
                        <div class="feature-icon bg-success-gradient">
                            <i class="bi bi-piggy-bank"></i>
                        </div>
                        <h4>Metas de Ahorro</h4>
                        <p>Define objetivos y haz seguimiento de tu progreso. Convierte tus sueños en realidad con disciplina.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card">
                        <div class="feature-icon bg-primary-gradient">
                            <i class="bi bi-calculator"></i>
                        </div>
                        <h4>Presupuesto Inteligente</h4>
                        <p>Establece límites de gastos y controla tu presupuesto mensual. Recibe alertas cuando te acerques al límite.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card">
                        <div class="feature-icon bg-primary-gradient">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h4>Reportes Detallados</h4>
                        <p>Visualiza tu situación financiera con gráficas y reportes. Toma decisiones informadas basadas en datos.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card">
                        <div class="feature-icon bg-secondary-gradient">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </div>
                        <h4>Exportación Fácil</h4>
                        <p>Exporta tus datos a PDF o Excel. Comparte con tu contador o guarda copias de seguridad.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card">
                        <div class="feature-icon bg-success-gradient">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4>100% Seguro</h4>
                        <p>Tus datos están protegidos con encriptación de nivel bancario. Tu privacidad es nuestra prioridad.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section" id="estadisticas">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Gratis</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">5min</div>
                        <div class="stat-label">Para Empezar</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Disponible</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">∞</div>
                        <div class="stat-label">Transacciones</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Video Tutorial Section -->
    <section class="video-section" id="tutorial">
        <div class="container">
            <h2 class="section-title">Aprende a usar la plataforma</h2>
            <p class="section-subtitle">Video tutorial completo para dominar todas las funcionalidades</p>
            
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="video-container">
                        <div class="ratio ratio-16x9">
                            <iframe 
                                src="https://www.youtube.com/embed/dQw4w9WgXcQ" 
                                title="Tutorial Finanzas Personales" 
                                allowfullscreen
                                class="video-frame">
                            </iframe>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <p class="text-muted">
                            <i class="bi bi-play-circle me-2"></i>
                            Aprende en solo 10 minutos cómo registrar transacciones, crear metas de ahorro y generar reportes
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Donation Section -->
    <section class="donation-section" id="donaciones">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center mb-5">
                    <h2 class="section-title">Apoya nuestro proyecto</h2>
                    <p class="section-subtitle">
                        Si te gusta nuestra aplicación y te ha sido útil, considera hacer una donación.
                        Tu apoyo nos ayuda a mantener y mejorar la plataforma.
                    </p>
                </div>
            </div>
            
            <div class="row g-4 justify-content-center">
                <!-- Nequi Card -->
                <div class="col-md-6 col-lg-5">
                    <div class="donation-card nequi-card">
                        <div class="donation-header">
                            <div class="donation-icon nequi-icon">
                                <i class="bi bi-phone"></i>
                            </div>
                            <h3 class="donation-title">NEQUI</h3>
                        </div>
                        <div class="donation-body">
                            <div class="qr-placeholder mb-3">
                                <!-- <i class="bi bi-qr-code" style="font-size: 100px; color: #9b26af;"></i> -->
                                <img src="QR2.jpg" alt="NEQUI" style="max-width: 100%; height: auto; opacity: 0.8; width: 15rem;  ">
                                <!-- <img src="QR2.jpg" alt="NEQUI" style="max-width: 100%; height: auto; "> -->
                                <p class="mt-2 text-muted small">Escanea el código QR</p>
                            </div>
                            <div class="donation-info">
                                <label class="small text-muted mb-1">Número de teléfono</label>
                                <div class="phone-number">
                                    <i class="bi bi-telephone-fill me-2"></i>
                                    <span class="fw-bold">+57 3132254093</span>
                                    <button class="btn-copy" onclick="copiarTexto('3001234567', 'nequi')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-success mt-2 d-none" id="copiado-nequi">
                                    <i class="bi bi-check-circle-fill"></i> ¡Copiado!
                                </small>
                            </div>
                        </div>
                        <div class="donation-footer">
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i>
                                Donación segura y directa
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Daviplata Card -->
                <div class="col-md-6 col-lg-5">
                    <div class="donation-card daviplata-card">
                        <div class="donation-header">
                            <div class="donation-icon daviplata-icon">
                                <i class="bi bi-phone"></i>
                            </div>
                            <h3 class="donation-title">DAVIPLATA</h3>
                        </div>
                        <div class="donation-body">
                            <div class="qr-placeholder mb-3">
                                <i class="bi bi-qr-code" style="font-size: 100px; color: #ed1c24;"></i>
                                <p class="mt-2 text-muted small">Escanea el código QR</p>
                            </div>
                            <div class="donation-info">
                                <label class="small text-muted mb-1">Número de teléfono</label>
                                <div class="phone-number">
                                    <i class="bi bi-telephone-fill me-2"></i>
                                    <span class="fw-bold">+57 3132254093</span>
                                    <button class="btn-copy" onclick="copiarTexto('3109876543', 'daviplata')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-success mt-2 d-none" id="copiado-daviplata">
                                    <i class="bi bi-check-circle-fill"></i> ¡Copiado!
                                </small>
                            </div>
                        </div>
                        <div class="donation-footer">
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i>
                                Donación segura y directa
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-12 text-center">
                    <div class="donation-thank-you">
                        <i class="bi bi-heart-fill text-danger me-2"></i>
                        <span class="fw-bold">¡Gracias por tu apoyo!</span>
                        <span class="text-muted ms-2">Cada donación nos ayuda a seguir mejorando</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-card">
                <h2 class="cta-title">¿Listo para tomar control de tus finanzas?</h2>
                <p class="cta-text">Únete hoy y comienza tu camino hacia la libertad financiera</p>
                <a href="registro.php" class="btn btn-light-custom btn-lg">
                    <i class="bi bi-person-plus me-2"></i>Crear Cuenta Gratis
                </a>
                <div class="mt-3">
                    <small class="opacity-75">¿Ya tienes cuenta? <a href="login.php" class="text-white fw-bold">Inicia sesión aquí</a></small>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5 class="mb-3">
                        <i class="bi bi-wallet2 me-2"></i>Finanzas Personales
                    </h5>
                    <p class="text-white-50">
                        Tu aliado para alcanzar la libertad financiera. Controla, ahorra y alcanza tus metas.
                    </p>
                </div>
                <div class="col-lg-4 mb-4 mb-lg-0 text-center">
                    <h6 class="mb-3">Enlaces</h6>
                    <div class="footer-links">
                        <a href="#caracteristicas">Características</a>
                        <a href="login.php">Iniciar Sesión</a>
                        <a href="registro.php">Registrarse</a>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <h6 class="mb-3">Síguenos</h6>
                    <div class="social-links">
                        <a href="#" class="text-white me-3"><i class="bi bi-facebook fs-4"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-twitter fs-4"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-instagram fs-4"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-linkedin fs-4"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center text-white-50">
                <small>&copy; José Federico Haad Atuesta - 2025 Finanzas Personales. Todos los derechos reservados.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scroll para links internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Cambiar navbar al hacer scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            } else {
                navbar.style.background = 'transparent';
                navbar.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>