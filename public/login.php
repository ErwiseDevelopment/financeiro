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
        $confirmar_senha = $_POST['confirmar_senha'];
        if (strlen($senha) < 8 || !preg_match("/[0-9]/", $senha) || !preg_match("/[\W]/", $senha)) {
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
                $sql = $pdo->prepare("INSERT INTO usuarios (usuarioemail, usuariosenha) VALUES (?, ?)");
                $sql->execute([$email, $hash]);
                $sucesso = "Conta criada! Agora você já pode entrar.";
            }
        }
    } else {
        $sql = $pdo->prepare("SELECT * FROM usuarios WHERE usuarioemail = ? AND usuarioativo = 1");
        $sql->execute([$email]);
        $user = $sql->fetch();
        if ($user && password_verify($senha, $user['usuariosenha'])) {
            $_SESSION['usuarioid'] = $user['usuarioid'];
            $_SESSION['usuarioemail'] = $user['usuarioemail'];
            header("Location: index.php");
            exit;
        } else { 
            $erro = "Credenciais inválidas ou conta inativa."; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ED Pro - Acesso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        :root { --primary-color: #0d6efd; }
        body { background: #fff; min-height: 100vh; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        /* Layout Split */
        .split-container { min-height: 100vh; display: flex; flex-wrap: wrap; }
        .left-side { flex: 1; min-width: 400px; display: flex; align-items: center; justify-content: center; padding: 40px; background: #f8fafc; }
        .right-side { flex: 1.2; background: linear-gradient(135deg, #0d6efd 0%, #003d99 100%); display: flex; align-items: center; justify-content: center; color: white; padding: 40px; position: relative; }

        @media (max-width: 992px) { .right-side { display: none; } }

        .auth-card { width: 100%; max-width: 400px; border: none; background: transparent; }
        .nav-pills .nav-link { border-radius: 12px; color: #64748b; font-weight: 600; padding: 10px; }
        .nav-pills .nav-link.active { background-color: var(--primary-color); box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2); }
        
        .form-control { border-radius: 12px; padding: 14px; border: 1.5px solid #e2e8f0; background: #fff; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: none; }
        .btn-toggle-pwd { border: 1.5px solid #e2e8f0; border-left: none; background: #fff; border-radius: 0 12px 12px 0; color: #94a3b8; }
        .input-pwd { border-right: none; }

        /* Carousel Styles */
        .benefit-icon { font-size: 4rem; margin-bottom: 20px; color: #fff; opacity: 0.9; }
        .carousel-item { text-align: center; padding: 20px; }
        .carousel-indicators [data-bs-target] { width: 10px; height: 10px; border-radius: 50%; margin: 0 5px; }
    </style>
</head>
<body>

<div class="split-container">
    <div class="left-side">
        <div class="auth-card">
            <div class="mb-5">
                <h2 class="fw-bold text-dark">ED <span class="text-primary">Pro</span></h2>
                <p class="text-muted">Gestão inteligente para suas finanças.</p>
            </div>

            <?php if($erro): ?> <div class="alert alert-danger border-0 small rounded-3 mb-3"><?= $erro ?></div> <?php endif; ?>
            <?php if($sucesso): ?> <div class="alert alert-success border-0 small rounded-3 mb-3"><?= $sucesso ?></div> <?php endif; ?>

            <ul class="nav nav-pills nav-fill mb-4 bg-white border p-1 rounded-4 shadow-sm" id="pills-tab">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#login">Entrar</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#cadastro">Criar Conta</button></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="login">
                    <form method="POST">
                        <input type="hidden" name="acao" value="login">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">E-mail</label>
                            <input type="email" name="email" class="form-control" placeholder="exemplo@email.com" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Senha</label>
                            <div class="input-group">
                                <input type="password" name="senha" class="form-control input-pwd pwd-input" placeholder="Sua senha" required>
                                <button class="btn btn-toggle-pwd" type="button" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 fw-bold shadow">Acessar Painel</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="cadastro">
                    <form method="POST">
                        <input type="hidden" name="acao" value="cadastro">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">E-mail</label>
                            <input type="email" name="email" class="form-control" placeholder="Seu melhor e-mail" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nova Senha</label>
                            <div class="input-group">
                                <input type="password" name="senha" class="form-control input-pwd pwd-input" required>
                                <button class="btn btn-toggle-pwd" type="button" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Confirmar Senha</label>
                            <div class="input-group">
                                <input type="password" name="confirmar_senha" class="form-control input-pwd pwd-input" required>
                                <button class="btn btn-toggle-pwd" type="button" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark w-100 py-3 rounded-4 fw-bold shadow">Criar Minha Conta</button>
                    </form>
                </div>
            </div>
            
            <footer class="mt-5">
                <p class="small text-muted mb-0">&copy; <?= date('Y') ?> <strong>ED Pro</strong> - Desenvolvido por erwise.com.br</p>
            </footer>
        </div>
    </div>
<div class="right-side">
    <div id="benefitCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#benefitCarousel" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#benefitCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#benefitCarousel" data-bs-slide-to="2"></button>
            <button type="button" data-bs-target="#benefitCarousel" data-bs-slide-to="3"></button>
        </div>
        <div class="carousel-inner" style="max-width: 500px;">
            <div class="carousel-item active">
                <i class="bi bi-wallet2 benefit-icon"></i>
                <h3 class="fw-bold">Tudo em um só lugar</h3>
                <p class="lead opacity-75">Chega de planilhas espalhadas. Centralize suas contas, ganhos e cartões em uma única plataforma inteligente.</p>
            </div>
            <div class="carousel-item">
                <i class="bi bi-star benefit-icon"></i>
                <h3 class="fw-bold">100% Gratuito</h3>
                <p class="lead opacity-75">Tenha acesso a recursos premium sem pagar nada. Nosso objetivo é ajudar você a prosperar financeiramente.</p>
            </div>
            <div class="carousel-item">
                <i class="bi bi-lightning-charge benefit-icon"></i>
                <h3 class="fw-bold">Rápido e Prático</h3>
                <p class="lead opacity-75">Cadastre seus gastos em segundos. Uma interface pensada para quem não quer perder tempo com burocracia.</p>
            </div>
            <div class="carousel-item">
                <i class="bi bi-emoji-smile benefit-icon"></i>
                <h3 class="fw-bold">Aproveite Agora</h3>
                <p class="lead opacity-75">Junte-se a usuários que já transformaram sua relação com o dinheiro. Comece hoje sua jornada de liberdade!</p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(btn) {
    const input = btn.parentElement.querySelector('.pwd-input');
    const icon = btn.querySelector('i');
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = "password";
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>