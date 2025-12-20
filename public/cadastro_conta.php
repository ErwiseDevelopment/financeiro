<?php
require_once "../config/database.php";
require_once "../includes/header.php";

// A sessão já é verificada no header.php conforme sua estrutura
$uid = $_SESSION['usuarioid'];
$tipo_pre = $_GET['tipo'] ?? 'Saída';

// Busca as categorias
$stmt_cat = $pdo->prepare("SELECT * FROM categorias WHERE usuarioid = ? ORDER BY categoriadescricao ASC");
$stmt_cat->execute([$uid]);
$categorias = $stmt_cat->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
        <a href="index.php" class="text-dark fs-4 text-decoration-none"><i class="bi bi-chevron-left"></i></a>
        <h5 class="fw-bold mb-0">Novo Lançamento</h5>
        <div style="width: 24px;"></div>
    </div>

    <form action="salvar_conta.php" method="POST">
        <div class="mb-4">
            <div class="btn-group w-100 p-1 bg-white border rounded-4 shadow-sm" role="group">
                <input type="radio" class="btn-check" name="contatipo" id="entrada" value="Entrada" <?= $tipo_pre == 'Entrada' ? 'checked' : '' ?>>
                <label class="btn btn-type-select py-2 rounded-4 fw-bold" for="entrada">
                    <i class="bi bi-plus-circle-fill me-1"></i> Entrada
                </label>

                <input type="radio" class="btn-check" name="contatipo" id="saida" value="Saída" <?= $tipo_pre == 'Saída' ? 'checked' : '' ?>>
                <label class="btn btn-type-select py-2 rounded-4 fw-bold" for="saida">
                    <i class="bi bi-dash-circle-fill me-1"></i> Saída
                </label>
            </div>
        </div>

        <div class="text-center mb-5">
            <span class="form-label-caps">VALOR DO LANÇAMENTO</span>
            <div class="d-flex justify-content-center align-items-baseline">
                <span class="fs-4 fw-bold text-muted me-2">R$</span>
                <input type="number" name="contavalor" class="form-control-flush fs-1 fw-bold text-center w-75 bg-transparent border-0" step="0.01" placeholder="0,00" required autofocus>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <div class="mb-4">
                <span class="form-label-caps">DESCRIÇÃO</span>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
                    <input type="text" name="contadescricao" class="form-control bg-light border-0 p-3" placeholder="Ex: Gasolina, Internet..." required>
                </div>
            </div>

            <div class="mb-4">
                <span class="form-label-caps">CATEGORIA</span>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-grid"></i></span>
                    <select name="categoriaid" class="form-select bg-light border-0 p-3" required>
                        <option value="">Selecione...</option>
                        <?php foreach($categorias as $cat): ?>
                            <option value="<?= $cat['categoriaid'] ?>"><?= $cat['categoriadescricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-6">
                    <span class="form-label-caps">DATA</span>
                    <input type="date" name="contavencimento" class="form-control bg-light border-0 p-3" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-6">
                    <span class="form-label-caps">PARCELAS</span>
                    <div class="input-group">
                        <input type="number" name="contaparcela_total" class="form-control bg-light border-0 p-3 text-center" value="1" min="1">
                        <span class="input-group-text bg-light border-0 small">x</span>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 fw-bold shadow-lg border-0">
            Confirmar Lançamento
        </button>
    </form>
</div>

<?php require_once "../includes/footer.php"; ?>