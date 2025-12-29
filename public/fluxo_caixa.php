<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$mensagem = "";

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_GET['acao']) && $_GET['acao'] === 'excluir' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = $pdo->prepare("DELETE FROM contas WHERE contasid = ? AND usuarioid = ?");
    if ($sql->execute([$id, $uid])) {
        $mensagem = "<div class='alert alert-danger border-0 shadow-sm py-2 rounded-3 mb-4 text-center'>Lançamento excluído!</div>";
    }
}

// --- LÓGICA DE ATUALIZAÇÃO (CORRIGIDA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id = $_POST['id'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $vencimento = $_POST['vencimento'];
    $cartoid = !empty($_POST['cartoid']) ? $_POST['cartoid'] : null;
    $contafixa = isset($_POST['contafixa']) ? 1 : 0;

    $data_obj = new DateTime($vencimento);
    if ($cartoid) {
        $stmt_c = $pdo->prepare("SELECT cartofechamento FROM cartoes WHERE cartoid = ?");
        $stmt_c->execute([$cartoid]);
        $fch = $stmt_c->fetchColumn();
        $dia_compra = (int)$data_obj->format('d');
        $margem = (int)$fch - 1;
        if ($dia_compra >= $margem) { $data_obj->modify('first day of next month'); }
    }
    $competencia = $data_obj->format('Y-m');

    $sql = $pdo->prepare("UPDATE contas SET contadescricao = ?, contavalor = ?, contavencimento = ?, contacompetencia = ?, cartoid = ?, contafixa = ? WHERE contasid = ? AND usuarioid = ?");
    if ($sql->execute([$descricao, $valor, $vencimento, $competencia, $cartoid, $contafixa, $id, $uid])) {
        $mensagem = "<div class='alert alert-success border-0 shadow-sm py-2 rounded-3 mb-4 text-center'>Atualizado com sucesso!</div>";
    }
}

// --- CONSULTAS ---
$stmt_cartoes = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
$stmt_cartoes->execute([$uid]);
$lista_cartoes = $stmt_cartoes->fetchAll();

$stmt_entradas = $pdo->prepare("SELECT c.* FROM contas c WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Entrada' ORDER BY c.contavencimento ASC");
$stmt_entradas->execute([$uid, $mes_filtro]);
$entradas = $stmt_entradas->fetchAll();

$stmt_saidas = $pdo->prepare("SELECT c.* FROM contas c WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída' AND c.cartoid IS NULL ORDER BY c.contavencimento ASC");
$stmt_saidas->execute([$uid, $mes_filtro]);
$saidas = $stmt_saidas->fetchAll();

$stmt_faturas = $pdo->prepare("SELECT car.cartonome, car.cartoid, car.cartovencimento, SUM(c.contavalor) as total_fatura FROM contas c JOIN cartoes car ON c.cartoid = car.cartoid WHERE c.usuarioid = ? AND c.contacompetencia = ? GROUP BY car.cartoid");
$stmt_faturas->execute([$uid, $mes_filtro]);
$faturas_mes = $stmt_faturas->fetchAll();

$total_entradas = array_sum(array_column($entradas, 'contavalor'));
$total_saidas_diretas = array_sum(array_column($saidas, 'contavalor'));
$total_faturas = array_sum(array_column($faturas_mes, 'total_fatura'));
$total_geral_saidas = $total_saidas_diretas + $total_faturas;
?>

<?php
// ... (Mantenha a lógica PHP inicial igual ao código anterior) ...
?>

