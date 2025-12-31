<?php
require_once "../config/database.php";
session_start();

$erro = "";
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];
    $acao  = $_POST['acao'];

    if ($acao === 'cadastro') {
        $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
        $confirmar_senha = $_POST['confirmar_senha'];

        if (empty($nome)) {
            $erro = "Por favor, informe seu nome.";
        } elseif (strlen($senha) < 8 || !preg_match("/[0-9]/", $senha) || !preg_match("/[\W]/", $senha)) {
            $erro = "A senha deve ter no mínimo 8 caracteres, incluir um número e um símbolo.";
        } elseif ($senha !== $confirmar_senha) {
            $erro = "As senhas não coincidem.";
        } else {
            $check = $pdo->prepare("SELECT usuarioid FROM usuarios WHERE usuarioemail = ?");
            $check->execute([$email]);
            if ($check->rowCount() > 0) {
                $erro = "Este e-mail já está em uso.";
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql = $pdo->prepare("INSERT INTO usuarios (usuarionome, usuarioemail, usuariosenha) VALUES (?, ?, ?)");
                if($sql->execute([$nome, $email, $hash])) {
                    $sucesso = "Conta criada com sucesso! Faça login para continuar.";
                } else {
                    $erro = "Erro ao criar conta. Tente novamente.";
                }
            }
        }
    } else {
        $sql = $pdo->prepare("SELECT * FROM usuarios WHERE usuarioemail = ? AND usuarioativo = 1");
        $sql->execute([$email]);
        $user = $sql->fetch();
        if ($user && password_verify($senha, $user['usuariosenha'])) {
            $_SESSION['usuarioid'] = $user['usuarioid'];
            $_SESSION['usuarioemail'] = $user['usuarioemail'];
            $_SESSION['usuarionome'] = $user['usuarionome'];
            header("Location: index.php");
            exit;
        } else { 
            $erro = "E-mail ou senha incorretos."; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso | ED Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-hover: #3a56d4;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            overflow-x: hidden;
        }

        .auth-container { min-height: 100vh; display: flex; }

        /* LADO ESQUERDO (Form) */
        .auth-form-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            background: #fff;
            max-width: 600px;
            width: 100%;
            z-index: 10;
        }

        .auth-content-wrapper {
            max-width: 420px;
            margin: 0 auto;
            width: 100%;
            animation: fadeIn 0.6s ease-out;
        }

        /* LADO DIREITO (Banner) */
        .auth-banner-side {
            flex: 1.5;
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
        }

        .auth-banner-side::before {
            content: '';
            position: absolute;
            width: 150%;
            height: 150%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 60%);
            top: -25%;
            left: -25%;
        }

        /* CARDS FLUTUANTES (DECORATIVOS) */
        .floating-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            color: #1e293b;
            position: absolute;
            transition: transform 0.3s ease;
            width: 240px;
            z-index: 1; /* Atrás do texto central se sobrepor */
        }
        
        .card-top-right {
            top: 15%;
            right: 8%;
            transform: rotate(5deg);
            animation: float 6s ease-in-out infinite;
        }

        .card-bottom-left {
            bottom: 15%;
            left: 8%;
            transform: rotate(-5deg);
            animation: float 7s ease-in-out infinite 1s;
        }

        /* LISTA DE FUNCIONALIDADES CENTRAL */
        .features-list {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            text-align: left;
        }

        .feature-badge {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 12px 15px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }

        .feature-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        .feature-badge i {
            color: #4cc9f0;
            font-size: 1.1rem;
        }

        /* ELEMENTOS UI */
        .nav-pills { background-color: #f1f5f9; padding: 5px; border-radius: 12px; margin-bottom: 2rem; }
        .nav-pills .nav-link { color: var(--text-muted); font-weight: 600; border-radius: 10px; transition: all 0.3s ease; }
        .nav-pills .nav-link.active { background-color: #fff; color: var(--primary-color); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        .form-floating > .form-control { border: 1px solid #e2e8f0; border-radius: 12px; background-color: #fff; }
        .form-floating > .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15); }
        
        .btn-primary-custom { background-color: var(--primary-color); border: none; color: white; padding: 14px; border-radius: 12px; font-weight: 600; width: 100%; transition: 0.2s; }
        .btn-primary-custom:hover { background-color: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(67, 97, 238, 0.2); color: white; }

        .btn-eye { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); border: none; background: none; color: var(--text-muted); z-index: 5; cursor: pointer; }
        .brand-logo { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); letter-spacing: -1px; margin-bottom: 0.5rem; display: inline-block; }
        .brand-logo span { color: var(--primary-color); }

        @keyframes float {
            0% { transform: translateY(0px) rotate(5deg); }
            50% { transform: translateY(-15px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(5deg); }
        }

        @media (max-width: 992px) { .auth-banner-side { display: none; } .auth-form-side { max-width: 100%; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="auth-container">
    
    <div class="auth-form-side">
        <div class="auth-content-wrapper">
            <div class="text-center mb-5">
                <div class="brand-logo">ED <span>Pro</span></div>
                <p class="text-muted">Bem-vindo de volta! Acesse sua conta.</p>
            </div>

            <?php if($erro): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4"><i class="bi bi-exclamation-circle-fill me-2"></i> <?= $erro ?></div>
            <?php endif; ?>
            <?php if($sucesso): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4"><i class="bi bi-check-circle-fill me-2"></i> <?= $sucesso ?></div>
            <?php endif; ?>

            <ul class="nav nav-pills nav-fill" id="authTab" role="tablist">
                <li class="nav-item"><button class="nav-link <?= empty($sucesso) ? 'active' : '' ?>" id="login-tab" data-bs-toggle="pill" data-bs-target="#login-content">Entrar</button></li>
                <li class="nav-item"><button class="nav-link <?= !empty($sucesso) ? 'active' : '' ?>" id="register-tab" data-bs-toggle="pill" data-bs-target="#register-content">Cadastrar</button></li>
            </ul>

            <div class="tab-content mt-4">
                <div class="tab-pane fade <?= empty($sucesso) ? 'show active' : '' ?>" id="login-content">
                    <form method="POST">
                        <input type="hidden" name="acao" value="login">
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="loginEmail" name="email" placeholder="Email" required>
                            <label>E-mail</label>
                        </div>
                        <div class="form-floating mb-4 password-wrapper">
                            <input type="password" class="form-control pwd-input" id="loginSenha" name="senha" placeholder="Senha" required>
                            <label>Senha</label>
                            <button type="button" class="btn-eye" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe">
                                <label class="form-check-label small text-muted" for="rememberMe">Lembrar-me</label>
                            </div>
                           
                        </div>
                        <button type="submit" class="btn btn-primary-custom shadow-sm">Acessar Sistema <i class="bi bi-arrow-right ms-2"></i></button>
                    </form>
                </div>

                <div class="tab-pane fade <?= !empty($sucesso) ? 'show active' : '' ?>" id="register-content">
                    <form method="POST">
                        <input type="hidden" name="acao" value="cadastro">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="cadNome" name="nome" placeholder="Nome" required>
                            <label>Nome Completo</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="cadEmail" name="email" placeholder="Email" required>
                            <label>E-mail</label>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <div class="form-floating password-wrapper">
                                    <input type="password" class="form-control pwd-input" id="cadSenha" name="senha" placeholder="Senha" required>
                                    <label>Senha</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating password-wrapper">
                                    <input type="password" class="form-control pwd-input" id="cadConfSenha" name="confirmar_senha" placeholder="Confirmar" required>
                                    <label>Confirmar</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4"><small class="text-muted" style="font-size: 0.75rem;">* A senha deve ter min. 8 caracteres, 1 número e 1 símbolo.</small></div>
                        <button type="submit" class="btn btn-primary-custom shadow-sm bg-dark border-0">Criar Conta Grátis</button>
                    </form>
                </div>
            </div>
            <div class="text-center mt-5">
                <p class="small text-muted mb-0">&copy; <?= date('Y') ?> Desenvolvido por <a href="https://erwise.com.br" class="text-decoration-none fw-bold text-dark">erwise.com.br</a></p>
            </div>
        </div>
    </div>

    <div class="auth-banner-side">
        
        <div class="text-center px-5" style="max-width: 650px; z-index: 2; position: relative;">
            <div class="mb-3 bg-white bg-opacity-25 p-3 rounded-4 d-inline-block">
                <i class="bi bi-graph-up-arrow fs-1"></i>
            </div>
            <h2 class="fw-bold mb-3 display-6">Controle Financeiro Simples e Poderoso.</h2>
            <p class="lead text-white-50 mb-0">Assuma o controle e organize sua vida financeira.</p>

            <div class="features-list">
                <div class="feature-badge">
                    <i class="bi bi-arrow-left-right"></i> Fluxo de Caixa
                </div>
                <div class="feature-badge">
                    <i class="bi bi-credit-card-2-front"></i> Controle de Cartões
                </div>
                <div class="feature-badge">
                    <i class="bi bi-bullseye"></i> Metas Inteligentes
                </div>
                <div class="feature-badge">
                    <i class="bi bi-pie-chart"></i> Relatórios Visuais
                </div>
            </div>
        </div>

        <div class="floating-card card-top-right">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted fw-bold">Meta de Economia</small>
                <i class="bi bi-trophy-fill text-warning"></i>
            </div>
            <h4 class="fw-bold mb-2">R$ 5.000,00</h4>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: 85%"></div>
            </div>
            <small class="text-success fw-bold mt-2 d-block text-end">85% Atingido! 🚀</small>
        </div>

        <div class="floating-card card-bottom-left" style="animation-delay: 1.5s;">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3 text-primary">
                    <i class="bi bi-wallet2 fs-4"></i>
                </div>
                <div>
                    <small class="text-muted d-block fw-bold" style="font-size: 0.75rem;">SALDO ATUAL</small>
                    <h5 class="fw-bold m-0 text-dark">R$ 12.450,00</h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 text-success bg-success bg-opacity-10 p-2 rounded-3" style="font-size: 0.8rem;">
                <i class="bi bi-arrow-up-circle-fill"></i>
                <strong>+ R$ 2.300,00</strong> <span class="text-muted ms-auto">Hoje</span>
            </div>
        </div>

    </div>

</div>

<script>
    function togglePassword(btn) {
        const wrapper = btn.closest('.password-wrapper');
        const input = wrapper.querySelector('.pwd-input');
        const icon = btn.querySelector('i');
        if (input.type === "password") { input.type = "text"; icon.classList.replace('bi-eye', 'bi-eye-slash'); } 
        else { input.type = "password"; icon.classList.replace('bi-eye-slash', 'bi-eye'); }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>