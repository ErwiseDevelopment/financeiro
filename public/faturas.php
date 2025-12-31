<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m'); 
$cartao_selecionado = $_GET['cartoid'] ?? null;

// --- LÓGICA DE SALVAR EDIÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar_lancamento') {
    try {
        $id_edit = $_POST['id_conta'];
        $desc_edit = $_POST['descricao'];
        $val_edit = str_replace(['R$', ' ', '.'], '', $_POST['valor']);
        $val_edit = str_replace(',', '.', $val_edit);
        $data_edit = $_POST['data'];
        $cat_edit = $_POST['categoria'];
        $comp_edit = date('Y-m', strtotime($data_edit));

        $stmtUpdate = $pdo->prepare("UPDATE contas SET contadescricao=?, contavalor=?, contavencimento=?, contacompetencia=?, categoriaid=? WHERE contasid=? AND usuarioid=?");
        $stmtUpdate->execute([$desc_edit, $val_edit, $data_edit, $comp_edit, $cat_edit, $id_edit, $uid]);

        echo "<script>window.location.href='faturas.php?cartoid=$cartao_selecionado&mes=$mes_filtro';</script>";
        exit;
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
    }
}

$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

$stmt_cards = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
$stmt_cards->execute([$uid]);
$meus_cartoes = $stmt_cards->fetchAll();

if (!$cartao_selecionado && !empty($meus_cartoes)) $cartao_selecionado = $meus_cartoes[0]['cartoid'];

$stmt_cats = $pdo->prepare("SELECT categoriaid, categoriadescricao FROM categorias WHERE usuarioid = ? ORDER BY categoriadescricao");
$stmt_cats->execute([$uid]);
$lista_categorias = $stmt_cats->fetchAll();

$itens_fatura = [];
$total_fatura_mes = 0;
$limite_comprometido_total = 0;
$itens_pendentes_mes = 0;
$limite_cartao = 0;
$dados_cartao = null;

if ($cartao_selecionado) {
    foreach($meus_cartoes as $m) { 
        if($m['cartoid'] == $cartao_selecionado) { $dados_cartao = $m; $limite_cartao = $m['cartolimite']; }
    }

    $sql_fatura = "SELECT c.*, cat.categoriadescricao 
                   FROM contas c 
                   LEFT JOIN categorias cat ON c.categoriaid = cat.categoriaid 
                   WHERE c.usuarioid = ? AND c.cartoid = ? 
                   AND COALESCE(c.competenciafatura, c.contacompetencia) = ? 
                   ORDER BY c.contavencimento ASC";
    
    $stmt_f = $pdo->prepare($sql_fatura);
    $stmt_f->execute([$uid, $cartao_selecionado, $mes_filtro]);
    $itens_fatura = $stmt_f->fetchAll();

    foreach($itens_fatura as $i) { 
        $total_fatura_mes += $i['contavalor']; 
        if($i['contasituacao'] == 'Pendente') $itens_pendentes_mes++;
    }

    $stmt_limite = $pdo->prepare("SELECT SUM(contavalor) as total FROM contas WHERE usuarioid = ? AND cartoid = ? AND contasituacao = 'Pendente' AND contatipo = 'Saída'");
    $stmt_limite->execute([$uid, $cartao_selecionado]);
    $limite_comprometido_total = $stmt_limite->fetch()['total'] ?? 0;
}

$limite_disponivel = $limite_cartao - $limite_comprometido_total;
$perc_uso = ($limite_cartao > 0) ? ($limite_comprometido_total / $limite_cartao) * 100 : 0;
?>

