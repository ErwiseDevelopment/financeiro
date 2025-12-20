<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];

// Lógica de inserção
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['descricao'])) {
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo']; // Entrada ou Saída

    $stmt = $pdo->prepare("INSERT INTO categorias (usuarioid, categoriadescricao, categoriatipo) VALUES (?, ?, ?)");
    $stmt->execute([$uid, $descricao, $tipo]);
    
    // Redireciona para evitar reenvio de formulário ao atualizar (F5)
    echo "<script>window.location.href='categorias.php';</script> text-uppercase";
    exit;
}

// Busca as categorias
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE usuarioid = ? ORDER BY categoriatipo DESC, categoriadescricao ASC");
$stmt->execute([$uid]);
$categorias = $stmt->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
        <div class="d-flex align-items-center">
            <a href="index.php" class="text-dark fs-4 me-3 text-decoration-none"><i class="bi bi-chevron-left"></i></a>
            <div>
                <h5 class="fw-bold mb-0">Categorias</h5>
                <p class="text-muted small mb-0">Gerencie seus fluxos</p>
            </div>
        </div>
        <span class="badge bg-white border text-dark rounded-pill px-3 py-2 shadow-sm">
            <?= count($categorias) ?> Total
        </span>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <span class="form-label-caps mb-3 d-block">ADICIONAR NOVA</span>
            <form method="POST" class="row g-2">
                <div class="col-12 mb-2">
                    <input type="text" name="descricao" class="form-control bg-light border-0 p-3" placeholder="Nome (Ex: Aluguel, Salário...)" required>
                </div>
                <div class="col-8">
                    <select name="tipo" class="form-select bg-light border-0 p-3 fw-bold" required>
                        <option value="Saída">🔴 Despesa (Saída)</option>
                        <option value="Entrada">🟢 Receita (Entrada)</option>
                    </select>
                </div>
                <div class="col-4">
                    <button type="submit" class="btn btn-primary w-100 h-100 rounded-3 fw-bold shadow-sm">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <span class="form-label-caps mb-3 px-1">MINHAS CATEGORIAS</span>
    
    <div class="bg-white shadow-sm rounded-4 overflow-hidden border">
        <div class="list-group list-group-flush">
            <?php if(empty($categorias)): ?>
                <div class="p-5 text-center">
                    <i class="bi bi-tags text-muted opacity-25" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2 small">Nenhuma categoria encontrada.</p>
                </div>
            <?php else: ?>
                <?php foreach($categorias as $c): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3 border-light">
                        <div class="d-flex align-items-center">
                            <div class="p-2 rounded-3 me-3 <?= $c['categoriatipo'] == 'Entrada' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                <i class="bi bi-tag-fill"></i>
                            </div>
                            <div>
                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($c['categoriadescricao']) ?></span>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= $c['categoriatipo'] ?></small>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3">
                                <li><a class="dropdown-item text-danger small fw-bold" href="#"><i class="bi bi-trash me-2"></i>Excluir</a></li>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>