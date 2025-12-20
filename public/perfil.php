<?php
require_once "../config/database.php";
require_once "../includes/header.php"; // Garante session_start e trava de login

$erro = "";
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirma_senha = $_POST['confirma_senha'];

    // 1. Busca a senha atual no banco
    $stmt = $pdo->prepare("SELECT usuariosenha FROM usuarios WHERE usuarioid = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha_atual, $user['usuariosenha'])) {
        // 2. Valida a nova senha
        if (strlen($nova_senha) < 8 || !preg_match("/[0-9]/", $nova_senha) || !preg_match("/[\W]/", $nova_senha)) {
            $erro = "A nova senha não segue as regras de segurança.";
        } elseif ($nova_senha !== $confirma_senha) {
            $erro = "As novas senhas não coincidem.";
        } else {
            // 3. Atualiza
            $novo_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE usuarios SET usuariosenha = ? WHERE usuarioid = ?");
            $update->execute([$novo_hash, $uid]);
            $sucesso = "Senha alterada com sucesso!";
        }
    } else {
        $erro = "A senha atual está incorreta.";
    }
}
?>

<div class="container py-5">
    <div class="card border-0 shadow-sm rounded-4 p-4 mx-auto" style="max-width: 450px;">
        <h4 class="fw-bold mb-4">Alterar Senha</h4>

        <?php if($erro): ?> <div class="alert alert-danger border-0 small rounded-3"><?= $erro ?></div> <?php endif; ?>
        <?php if($sucesso): ?> <div class="alert alert-success border-0 small rounded-3"><?= $sucesso ?></div> <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">Senha Atual</label>
                <input type="password" name="senha_atual" class="form-control" required>
            </div>
            <hr>
            <div class="mb-3">
                <label class="form-label small fw-bold">Nova Senha</label>
                <input type="password" name="nova_senha" class="form-control" required>
                <div class="form-text" style="font-size: 0.7rem;">Mínimo 8 caracteres, números e símbolos.</div>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">Confirmar Nova Senha</label>
                <input type="password" name="confirma_senha" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 fw-bold">Atualizar Senha</button>
        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>