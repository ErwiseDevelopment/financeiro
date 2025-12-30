<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$filtro_tipo = $_GET['tipo'] ?? 'todos';

// Lógica de Datas para Navegação
$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

// Formatadores de data para exibição
$fmt_mes_ano = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'America/Sao_Paulo', IntlDateFormatter::GREGORIAN, "MMM yy");
$fmt_mes_longo = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'America/Sao_Paulo', IntlDateFormatter::GREGORIAN, "MMMM");

// 1. Busca cartões
$stmt_cards = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
$stmt_cards->execute([$uid]);
$meus_cartoes = $stmt_cards->fetchAll();

$limite_total_geral = 0;
$utilizado_total_global = 0;
$gasto_mes_consolidado = 0;
$resumo_por_cartao = [];

foreach ($meus_cartoes as $cartao) {
    $cid = $cartao['cartoid'];
    $limite_cartao = (float)$cartao['cartolimite'];
    $limite_total_geral += $limite_cartao;
    
    // GASTO DO MÊS
    $sql_mes = "SELECT SUM(contavalor) as total FROM contas 
                WHERE usuarioid = ? AND cartoid = ? AND contacompetencia = ?";
    if ($filtro_tipo == 'parcelados') $sql_mes .= " AND contaparcela_total > 1";
    if ($filtro_tipo == 'avulsos') $sql_mes .= " AND contaparcela_total <= 1";
    
    $stmt_mes = $pdo->prepare($sql_mes);
    $stmt_mes->execute([$uid, $cid, $mes_filtro]);
    $gasto_mes = (float)($stmt_mes->fetch()['total'] ?? 0);
    $gasto_mes_consolidado += $gasto_mes;

    // USO TOTAL (Pendentes)
    $stmt_total = $pdo->prepare("SELECT SUM(contavalor) as total FROM contas 
                                 WHERE usuarioid = ? AND cartoid = ? AND contasituacao = 'Pendente'");
    $stmt_total->execute([$uid, $cid]);
    $uso_total_cartao = (float)($stmt_total->fetch()['total'] ?? 0);
    $utilizado_total_global += $uso_total_cartao;
    
    $resumo_por_cartao[] = [
        'id' => $cid,
        'nome' => $cartao['cartonome'],
        'limite' => $limite_cartao,
        'gasto_mes' => $gasto_mes,
        'uso_total' => $uso_total_cartao
    ];
}

$perc_geral = ($limite_total_geral > 0) ? ($utilizado_total_global / $limite_total_geral) * 100 : 0;
?>

<style>
    .month-nav { background: #fff; border-radius: 15px; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #eee; }
    .card-resumo { border-radius: 20px; border: none; transition: 0.3s; }
    .filter-chip { padding: 8px 15px; border-radius: 50px; font-size: 0.8rem; text-decoration: none; color: #6c757d; background: #f8f9fa; border: 1px solid #dee2e6; transition: 0.2s; white-space: nowrap; }
    .filter-chip.active { background: #212529; color: #fff; border-color: #212529; }
    .progress-thin { height: 6px; border-radius: 10px; }
    .info-mini { font-size: 0.65rem; font-weight: bold; text-transform: uppercase; opacity: 0.8; }
</style>

<div class="container py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
        <div class="d-flex align-items-center">
            <a href="faturas.php" class="btn btn-light rounded-circle me-3"><i class="bi bi-chevron-left"></i></a>
            <div>
                <h4 class="fw-bold m-0" style="font-size: 1.1rem;">Análise de Crédito</h4>
                <small class="text-muted" style="font-size: 0.75rem;">Saúde dos seus cartões</small>
            </div>
        </div>
        <div class="month-nav shadow-sm border-0">
            <a href="?mes=<?= $mes_anterior ?>&tipo=<?= $filtro_tipo ?>"><i class="bi bi-chevron-left text-dark"></i></a>
            <span class="mx-2 text-uppercase fw-bold" style="font-size: 0.7rem;"><?= $fmt_mes_ano->format($data_atual) ?></span>
            <a href="?mes=<?= $mes_proximo ?>&tipo=<?= $filtro_tipo ?>"><i class="bi bi-chevron-right text-dark"></i></a>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2" style="scrollbar-width: none;">
        <a href="?mes=<?= $mes_filtro ?>&tipo=todos" class="filter-chip <?= $filtro_tipo == 'todos' ? 'active' : '' ?>">Todos</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=parcelados" class="filter-chip <?= $filtro_tipo == 'parcelados' ? 'active' : '' ?>">Parcelados</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=avulsos" class="filter-chip <?= $filtro_tipo == 'avulsos' ? 'active' : '' ?>">Avulsos</a>
    </div>

    <div class="card card-resumo shadow-sm bg-dark text-white p-4 mb-4">
        <div class="row">
            <div class="col-7">
                <span class="info-mini">Uso Total Utilizado</span>
                <h2 class="fw-bold mb-0">R$ <?= number_format($utilizado_total_global, 2, ',', '.') ?></h2>
                <small class="opacity-50" style="font-size: 0.65rem;">Soma de todas as faturas futuras</small>
            </div>
            <div class="col-5 text-end border-start border-secondary">
                <span class="info-mini">Limite Total</span>
                <h4 class="fw-bold mb-0">R$ <?= number_format($limite_total_geral, 2, ',', '.') ?></h4>
                <small class="text-info fw-bold">Disponível: R$ <?= number_format($limite_total_geral - $utilizado_total_global, 2, ',', '.') ?></small>
            </div>
        </div>
        <div class="progress progress-thin bg-secondary mt-4">
            <div class="progress-bar bg-info" style="width: <?= min(100, $perc_geral) ?>%"></div>
        </div>
        <div class="d-flex justify-content-between mt-2" style="font-size: 0.7rem;">
            <span>Comprometimento Global: <?= number_format($perc_geral, 1) ?>%</span>
            <span class="text-warning">Fatura <?= $fmt_mes_longo->format($data_atual) ?>: R$ <?= number_format($gasto_mes_consolidado, 2, ',', '.') ?></span>
        </div>
    </div>

    <h6 class="fw-bold mb-3">Detalhamento por Cartão</h6>
    <div class="row g-3">
        <?php foreach ($resumo_por_cartao as $res): 
            $perc_card = ($res['limite'] > 0) ? ($res['uso_total'] / $res['limite']) * 100 : 0;
        ?>
        <div class="col-12 col-md-6">
            <div class="card card-resumo shadow-sm p-3 bg-white border">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="fw-bold d-block"><?= $res['nome'] ?></span>
                        <small class="text-muted" style="font-size: 0.65rem;">Limite: R$ <?= number_format($res['limite'], 2, ',', '.') ?></small>
                    </div>
                    <a href="faturas.php?cartoid=<?= $res['id'] ?>&mes=<?= $mes_filtro ?>" 
                       class="btn btn-sm btn-outline-dark rounded-pill py-1 px-3" 
                       style="font-size: 0.7rem; font-weight: bold;">
                       VER FATURA
                    </a>
                </div>
                
                <div class="row g-0 mb-2">
                    <div class="col-6">
                        <small class="text-muted d-block info-mini">Uso Total</small>
                        <span class="fw-bold text-danger" style="font-size: 0.9rem;">R$ <?= number_format($res['uso_total'], 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted d-block info-mini">Neste Mês</small>
                        <span class="fw-bold text-dark" style="font-size: 0.9rem;">R$ <?= number_format($res['gasto_mes'], 2, ',', '.') ?></span>
                    </div>
                </div>

                <div class="progress progress-thin bg-light">
                    <div class="progress-bar bg-dark" style="width: <?= min(100, $perc_card) ?>%"></div>
                </div>
                <div class="d-flex justify-content-between mt-1" style="font-size: 0.6rem;">
                    <span class="text-muted"><?= number_format($perc_card, 1) ?>% ocupado</span>
                    <span class="fw-bold text-success">Livre: R$ <?= number_format($res['limite'] - $res['uso_total'], 2, ',', '.') ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>