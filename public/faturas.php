<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$cartao_selecionado = $_GET['cartoid'] ?? null;

$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

// 1. Busca os cartões do usuário
$stmt_cards = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ?");
$stmt_cards->execute([$uid]);
$meus_cartoes = $stmt_cards->fetchAll();

if (!$cartao_selecionado && !empty($meus_cartoes)) {
    $cartao_selecionado = $meus_cartoes[0]['cartoid'];
}

// Inicialização de variáveis
$itens_fatura = [];
$total_fatura_mes = 0;
$limite_comprometido_total = 0;
$itens_pendentes_mes = 0;
$limite_cartao = 0;

if ($cartao_selecionado) {
    // 1. Primeiro, pegamos o dia de fechamento desse cartão específico
    $dia_fechamento = 1; // Valor padrão
    foreach($meus_cartoes as $m) { 
        if($m['cartoid'] == $cartao_selecionado) {
            $limite_cartao = $m['cartolimite']; 
            $dia_fechamento = (int)$m['cartofechamento']; // Importante para a regra
        }
    }

    // 2. Preparamos as datas para o filtro
    // $mes_filtro vem da URL (ex: 2026-01)
    // No faturas.php, substitua a query principal por esta:
$data_alvo = new DateTime($mes_filtro . "-01");
$mes_atual_txt = $data_alvo->format('Y-m');
$mes_anterior_txt = (clone $data_alvo)->modify('-1 month')->format('Y-m');

$sql_fatura = "SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    JOIN cartoes car ON c.cartoid = car.cartoid
    WHERE c.usuarioid = ? AND c.cartoid = ? 
    AND (
        -- Regra A: Gastos do mês anterior feitos APÓS o fechamento
        (c.contacompetencia = ? AND DAY(c.contavencimento) >= car.cartofechamento)
        OR 
        -- Regra B: Gastos do mês atual feitos ANTES do fechamento
        (c.contacompetencia = ? AND DAY(c.contavencimento) < car.cartofechamento)
    )
    ORDER BY c.contavencimento ASC";

$stmt_f = $pdo->prepare($sql_fatura);
$stmt_f->execute([$uid, $cartao_selecionado, $mes_anterior_txt, $mes_atual_txt]);
    
    // NOTA: Para o seu caso específico (Compra dia 25, Fechamento dia 30, Ver em Janeiro):
    // A query abaixo é a mais simplificada para o seu modelo de competência atual:
    
    $stmt_f = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
        FROM contas c 
        JOIN categorias cat ON c.categoriaid = cat.categoriaid 
        WHERE c.usuarioid = ? AND c.cartoid = ? 
        AND (
            -- Compras feitas no mês anterior que pertencem a esta fatura
            (c.contacompetencia = ? AND DAY(c.contavencimento) <= ?)
        )
        ORDER BY c.contavencimento ASC");
    $stmt_f->execute([$uid, $cartao_selecionado, $mes_anterior, $dia_fechamento]);
    
    $itens_fatura = $stmt_f->fetchAll();

    foreach($itens_fatura as $i) { 
        $total_fatura_mes += $i['contavalor']; 
        if($i['contasituacao'] == 'Pendente') $itens_pendentes_mes++;
    }


    // 4. LÓGICA DO LIMITE REAL: Busca a soma de TUDO que está pendente neste cartão (futuro e atual)
    // Isso garante que compras parceladas abatam do limite total até serem pagas.
    $stmt_limite = $pdo->prepare("SELECT SUM(contavalor) as total_pendente FROM contas 
                                 WHERE usuarioid = ? AND cartoid = ? AND contasituacao = 'Pendente'");
    $stmt_limite->execute([$uid, $cartao_selecionado]);
    $limite_comprometido_total = $stmt_limite->fetch()['total_pendente'] ?? 0;
}

$limite_disponivel = $limite_cartao - $limite_comprometido_total;
$perc_uso = ($limite_cartao > 0) ? ($limite_comprometido_total / $limite_cartao) * 100 : 0;
?>

<style>
    .card-fatura { background: #fff; border-radius: 20px; border: none; overflow: hidden; }
    .card-fatura-header { background: #212529; color: #fff; padding: 25px; }
    .chip-cartao { 
        padding: 8px 18px; border-radius: 50px; border: 1px solid #dee2e6; 
        font-size: 0.85rem; text-decoration: none; color: #6c757d; white-space: nowrap; transition: 0.2s;
    }
    .chip-cartao.active { background: #212529; color: #fff; border-color: #212529; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .month-nav { background: #fff; border-radius: 15px; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #eee; }
    .month-nav a { color: #212529; text-decoration: none; font-weight: bold; padding: 5px 10px; border-radius: 8px; }
    .btn-delete { color: #ff4d4d; opacity: 0.3; transition: 0.2s; cursor: pointer; border: none; background: none; padding: 5px; }
    .card:hover .btn-delete { opacity: 1; }
    .btn-view-report { background: #fff; border: 1px solid #edf2f7; border-radius: 18px; padding: 15px; text-decoration: none; color: #2d3748; display: flex; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); height: 100%; }
    .btn-view-report i { font-size: 1.3rem; margin-right: 12px; color: #212529; }
    .btn-view-report span { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
</style>

<div class="container py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
        <div>
            <h4 class="fw-bold m-0">Faturas</h4>
            <small class="text-muted">Gestão de cartões de crédito</small>
        </div>
        <div class="month-nav shadow-sm">
            <a href="?cartoid=<?= $cartao_selecionado ?>&mes=<?= $mes_anterior ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="mx-3 text-uppercase small fw-bold"><?= (new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, "MMMM yyyy"))->format($data_atual) ?></span>
            <a href="?cartoid=<?= $cartao_selecionado ?>&mes=<?= $mes_proximo ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="row g-2 mb-4">
        <div class="col-6"><a href="resumo_cartoes.php" class="btn-view-report"><i class="bi bi-pie-chart-fill"></i><span>Resumo por<br>Cartão</span></a></div>
        <div class="col-6"><a href="faturas_geral.php" class="btn-view-report"><i class="bi bi-list-check"></i><span>Todas as<br>Faturas</span></a></div>
    </div>

    <div class="d-flex overflow-x-auto gap-2 mb-4 pb-2" style="scrollbar-width: none;">
        <?php foreach($meus_cartoes as $ct): ?>
            <a href="?cartoid=<?= $ct['cartoid'] ?>&mes=<?= $mes_filtro ?>" class="chip-cartao <?= $cartao_selecionado == $ct['cartoid'] ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-back me-1"></i> <?= $ct['cartonome'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if($cartao_selecionado): ?>
        <div class="card-fatura shadow-sm mb-4">
            <div class="card-fatura-header">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-uppercase small opacity-75 fw-bold">Nesta Fatura (<?= date('M/y', strtotime($mes_filtro)) ?>)</span>
                    <?php if($itens_pendentes_mes > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.6rem;">ABERTA</span>
                    <?php else: ?>
                        <span class="badge bg-success rounded-pill" style="font-size: 0.6rem;">PAGA</span>
                    <?php endif; ?>
                </div>
                <h2 class="fw-bold m-0">R$ <?= number_format($total_fatura_mes, 2, ',', '.') ?></h2>
            </div>
            
            <div class="p-4 bg-white">
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block" style="font-size: 0.65rem; font-weight: 700;">LIMITE UTILIZADO</small>
                        <span class="fw-bold text-danger">R$ <?= number_format($limite_comprometido_total, 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted d-block" style="font-size: 0.65rem; font-weight: 700;">DISPONÍVEL</small>
                        <span class="fw-bold text-success">R$ <?= number_format($limite_disponivel, 2, ',', '.') ?></span>
                    </div>
                </div>

                <div class="progress" style="height: 8px; border-radius: 10px; background: #eee;">
                    <div class="progress-bar <?= $perc_uso > 85 ? 'bg-danger' : 'bg-dark' ?>" style="width: <?= min(100, $perc_uso) ?>%"></div>
                </div>
                <div class="mt-1">
                    <small class="text-muted" style="font-size: 0.65rem;">Seu limite total é de R$ <?= number_format($limite_cartao, 2, ',', '.') ?></small>
                </div>

                <?php if($total_fatura_mes > 0 && $itens_pendentes_mes > 0): ?>
                    <button class="btn btn-primary w-100 py-3 rounded-4 fw-bold mt-4 shadow" data-bs-toggle="modal" data-bs-target="#modalPagamento">
                        <i class="bi bi-check2-circle me-2"></i> PAGAR FATURA DO MÊS
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <h6 class="fw-bold mb-3 px-1 mt-4">Compras deste mês</h6>
        <?php if(empty($itens_fatura)): ?>
            <div class="p-5 text-center text-muted bg-white rounded-4 border border-dashed small">Nenhuma compra neste mês.</div>
        <?php else: foreach($itens_fatura as $it): ?>
            <div class="card border-0 shadow-sm rounded-4 p-3 mb-2 d-flex flex-row justify-content-between align-items-center bg-white">
                <div class="d-flex align-items-center">
                    <div class="bg-light p-2 rounded-3 me-3"><i class="bi <?= $it['contasituacao'] == 'Pago' ? 'bi-check-circle-fill text-success' : 'bi-cart' ?>"></i></div>
                    <div>
                        <span class="fw-bold d-block small <?= $it['contasituacao'] == 'Pago' ? 'text-decoration-line-through opacity-50' : '' ?>"><?= $it['contadescricao'] ?></span>
                        <small class="text-muted" style="font-size: 0.65rem;"><?= date('d/m', strtotime($it['contavencimento'])) ?> • <?= $it['categoriadescricao'] ?></small>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="text-end me-3">
                        <span class="fw-bold small d-block">R$ <?= number_format($it['contavalor'], 2, ',', '.') ?></span>
                        <?php if($it['contaparcela_total'] > 1): ?>
                            <small class="badge bg-light text-dark fw-normal" style="font-size: 0.55rem;">P: <?= $it['contaparcela_num'] ?>/<?= $it['contaparcela_total'] ?></small>
                        <?php endif; ?>
                    </div>
                    <button onclick="confirmarExclusao(<?= $it['contasid'] ?>, <?= $it['contaparcela_total'] > 1 ? 'true' : 'false' ?>)" class="btn-delete"><i class="bi bi-trash3"></i></button>
                </div>
            </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>
</div>

<script>
function confirmarExclusao(id, eParcelado) {
    let msg = eParcelado ? "Este item é um parcelamento. Excluir TODAS as parcelas?" : "Deseja excluir este lançamento?";
    if (confirm(msg)) window.location.href = "acoes_conta.php?id=" + id + "&acao=excluir";
}
</script>

<?php require_once "../includes/footer.php"; ?>