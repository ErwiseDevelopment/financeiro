<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuarioid'])) {
    header("Location: login.php");
    exit;
}

// Configuração moderna para a data (PHP 8.1+)
// Evita o erro de 'Deprecated' do strftime
$formatter = new IntlDateFormatter(
    'pt_BR',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    'America/Sao_Paulo',          
    IntlDateFormatter::GREGORIAN,
    "dd 'de' MMMM"
);
$data_extenso = ucfirst($formatter->format(new DateTime()));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ED Pro - Finanças</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    
    <style>
        :root {
            --primary-color: #4361ee;
            --bg-body: #f8fafc;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-body);
            color: #1e293b;
        }

        /* Header Estilizado */
        .main-header {
            background: #fff;
            padding: 20px 0;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 30px;
        }

        .brand-logo {
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 1px;
            color: var(--primary-color);
            text-transform: uppercase;
            margin-bottom: 4px;
            display: block;
        }

        .welcome-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0;
        }

        .date-text {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }

        .btn-logout {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #fef2f2;
            color: #ef4444;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #fee2e2;
        }

        .btn-logout:hover {
            background: #fee2e2;
            color: #dc2626;
            transform: scale(1.05);
        }

        /* Ajuste para telas pequenas */
        @media (max-width: 576px) {
            .welcome-text { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="brand-logo">ED PRO Financeiro</span>
                <h1 class="welcome-text">
                    Olá, <?= !empty($_SESSION['usuarionome']) ? explode(' ', $_SESSION['usuarionome'])[0] : 'Usuário' ?>! 👋
                </h1>
                <span class="date-text"><?= $data_extenso ?></span>
            </div>
            
            <a href="logout.php" class="btn-logout" title="Sair do sistema">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </div>
</header>

<main class="container">