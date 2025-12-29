<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$cartao_selecionado = $_GET['cartoid'] ?? null;

// Lógica para navegação de meses
$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

// 1. Busca os cartões do usuário para o seletor
$stmt_cards = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ?");
$stmt_cards->execute([$uid]);
$meus_cartoes = $stmt_cards->fetchAll();

if (!$cartao_selecionado && !empty($meus_cartoes)) {
    $cartao_selecionado = $meus_cartoes[0]['cartoid'];
}

// 2. Busca itens e verifica status da fatura
$itens_fatura = [];
$total_fatura = 0;
$itens_pendentes = 0;

if ($cartao_selecionado) {
    $stmt_f = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
        FROM contas c 
        JOIN categorias cat ON c.categoriaid = cat.categoriaid 
        WHERE c.usuarioid = ? AND c.cartoid = ? AND c.contacompetencia = ?
        ORDER BY c.contavencimento ASC");
    $stmt_f->execute([$uid, $cartao_selecionado, $mes_filtro]);
    $itens_fatura = $stmt_f->fetchAll();

    foreach($itens_fatura as $i) { 
        $total_fatura += $i['contavalor']; 
        if($i['contasituacao'] == 'Pendente') $itens_pendentes++;
    }
}
?>

<style>
    .card-fatura { background: #fff; border-radius: 20px; border: none; overflow: hidden; }
    .card-fatura-header { background: #212529; color: #fff; padding: 25px; }
    .chip-cartao { 
        padding: 8px 18px; border-radius: 50px; border: 1px solid #dee2e6; 
        font-size: 0.85rem; text-decoration: none; color: #6c757d; white-space: nowrap; transition: 0.2s;
    }
    .chip-cartao.active { background: #212529; color: #fff; border-color: #212529; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .form-label-caps { font-size: 0.65rem; font-weight: 800; color: #a0aec0; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 5px; display: block; }
    
    /* Estilo para o navegador de meses */
    .month-nav { background: #fff; border-radius: 15px; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #eee; }
    .month-nav a { color: #212529; text-decoration: none; font-weight: bold; padding: 5px 10px; border-radius: 8px; transition: 0.2s; }
    .month-nav a:hover { background: #f8f9fa; }
    .btn-delete { color: #ff4d4d; opacity: 0.3; transition: 0.2s; cursor: pointer; border: none; background: none; padding: 5px; }
    .card:hover .btn-delete { opacity: 1; }
</style>

<div class="container py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
        <div>
            <h4 class="fw-bold m-0">Faturas</h4>
            <small class="text-muted">Gestão de cartões de crédito</small>
        </div>
        <div class="month-nav shadow-sm">
            <a href="?cartoid=<?= $cartao_selecionado ?>&mes=<?= $mes_anterior ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="mx-3 text-uppercase small fw-bold"><?= ucfirst((new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, "MMMM yyyy"))->format($data_atual)) ?></span>
            <a href="?cartoid=<?= $cartao_selecionado ?>&mes=<?= $mes_proximo ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="d-flex overflow-x-auto gap-2 mb-4 pb-2" style="scrollbar-width: none;">
        <?php foreach($meus_cartoes as $ct): ?>
            <a href="?cartoid=<?= $ct['cartoid'] ?>&mes=<?= $mes_filtro ?>" 
               class="chip-cartao <?= $cartao_selecionado == $ct['cartoid'] ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-back me-1"></i> <?= $ct['cartonome'] ?>
            </a>
        <?php endforeach; ?>
        <a href="cadastro_cartao.php" class="chip-cartao bg-light"><i class="bi bi-plus-lg text-primary"></i></a>
    </div>

    <?php if($cartao_selecionado): ?>
        <div class="card-fatura shadow-sm mb-4">
            <div class="card-fatura-header">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-uppercase small opacity-75 fw-bold">Total da Fatura</span>
                    <span class="badge bg-primary px-3"><?= date('M/Y', strtotime($mes_filtro)) ?></span>
                </div>
                <h2 class="fw-bold m-0">R$ <?= number_format($total_fatura, 2, ',', '.') ?></h2>
            </div>
            
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-bold">STATUS</span>
                    <?php if($total_fatura > 0): ?>
                        <?php if($itens_pendentes > 0): ?>
                            <span class="badge bg-warning-subtle text-warning px-3 rounded-pill">ABERTA</span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success px-3 rounded-pill">PAGA</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted small">-</span>
                    <?php endif; ?>
                </div>

                <?php 
                    $limite = 0;
                    foreach($meus_cartoes as $m) { if($m['cartoid'] == $cartao_selecionado) $limite = $m['cartolimite']; }
                    $perc_limite = ($limite > 0) ? ($total_fatura / $limite) * 100 : 0;
                ?>
                <div class="progress mt-3" style="height: 6px; border-radius: 10px;">
                    <div class="progress-bar bg-dark" style="width: <?= min(100, $perc_limite) ?>%"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted" style="font-size: 0.65rem;">Limite utilizado</small>
                    <small class="text-muted fw-bold" style="font-size: 0.65rem;">Total: R$ <?= number_format($limite, 2, ',', '.') ?></small>
                </div>

                <?php if($total_fatura > 0 && $itens_pendentes > 0): ?>
                    <button class="btn btn-primary w-100 py-3 rounded-4 fw-bold mt-4 shadow" data-bs-toggle="modal" data-bs-target="#modalPagamento">
                        <i class="bi bi-currency-dollar me-2"></i> EFETUAR PAGAMENTO
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <h6 class="fw-bold mb-3 px-1 mt-4">Lançamentos no Cartão</h6>
        <?php if(empty($itens_fatura)): ?>
            <div class="p-5 text-center text-muted bg-white rounded-4 border border-dashed small shadow-sm">
                <i class="bi bi-emoji-smile d-block fs-2 mb-2"></i>
                Nenhuma compra neste mês.
            </div>
        <?php else: foreach($itens_fatura as $it): ?>
            <div class="card border-0 shadow-sm rounded-4 p-3 mb-2 d-flex flex-row justify-content-between align-items-center bg-white">
                <div class="d-flex align-items-center">
                    <div class="bg-light p-2 rounded-3 me-3 text-dark">
                        <i class="bi <?= $it['contasituacao'] == 'Pago' ? 'bi-check-circle-fill text-success' : 'bi-bag-check text-muted' ?>"></i>
                    </div>
                    <div>
                        <span class="fw-bold d-block small <?= $it['contasituacao'] == 'Pago' ? 'text-decoration-line-through opacity-50' : '' ?>"><?= $it['contadescricao'] ?></span>
                        <small class="text-muted" style="font-size: 0.65rem;">
                            <?= date('d/m', strtotime($it['contavencimento'])) ?> • <?= $it['categoriadescricao'] ?>
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="text-end me-3">
                        <span class="fw-bold small d-block">R$ <?= number_format($it['contavalor'], 2, ',', '.') ?></span>
                        <?php if($it['contaparcela_total'] > 1): ?>
                            <small class="badge bg-light text-dark fw-normal" style="font-size: 0.55rem;">P: <?= $it['contaparcela_num'] ?>/<?= $it['contaparcela_total'] ?></small>
                        <?php endif; ?>
                    </div>
<button onclick="confirmarExclusao(<?= $it['contasid'] ?>, <?= $it['contaparcela_total'] > 1 ? 'true' : 'false' ?>)" class="btn-delete">
    <i class="bi bi-trash3"></i>
</button>
                </div>
            </div>
        <?php endforeach; endif; ?>
        
    <?php endif; ?>
</div>

<script>
function confirmarExclusao(id, eParcelado) {
    let msg = "Tem certeza que deseja excluir este lançamento?";
    if (eParcelado) {
        msg = "Este item faz parte de um parcelamento. Ao excluir, TODAS as parcelas desta compra serão removidas. Confirmar?";
    }
    
    if (confirm(msg)) {
        window.location.href = "acoes_conta.php?id=" + id + "&acao=excluir";
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>