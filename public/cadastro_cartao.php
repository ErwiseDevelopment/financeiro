<?php
require_once "../config/database.php";
require_once "../includes/header.php";
?>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="dashboard.php" class="btn btn-light rounded-circle me-3"><i class="bi bi-arrow-left"></i></a>
        <h4 class="fw-bold m-0">Novo Cartão</h4>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <form action="salvar_cartao.php" method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">NOME DO CARTÃO</label>
                <input type="text" name="cartonome" class="form-control form-control-lg rounded-3 border-light bg-light" placeholder="Ex: Nubank, Inter..." required>
            </div>

            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label small fw-bold text-muted">DIA FECHAMENTO</label>
                    <input type="number" name="cartofechamento" class="form-control form-control-lg rounded-3 border-light bg-light" placeholder="Ex: 5" min="1" max="31" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label small fw-bold text-muted">DIA VENCIMENTO</label>
                    <input type="number" name="cartovencimento" class="form-control form-control-lg rounded-3 border-light bg-light" placeholder="Ex: 15" min="1" max="31" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">LIMITE TOTAL (R$)</label>
                <input type="number" step="0.01" name="cartolimite" class="form-control form-control-lg rounded-3 border-light bg-light" placeholder="0,00">
            </div>

            <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 fw-bold shadow-sm">
                CADASTRAR CARTÃO
            </button>
        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>