<style>
    body { background-color: #f8fafc; color: #334155; }
    .card-fatura { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .card-fatura-header { background: #1e293b; color: #fff; padding: 25px; }
    .chip-cartao { padding: 8px 18px; border-radius: 50px; border: 1px solid #dee2e6; font-size: 0.85rem; text-decoration: none; color: #6c757d; background: #fff; white-space: nowrap; }
    .chip-cartao.active { background: #1e293b; color: #fff; border-color: #1e293b; }
    .month-nav { background: #fff; border-radius: 15px; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee; }
    .month-nav a { color: #212529; text-decoration: none; font-weight: bold; }
    
    /* Botões Topo */
    .btn-view-report { background: #fff; border: 1px solid #edf2f7; border-radius: 18px; padding: 15px; text-decoration: none; color: #2d3748; display: flex; align-items: center; justify-content: center; height: 100%; transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
    .btn-view-report:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

    .btn-action { border: none; background: none; transition: 0.2s; }
    .btn-action:hover { transform: scale(1.1); }
    .btn-pay-invoice { background: #fff; color: #1e293b; font-weight: 800; border: none; padding: 10px 20px; border-radius: 12px; width: 100%; margin-top: 15px; transition: 0.2s; }
    .btn-pay-invoice:hover { background: #f1f5f9; }
</style>

<div class="container py-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold m-0">Faturas</h4>
            <small class="text-muted">Cartão: <?= $dados_cartao['cartonome'] ?? 'Selecione' ?></small>
        </div>
        <div class="month-nav shadow-sm">
            <a href="?cartoid=<?= $cartao_selecionado ?>&mes=<?= $mes_anterior ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="mx-3 text-uppercase small fw-bold"><?= (new IntlDateFormatter('pt_BR', 0, 0, null, null, "MMMM yyyy"))->format($data_atual) ?></span>
            <a href="?cartoid=<?= $cartao_selecionado ?>&mes=<?= $mes_proximo ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6">
            <a href="resumo_cartoes.php" class="btn-view-report">
                <i class="bi bi-pie-chart-fill me-2 text-primary"></i>
                <span class="small fw-bold">RESUMO GERAL</span>
            </a>
        </div>
        <div class="col-6">
            <a href="faturas_geral.php" class="btn-view-report">
                <i class="bi bi-list-check me-2 text-success"></i>
                <span class="small fw-bold">VER TODAS</span>
            </a>
        </div>
    </div>

    <div class="d-flex overflow-x-auto gap-2 mb-4 pb-2" style="scrollbar-width: none;">
        <?php foreach($meus_cartoes as $ct): ?>
            <a href="?cartoid=<?= $ct['cartoid'] ?>&mes=<?= $mes_filtro ?>" class="chip-cartao <?= $cartao_selecionado == $ct['cartoid'] ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-back me-1"></i> <?= $ct['cartonome'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if($cartao_selecionado): ?>
        
        <div class="card-fatura mb-4">
            <div class="card-fatura-header">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-uppercase small opacity-75 fw-bold">Vencimento <?= date('d/m', strtotime($dados_cartao['cartovencimento'].'-'.$mes_filtro)) ?></span>
                    <span class="badge <?= $itens_pendentes_mes > 0 ? 'bg-warning text-dark' : 'bg-success' ?> rounded-pill">
                        <?= $itens_pendentes_mes > 0 ? 'ABERTA' : 'PAGA' ?>
                    </span>
                </div>
                <h2 class="fw-bold m-0">R$ <?= number_format($total_fatura_mes, 2, ',', '.') ?></h2>
                
                <?php if($itens_pendentes_mes > 0): ?>
                    <button type="button" class="btn-pay-invoice shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPagarFatura">
                        <i class="bi bi-check2-circle me-1"></i> MARCAR COMO PAGO
                    </button>
                <?php else: ?>
                    <div class="mt-3 text-center p-2 bg-success bg-opacity-25 rounded-3 border border-success border-opacity-25 fw-bold text-white small">
                        <i class="bi bi-hand-thumbs-up-fill me-1"></i> FATURA QUITADA
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="p-4 bg-white">
                <div class="d-flex justify-content-between mb-1 small fw-bold text-muted">
                    <span>USADO: R$ <?= number_format($limite_comprometido_total, 2, ',', '.') ?></span>
                    <span class="text-success">LIVRE: R$ <?= number_format($limite_disponivel, 2, ',', '.') ?></span>
                </div>
                <div class="progress" style="height: 6px;"><div class="progress-bar <?= $perc_uso > 85 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= min(100, $perc_uso) ?>%"></div></div>
            </div>
        </div>

        <h6 class="fw-bold mb-3 px-1">Lançamentos</h6>
        <?php if(empty($itens_fatura)): ?>
            <div class="p-5 text-center text-muted bg-white rounded-4 border">Nenhum lançamento.</div>
        <?php else: foreach($itens_fatura as $it): ?>
            <div class="card border-0 shadow-sm rounded-4 p-3 mb-2 d-flex flex-row justify-content-between align-items-center bg-white">
                <div class="d-flex align-items-center" style="overflow: hidden;">
                    <div class="bg-light p-2 rounded-3 me-3 flex-shrink-0">
                        <i class="bi <?= $it['contasituacao'] == 'Pago' ? 'bi-check-circle-fill text-success' : 'bi-cart' ?>"></i>
                    </div>
                    <div style="min-width: 0;">
                        <span class="fw-bold d-block small text-truncate <?= $it['contasituacao'] == 'Pago' ? 'text-decoration-line-through opacity-50' : '' ?>"><?= $it['contadescricao'] ?></span>
                        <small class="text-muted" style="font-size: 0.65rem;">
                            <?= date('d/m', strtotime($it['contavencimento'])) ?> • <?= $it['categoriadescricao'] ?>
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center text-end ms-2">
                    <div class="me-3">
                        <span class="fw-bold small d-block">R$ <?= number_format($it['contavalor'], 2, ',', '.') ?></span>
                        <?php if($it['contaparcela_total'] > 1): ?>
                            <small class="badge bg-light text-dark" style="font-size: 0.55rem;"><?= $it['contaparcela_num'] ?>/<?= $it['contaparcela_total'] ?></small>
                        <?php endif; ?>
                    </div>
                    <button class="btn-action text-primary me-2" onclick='abrirEditar(<?= json_encode($it) ?>)'><i class="bi bi-pencil-square"></i></button>
                    <button onclick="confirmarExclusao(<?= $it['contasid'] ?>)" class="btn-action text-danger"><i class="bi bi-trash3"></i></button>
                </div>
            </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalPagarFatura" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="processar_pagamento_fatura.php" method="POST" class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Baixar Fatura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Todos os lançamentos desta fatura serão marcados como <strong>PAGOS</strong>.</p>
                <input type="hidden" name="cartao_id" value="<?= $cartao_selecionado ?>">
                <input type="hidden" name="competencia" value="<?= $mes_filtro ?>">
                
                <div class="mb-3">
                    <label class="fw-bold small">Valor Total</label>
                    <input type="text" class="form-control fw-bold bg-light" value="R$ <?= number_format($total_fatura_mes, 2, ',', '.') ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="fw-bold small">Data do Pagamento</label>
                    <input type="date" name="data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="submit" class="btn btn-dark fw-bold w-100 rounded-3">Confirmar Pagamento</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Editar Lançamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="acao" value="editar_lancamento">
                <input type="hidden" name="id_conta" id="edit_id">
                <div class="mb-3">
                    <label class="small fw-bold text-muted">Descrição</label>
                    <input type="text" name="descricao" id="edit_desc" class="form-control" required>
                </div>
                <div class="row g-2">
                    <div class="col-6 mb-3">
                        <label class="small fw-bold text-muted">Valor (R$)</label>
                        <input type="text" name="valor" id="edit_valor" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="small fw-bold text-muted">Data</label>
                        <input type="date" name="data" id="edit_data" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold text-muted">Categoria</label>
                    <select name="categoria" id="edit_cat" class="form-select">
                        <?php foreach($lista_categorias as $c): ?>
                            <option value="<?= $c['categoriaid'] ?>"><?= $c['categoriadescricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirEditar(item) {
    document.getElementById('edit_id').value = item.contasid;
    document.getElementById('edit_desc').value = item.contadescricao;
    let val = parseFloat(item.contavalor).toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('edit_valor').value = val;
    document.getElementById('edit_data').value = item.contavencimento;
    document.getElementById('edit_cat').value = item.categoriaid;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function confirmarExclusao(id) {
    if (confirm("Excluir este lançamento?")) {
        window.location.href = "acoes_conta.php?id=" + id + "&acao=excluir&origem=faturas";
    }
}

document.getElementById('edit_valor').addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g, "");
    v = (v/100).toFixed(2) + "";
    v = v.replace(".", ",");
    v = v.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
    v = v.replace(/(\d)(\d{3}),/g, "$1.$2,");
    e.target.value = v;
});
</script>

<?php require_once "../includes/footer.php"; ?>