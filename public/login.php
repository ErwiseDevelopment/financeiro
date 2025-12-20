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

        // 1. REGRAS DE SEGURANÇA
        if (strlen($senha) < 8 || !preg_match("/[0-9]/", $senha) || !preg_match("/[W]/", $senha)) {
            $erro = "A senha deve ter no mínimo 8 caracteres, incluir um número e um símbolo.";
        } elseif ($senha !== $confirmar_senha) {
            $erro = "As senhas não coincidem.";
        } else {
            // 2. VERIFICA SE E-MAIL JÁ EXISTE
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
        // LOGIN
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
    <title>FinancePro - Acesso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f7fe; min-height: 100vh; display: flex; align-items: center; font-family: 'Inter', sans-serif; }
        .auth-card { width: 100%; max-width: 420px; margin: auto; border: none; border-radius: 28px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .nav-pills .nav-link { border-radius: 14px; color: #6c757d; font-weight: 600; transition: 0.3s; }
        .nav-pills .nav-link.active { background-color: #0d6efd; color: white; }
        .input-group-text { border-right: none; background: #fff; border-radius: 12px 0 0 12px; border-color: #eee; }
        .form-control { border-radius: 12px; padding: 12px; border: 1px solid #eee; }
        .form-control:focus { border-color: #0d6efd; box-shadow: none; }
        .btn-toggle-pwd { border-left: none; background: #fff; border-radius: 0 12px 12px 0; border-color: #eee; color: #adb5bd; }
    </style>
</head>
<body>
<div class="container">
    <div class="card auth-card p-4 bg-white">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark mb-1">Finance<span class="text-primary">Pro</span></h3>
            <p class="text-muted small">Sua gestão financeira simplificada</p>
        </div>

        <?php if($erro): ?> <div class="alert alert-danger border-0 small py-2 rounded-3 text-center"><?= $erro ?></div> <?php endif; ?>
        <?php if($sucesso): ?> <div class="alert alert-success border-0 small py-2 rounded-3 text-center"><?= $sucesso ?></div> <?php endif; ?>

        <ul class="nav nav-pills nav-fill mb-4 bg-light p-1 rounded-4" id="pills-tab">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#login">Entrar</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#cadastro">Cadastro</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="login">
                <form method="POST">
                    <input type="hidden" name="acao" value="login">
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                    </div>
                    <div class="input-group mb-4">
                        <input type="password" name="senha" class="form-control pwd-input" placeholder="Senha" required>
                        <button class="btn btn-toggle-pwd" type="button" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 fw-bold shadow-sm">Entrar no Sistema</button>
                </form>
            </div>

            <div class="tab-pane fade" id="cadastro">
                <form method="POST">
                    <input type="hidden" name="acao" value="cadastro">
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Seu e-mail principal" required>
                    </div>
                    <div class="input-group mb-2">
                        <input type="password" name="senha" class="form-control pwd-input" placeholder="Nova Senha" required>
                        <button class="btn btn-toggle-pwd" type="button" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="confirmar_senha" class="form-control pwd-input" placeholder="Repetir Senha" required>
                        <button class="btn btn-toggle-pwd" type="button" onclick="togglePassword(this)"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="mb-4">
                        <small class="text-muted" style="font-size: 0.7rem;">
                            * Mínimo 8 caracteres, números e símbolos (@#$).
                        </small>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-3 rounded-4 fw-bold shadow-sm">Criar Minha Conta</button>
                </form>
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