<style>
    body { background-color: #f8fafc; color: #334155; font-family: 'Inter', -apple-system, sans-serif; }
    
    /* Navegação de Meses mais clean */
    .month-pill { white-space: nowrap; padding: 10px 20px; border-radius: 12px; background: #fff; color: #64748b; text-decoration: none; font-size: 0.85rem; border: 1px solid #e2e8f0; transition: 0.2s; }
    .month-pill.active { background: #334155; color: #fff; border-color: #334155; font-weight: 500; }
    
    /* Cards e Listas */
    .card-main { border: none; border-radius: 20px; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,0.04); overflow: hidden; }
    .list-item { border: none; border-bottom: 1px solid #f8fafc; padding: 1rem 1.25rem; }
    
    /* Tipografia Ajustada */
    .desc-conta { color: #334155; font-size: 0.95rem; font-weight: 400; margin-bottom: 2px; display: block; }
    .val-conta { font-weight: 600; font-size: 1rem; letter-spacing: -0.5px; }
    .section-header { font-size: 0.7rem; font-weight: 600; color: #94a3b8; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 12px; }
    
    /* Botões Sutis */
    .btn-action-round { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: none; background: #f1f5f9; color: #64748b; transition: 0.2s; font-size: 0.9rem; }
    .btn-action-round:hover { background: #e2e8f0; color: #1e293b; }
    
    .btn-status { font-size: 0.75rem; font-weight: 500; border-radius: 8px; padding: 5px 14px; border: none; transition: 0.2s; }
    
    /* Destaque Fatura */
    .fatura-highlight { background: #fafaff; border-left: 4px solid #3b82f6 !important; }
    .badge-info-custom { color: #94a3b8; font-size: 0.75rem; font-weight: 400; }
    .text-done { opacity: 0.4; filter: grayscale(1); }
</style>

<div class="container py-4">
    <div class="d-flex overflow-x-auto gap-2 mb-4 px-1" id="monthSlider" style="scrollbar-width: none;">
        <?php for($i = -1; $i <= 5; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $label = (new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMM yy'))->format(strtotime($m."-01"));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $mes_filtro == $m ? 'active' : '' ?>"><?= ucfirst($label) ?></a>
        <?php endfor; ?>
    </div>

    <?= $mensagem ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <span class="section-header">Entradas</span>
                <span class="fw-bold text-success" style="font-size: 1.1rem;">R$ <?= number_format($total_entradas, 2, ',', '.') ?></span>
            </div>
            <div class="card-main">
                <div class="list-group list-group-flush">
                    <?php foreach($entradas as $e): ?>
                        <div class="list-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="<?= $e['contasituacao'] == 'Pago' ? 'text-done' : '' ?>">
                                    <span class="desc-conta"><?= $e['contadescricao'] ?></span>
                                    <div class="badge-info-custom">
                                        <i class="bi bi-calendar3 me-1"></i><?= date('d/m', strtotime($e['contavencimento'])) ?>
                                        <?= $e['contafixa'] ? ' • Fixa' : '' ?>
                                    </div>
                                </div>
                                <span class="val-conta text-success">R$ <?= number_format($e['contavalor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <a href="acoes_conta.php?acao=<?= $e['contasituacao'] == 'Pendente' ? 'pagar' : 'estornar' ?>&id=<?= $e['contasid'] ?>" 
                                   class="btn-status <?= $e['contasituacao'] == 'Pendente' ? 'bg-success text-white' : 'bg-light text-secondary' ?>">
                                    <?= $e['contasituacao'] == 'Pendente' ? 'Receber' : 'Estornado' ?>
                                </a>
                                <div class="d-flex gap-2">
                                    <button onclick='abrirModalEdicao(<?= json_encode($e) ?>)' class="btn-action-round"><i class="bi bi-pencil"></i></button>
                                    <a href="?mes=<?= $mes_filtro ?>&acao=excluir&id=<?= $e['contasid'] ?>" class="btn-action-round text-danger"><i class="bi bi-trash"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <span class="section-header">Saídas</span>
                <span class="fw-bold text-danger" style="font-size: 1.1rem;">R$ <?= number_format($total_geral_saidas, 2, ',', '.') ?></span>
            </div>
            <div class="card-main">
                <div class="list-group list-group-flush">
                    <?php foreach($faturas_mes as $fat): ?>
                        <div class="list-item fatura-highlight">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="text-primary me-3"><i class="bi bi-credit-card-2-front fs-5"></i></div>
                                    <div>
                                        <span class="desc-conta" style="font-weight: 500;">Fatura <?= $fat['cartonome'] ?></span>
                                        <span class="badge-info-custom">Vence dia <?= $fat['cartovencimento'] ?></span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="val-conta text-danger d-block">R$ <?= number_format($fat['total_fatura'], 2, ',', '.') ?></span>
                                    <a href="faturas.php?cartoid=<?= $fat['cartoid'] ?>&mes=<?= $mes_filtro ?>" class="text-primary text-decoration-none" style="font-size: 0.75rem; font-weight: 500;">VER DETALHES</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach($saidas as $s): ?>
                        <div class="list-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="<?= $s['contasituacao'] == 'Pago' ? 'text-done' : '' ?>">
                                    <span class="desc-conta"><?= $s['contadescricao'] ?></span>
                                    <div class="badge-info-custom">
                                        <i class="bi bi-calendar3 me-1"></i><?= date('d/m', strtotime($s['contavencimento'])) ?>
                                        <?= $s['contafixa'] ? ' • Fixa' : '' ?>
                                    </div>
                                </div>
                                <span class="val-conta text-danger">R$ <?= number_format($s['contavalor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <a href="acoes_conta.php?acao=<?= $s['contasituacao'] == 'Pendente' ? 'pagar' : 'estornar' ?>&id=<?= $s['contasid'] ?>" 
                                   class="btn-status <?= $s['contasituacao'] == 'Pendente' ? 'bg-danger text-white' : 'bg-light text-secondary' ?>">
                                    <?= $s['contasituacao'] == 'Pendente' ? 'Pagar' : 'Pago' ?>
                                </a>
                                <div class="d-flex gap-2">
                                    <button onclick='abrirModalEdicao(<?= json_encode($s) ?>)' class="btn-action-round"><i class="bi bi-pencil"></i></button>
                                    <a href="?mes=<?= $mes_filtro ?>&acao=excluir&id=<?= $s['contasid'] ?>" class="btn-action-round text-danger"><i class="bi bi-trash"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarConta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <div class="modal-header border-0 px-4 pt-4 pb-0">
                <h5 class="fw-bold">Editar Lançamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">DESCRIÇÃO</label>
                        <input type="text" name="descricao" id="edit_descricao" class="form-control form-control-lg bg-light border-0" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">VALOR (R$)</label>
                            <input type="number" step="0.01" name="valor" id="edit_valor" class="form-control form-control-lg bg-light border-0 fw-bold" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">DATA</label>
                            <input type="date" name="vencimento" id="edit_vencimento" class="form-control form-control-lg bg-light border-0" required>
                        </div>
                    </div>
                    <div class="mb-4" id="edit_div_cartao">
                        <label class="form-label small fw-bold text-muted">PAGAMENTO VIA</label>
                        <select name="cartoid" id="edit_cartoid" class="form-select form-select-lg bg-light border-0">
                            <option value="">Saldo em Conta (Débito/Pix)</option>
                            <?php foreach($lista_cartoes as $c): ?>
                                <option value="<?= $c['cartoid'] ?>"><?= $c['cartonome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check form-switch p-3 bg-light rounded-4 d-flex justify-content-between align-items-center">
                        <label class="form-check-label small fw-bold mb-0" for="edit_contafixa">Lançamento Fixo Mensal</label>
                        <input class="form-check-input ms-0" type="checkbox" name="contafixa" id="edit_contafixa" value="1">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-dark w-100 py-3 rounded-4 fw-bold shadow">SALVAR ALTERAÇÕES</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalEdicao(conta) {
    document.getElementById('edit_id').value = conta.contasid;
    document.getElementById('edit_descricao').value = conta.contadescricao;
    document.getElementById('edit_valor').value = conta.contavalor;
    document.getElementById('edit_vencimento').value = conta.contavencimento;
    document.getElementById('edit_cartoid').value = conta.cartoid || "";
    document.getElementById('edit_contafixa').checked = (conta.contafixa == 1);
    document.getElementById('edit_div_cartao').style.display = (conta.contatipo === 'Entrada') ? 'none' : 'block';
    new bootstrap.Modal(document.getElementById('modalEditarConta')).show();
}
</script>

<?php require_once "../includes/footer.php"; ?>