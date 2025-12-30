<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$msg = '';
$msg_type = '';

// --- LÓGICA DE PROCESSAMENTO ---

// 1. ADICIONAR NOVA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'adicionar') {
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo']; // Agora recebe 'Despesa', 'Receita' ou 'Ambos'
    $meta = str_replace(',', '.', $_POST['meta']);

    if(!empty($descricao)) {
        $stmt = $pdo->prepare("INSERT INTO categorias (usuarioid, categoriadescricao, categoriatipo, categoriameta) VALUES (?, ?, ?, ?)");
        if($stmt->execute([$uid, $descricao, $tipo, $meta])) {
            $msg = "Categoria adicionada com sucesso!";
            $msg_type = "success";
        }
    }
}

// 2. EDITAR EXISTENTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $cat_id = $_POST['categoria_id'];
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo'];
    $meta = str_replace(',', '.', $_POST['meta']);

    $stmt = $pdo->prepare("UPDATE categorias SET categoriadescricao = ?, categoriatipo = ?, categoriameta = ? WHERE categoriaid = ? AND usuarioid = ?");
    if($stmt->execute([$descricao, $tipo, $meta, $cat_id, $uid])) {
        $msg = "Categoria atualizada!";
        $msg_type = "primary";
    }
}

// 3. EXCLUIR (COM VERIFICAÇÃO DE USO)
if (isset($_GET['acao']) && $_GET['acao'] === 'excluir' && isset($_GET['id'])) {
    $id_excluir = $_GET['id'];

    // Verifica se a categoria está em uso
    $check = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE categoriaid = ? AND usuarioid = ?");
    $check->execute([$id_excluir, $uid]);
    $em_uso = $check->fetchColumn();

    if ($em_uso > 0) {
        $msg = "Não é possível excluir: Existem <b>$em_uso lançamentos</b> vinculados a esta categoria.";
        $msg_type = "danger";
    } else {
        $stmtDel = $pdo->prepare("DELETE FROM categorias WHERE categoriaid = ? AND usuarioid = ?");
        if($stmtDel->execute([$id_excluir, $uid])) {
            $msg = "Categoria removida com sucesso.";
            $msg_type = "success";
        }
    }
}

// BUSCAR LISTA ATUALIZADA
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE usuarioid = ? ORDER BY categoriatipo DESC, categoriadescricao ASC");
$stmt->execute([$uid]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-4">
    
    <?php if($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

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
            <span class="form-label-caps mb-3 d-block text-muted small fw-bold">ADICIONAR NOVA</span>
            <form method="POST" class="row g-2">
                <input type="hidden" name="acao" value="adicionar">
                
                <div class="col-12 col-md-5 mb-2">
                    <input type="text" name="descricao" class="form-control bg-light border-0 p-3" placeholder="Nome (Ex: Mercado, Luz...)" required>
                </div>
                
                <div class="col-6 col-md-3">
                    <select name="tipo" class="form-select bg-light border-0 p-3 fw-bold" required>
                        <option value="Despesa">🔴 Despesa</option>
                        <option value="Receita">🟢 Receita</option>
                        <option value="Ambos">⚪ Ambos</option>
                    </select>
                </div>
                
                <div class="col-6 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0">R$</span>
                        <input type="number" step="0.01" name="meta" class="form-control bg-light border-0 p-3" placeholder="Meta">
                    </div>
                </div>

                <div class="col-12 col-md-1">
                    <button type="submit" class="btn btn-primary w-100 h-100 rounded-3 fw-bold shadow-sm py-3 py-md-0">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <span class="form-label-caps mb-3 px-1 text-muted small fw-bold">MINHAS CATEGORIAS</span>
    
    <div class="bg-white shadow-sm rounded-4 overflow-hidden border">
        <div class="list-group list-group-flush">
            <?php if(empty($categorias)): ?>
                <div class="p-5 text-center">
                    <i class="bi bi-tags text-muted opacity-25" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2 small">Nenhuma categoria encontrada.</p>
                </div>
            <?php else: ?>
                <?php foreach($categorias as $c): 
                    $meta_display = $c['categoriameta'] > 0 ? "Meta: R$ " . number_format($c['categoriameta'], 2, ',', '.') : "Sem meta";
                    
                    // Ícone e Cor baseados no tipo (Despesa / Receita)
                    $icone = 'bi-circle'; $cor = 'text-secondary'; $bg = 'bg-light';
                    
                    if($c['categoriatipo'] == 'Receita') { 
                        $icone = 'bi-arrow-up-circle-fill'; $cor = 'text-success'; $bg = 'bg-success-subtle'; 
                    }
                    elseif($c['categoriatipo'] == 'Despesa') { 
                        $icone = 'bi-arrow-down-circle-fill'; $cor = 'text-danger'; $bg = 'bg-danger-subtle'; 
                    }
                    elseif($c['categoriatipo'] == 'Ambos') { 
                        $icone = 'bi-arrow-left-right'; $cor = 'text-primary'; $bg = 'bg-primary-subtle'; 
                    }
                ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3 border-light">
                        <div class="d-flex align-items-center">
                            <div class="p-3 rounded-4 me-3 <?= $bg ?> <?= $cor ?> d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                <i class="bi <?= $icone ?> fs-5"></i>
                            </div>
                            <div>
                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($c['categoriadescricao']) ?></span>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-light border text-muted fw-normal"><?= $c['categoriatipo'] ?></span>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= $meta_display ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm rounded-circle shadow-sm" style="width: 32px; height: 32px;" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3">
                                <li>
                                    <a class="dropdown-item small fw-bold" href="#" 
                                       onclick="abrirModalEditar(<?= $c['categoriaid'] ?>, '<?= htmlspecialchars($c['categoriadescricao']) ?>', '<?= $c['categoriatipo'] ?>', '<?= $c['categoriameta'] ?>')">
                                       <i class="bi bi-pencil me-2 text-primary"></i>Editar
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger small fw-bold" href="?acao=excluir&id=<?= $c['categoriaid'] ?>" onclick="return confirm('Tem certeza?');">
                                        <i class="bi bi-trash me-2"></i>Excluir
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Editar Categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="categoria_id" id="edit_id">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nome</label>
                        <input type="text" name="descricao" id="edit_descricao" class="form-control p-3 bg-light border-0 fw-bold" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tipo</label>
                        <select name="tipo" id="edit_tipo" class="form-select p-3 bg-light border-0 fw-bold">
                            <option value="Despesa">Despesa</option>
                            <option value="Receita">Receita</option>
                            <option value="Ambos">Ambos</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Meta de Gasto (R$)</label>
                        <input type="number" step="0.01" name="meta" id="edit_meta" class="form-control p-3 bg-light border-0 fw-bold">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-3 fw-bold px-4">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function abrirModalEditar(id, descricao, tipo, meta) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_descricao').value = descricao;
        document.getElementById('edit_tipo').value = tipo;
        document.getElementById('edit_meta').value = meta;
        
        var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
        modal.show();
    }
</script>

<?php require_once "../includes/footer.php"; ?>