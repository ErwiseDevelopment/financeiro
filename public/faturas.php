<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m'); // Ex: 2025-01
$cartao_selecionado = $_GET['cartoid'] ?? null;

$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

// 1. Busca os cartões do usuário
$stmt_cards = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
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
$dados_cartao = null;

if ($cartao_selecionado) {
    // 2. Busca dados do cartão selecionado
    foreach($meus_cartoes as $m) { 
        if($m['cartoid'] == $cartao_selecionado) {
            $dados_cartao = $m;
            $limite_cartao = $m['cartolimite']; 
        }
    }

    // 3. BUSCA OS ITENS DA FATURA (Mesma lógica corrigida anteriormente)
    $sql_fatura = "SELECT c.*, cat.categoriadescricao 
                   FROM contas c 
                   LEFT JOIN categorias cat ON c.categoriaid = cat.categoriaid 
                   WHERE c.usuarioid = ? 
                   AND c.cartoid = ? 
                   AND COALESCE(c.competenciafatura, c.contacompetencia) = ? 
                   ORDER BY c.contavencimento ASC";
    
    $stmt_f = $pdo->prepare($sql_fatura);
    $stmt_f->execute([$uid, $cartao_selecionado, $mes_filtro]);
    $itens_fatura = $stmt_f->fetchAll();

    foreach($itens_fatura as $i) { 
        $total_fatura_mes += $i['contavalor']; 
        if($i['contasituacao'] == 'Pendente') $itens_pendentes_mes++;
    }

    // --- 4. LÓGICA DO LIMITE (AJUSTADA) ---
    // Objetivo: Somar TUDO que é 'Saída' e está 'Pendente' neste cartão, 
    // independente se é para este mês ou para 2030.
    // Assim que a conta vira 'Pago', ela sai dessa soma e libera o limite.
    $stmt_limite = $pdo->prepare("SELECT SUM(contavalor) as total_pendente 
                                  FROM contas 
                                  WHERE usuarioid = ? 
                                  AND cartoid = ? 
                                  AND contasituacao = 'Pendente' 
                                  AND contatipo = 'Saída'");
    
    $stmt_limite->execute([$uid, $cartao_selecionado]);
    $row_limite = $stmt_limite->fetch();
    $limite_comprometido_total = $row_limite['total_pendente'] ?? 0;
}

// Cálculo final do disponível
$limite_disponivel = $limite_cartao - $limite_comprometido_total;

// Evita divisão por zero no gráfico
$perc_uso = ($limite_cartao > 0) ? ($limite_comprometido_total / $limite_cartao) * 100 : 0;
?>

<style>
    body { background-color: #f8fafc; color: #334155; }
    .card-fatura { background: #fff; border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .card-fatura-header { background: #1e293b; color: #fff; padding: 25px; }
    .chip-cartao { 
        padding: 8px 18px; border-radius: 50px; border: 1px solid #dee2e6; 
        font-size: 0.85rem; text-decoration: none; color: #6c757d; white-space: nowrap; transition: 0.2s; background: #fff;
    }
    .chip-cartao.active { background: #1e293b; color: #fff; border-color: #1e293b; }
    .month-nav { background: #fff; border-radius: 15px; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #eee; }
    .month-nav a { color: #212529; text-decoration: none; font-weight: bold; }
    .btn-delete { color: #ff4d4d; opacity: 0.5; transition: 0.2s; cursor: pointer; border: none; background: none; }
    .btn-delete:hover { opacity: 1; }
    .btn-view-report { background: #fff; border: 1px solid #edf2f7; border-radius: 18px; padding: 15px; text-decoration: none; color: #2d3748; display: flex; align-items: center; height: 100%; }
</style>

<div class="container py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold m-0">Faturas</h4>
            <small class="text-muted">Cartão: <?= $dados_cartao['cartonome'] ?? 'Selecione' ?></small>
        </div>
        <div class="month-nav shadow-sm">
            <a href="?cartoid=<?= $cartao_selecionado ?>&mes=<?= $mes_anterior ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="mx-3 text-uppercase small fw-bold">
                <?= (new IntlDateFormatter('pt_BR', 0, 0, null, null, "MMMM yyyy"))->format($data_atual) ?>
            </span>
            <a href="?cartoid=<?= $cartao_selecionado ?>&mes=<?= $mes_proximo ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="row g-2 mb-4">
        <div class="col-6"><a href="resumo_cartoes.php" class="btn-view-report"><i class="bi bi-pie-chart-fill me-2"></i><span class="small fw-bold">RESUMO</span></a></div>
        <div class="col-6"><a href="faturas_geral.php" class="btn-view-report"><i class="bi bi-list-check me-2"></i><span class="small fw-bold">TODAS</span></a></div>
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
                    <span class="text-uppercase small opacity-75 fw-bold">Fatura de <?= (new IntlDateFormatter('pt_BR', 0, 0, null, null, "MMMM"))->format($data_atual) ?></span>
                    <span class="badge <?= $itens_pendentes_mes > 0 ? 'bg-warning text-dark' : 'bg-success' ?> rounded-pill">
                        <?= $itens_pendentes_mes > 0 ? 'ABERTA' : 'PAGA' ?>
                    </span>
                </div>
                <h2 class="fw-bold m-0">R$ <?= number_format($total_fatura_mes, 2, ',', '.') ?></h2>
                <small class="opacity-75" style="font-size: 0.8rem">Vence dia: <?= $dados_cartao['cartovencimento'] ?></small>
            </div>
            
            <div class="p-4 bg-white">
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block fw-bold" style="font-size: 0.65rem;">LIMITE EM USO</small>
                        <span class="fw-bold text-danger">R$ <?= number_format($limite_comprometido_total, 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted d-block fw-bold" style="font-size: 0.65rem;">DISPONÍVEL</small>
                        <span class="fw-bold text-success">R$ <?= number_format($limite_disponivel, 2, ',', '.') ?></span>
                    </div>
                </div>
                <div class="progress" style="height: 8px; border-radius: 10px; background: #eee;">
                    <div class="progress-bar <?= $perc_uso > 85 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= min(100, $perc_uso) ?>%"></div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold mb-3 px-1">Detalhamento da Fatura</h6>
        <?php if(empty($itens_fatura)): ?>
            <div class="p-5 text-center text-muted bg-white rounded-4 border">Nenhum lançamento previsto para esta fatura.</div>
        <?php else: foreach($itens_fatura as $it): ?>
            <div class="card border-0 shadow-sm rounded-4 p-3 mb-2 d-flex flex-row justify-content-between align-items-center bg-white">
                <div class="d-flex align-items-center">
                    <div class="bg-light p-2 rounded-3 me-3">
                        <i class="bi <?= $it['contasituacao'] == 'Pago' ? 'bi-check-circle-fill text-success' : 'bi-cart' ?>"></i>
                    </div>
                    <div>
                        <span class="fw-bold d-block small <?= $it['contasituacao'] == 'Pago' ? 'text-decoration-line-through opacity-50' : '' ?>"><?= $it['contadescricao'] ?></span>
                        <small class="text-muted" style="font-size: 0.65rem;">
                            <?= date('d/m', strtotime($it['contavencimento'])) ?> • <?= $it['categoriadescricao'] ?>
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center text-end">
                    <div class="me-3">
                        <span class="fw-bold small d-block">R$ <?= number_format($it['contavalor'], 2, ',', '.') ?></span>
                        <?php if($it['contaparcela_total'] > 1): ?>
                            <small class="badge bg-light text-dark fw-normal" style="font-size: 0.55rem;">P: <?= $it['contaparcela_num'] ?>/<?= $it['contaparcela_total'] ?></small>
                        <?php endif; ?>
                    </div>
                    <button onclick="confirmarExclusao(<?= $it['contasid'] ?>)" class="btn-delete"><i class="bi bi-trash3"></i></button>
                </div>
            </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>
</div>

<script>
function confirmarExclusao(id) {
    if (confirm("Deseja realmente excluir este lançamento da fatura?")) {
        window.location.href = "acoes_conta.php?id=" + id + "&acao=excluir&origem=faturas";
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>