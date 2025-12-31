<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$filtro_tipo = $_GET['tipo'] ?? 'todos';

// Lógica de Datas
$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

$fmt_mes_ano = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'America/Sao_Paulo', IntlDateFormatter::GREGORIAN, "MMM yy");
$fmt_mes_longo = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'America/Sao_Paulo', IntlDateFormatter::GREGORIAN, "MMMM");

$campo_data_real = "COALESCE(competenciafatura, contacompetencia)";

// Busca cartões
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
    
    // Gasto do Mês
    $sql_mes = "SELECT SUM(contavalor) as total FROM contas 
                WHERE usuarioid = ? AND cartoid = ? 
                AND $campo_data_real = ?";
    if ($filtro_tipo == 'parcelados') $sql_mes .= " AND contaparcela_total > 1";
    if ($filtro_tipo == 'avulsos') $sql_mes .= " AND contaparcela_total <= 1";
    
    $stmt_mes = $pdo->prepare($sql_mes);
    $stmt_mes->execute([$uid, $cid, $mes_filtro]);
    $gasto_mes = (float)($stmt_mes->fetch()['total'] ?? 0);
    $gasto_mes_consolidado += $gasto_mes;

    // Uso Total
    $stmt_total = $pdo->prepare("SELECT SUM(contavalor) as total FROM contas 
                                 WHERE usuarioid = ? AND cartoid = ? 
                                 AND contasituacao = 'Pendente' 
                                 AND contatipo = 'Saída'");
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
    /* Navegação de Mês */
    .month-nav { background: #fff; border-radius: 15px; padding: 8px 15px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #eee; min-width: 160px; }
    
    /* Chips de Filtro */
    .filter-chip { padding: 8px 16px; border-radius: 50px; font-size: 0.85rem; text-decoration: none; color: #64748b; background: #fff; border: 1px solid #e2e8f0; transition: 0.2s; white-space: nowrap; font-weight: 500; }
    .filter-chip.active { background: #1e293b; color: #fff; border-color: #1e293b; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    /* Cards */
    .card-resumo { border-radius: 24px; border: 1px solid rgba(0,0,0,0.05); transition: transform 0.2s; }
    .card-resumo:active { transform: scale(0.98); }
    
    .progress-thin { height: 8px; border-radius: 10px; background-color: rgba(255,255,255,0.1); }
    .info-mini { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7; display: block; margin-bottom: 2px; }
    
    /* Responsividade Fina */
    @media (max-width: 576px) {
        .card-resumo { padding: 1.25rem !important; }
        .big-number { font-size: 1.5rem !important; } /* Números grandes no mobile */
        .card-divider-mobile { 
            border-left: none !important; 
            border-top: 1px solid rgba(255,255,255,0.2); 
            padding-top: 15px; margin-top: 15px; 
            text-align: left !important;
        }
    }
</style>

<div class="container py-4 mb-5">
    
    <div class="row align-items-center mb-4 g-3">
        <div class="col-7 col-md-8">
            <div class="d-flex align-items-center">
                <a href="faturas.php" class="btn btn-light rounded-circle shadow-sm me-3 border"><i class="bi bi-chevron-left"></i></a>
                <div>
                    <h4 class="fw-bold m-0 lh-1">Análise de Crédito</h4>
                    <small class="text-muted">Visão geral dos limites</small>
                </div>
            </div>
        </div>
        <div class="col-5 col-md-4">
            <div class="month-nav shadow-sm">
                <a href="?mes=<?= $mes_anterior ?>&tipo=<?= $filtro_tipo ?>"><i class="bi bi-chevron-left text-dark"></i></a>
                <span class="text-uppercase fw-bold small"><?= $fmt_mes_ano->format($data_atual) ?></span>
                <a href="?mes=<?= $mes_proximo ?>&tipo=<?= $filtro_tipo ?>"><i class="bi bi-chevron-right text-dark"></i></a>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2 px-1" style="scrollbar-width: none;">
        <a href="?mes=<?= $mes_filtro ?>&tipo=todos" class="filter-chip <?= $filtro_tipo == 'todos' ? 'active' : '' ?>">Todos</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=parcelados" class="filter-chip <?= $filtro_tipo == 'parcelados' ? 'active' : '' ?>">Parcelados</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=avulsos" class="filter-chip <?= $filtro_tipo == 'avulsos' ? 'active' : '' ?>">Avulsos</a>
    </div>

    <div class="card card-resumo shadow bg-dark text-white p-4 mb-4 border-0">
        <div class="row">
            <div class="col-12 col-sm-6 mb-2 mb-sm-0">
                <span class="info-mini text-info">Total Utilizado</span>
                <h2 class="fw-bold mb-0 big-number">R$ <?= number_format($utilizado_total_global, 2, ',', '.') ?></h2>
                <small class="opacity-50" style="font-size: 0.75rem;">Soma de todas faturas futuras</small>
            </div>
            
            <div class="col-12 col-sm-6 text-sm-end card-divider-mobile border-start-sm border-secondary ps-sm-4">
                <span class="info-mini">Limite Global</span>
                <h4 class="fw-bold mb-0 text-white-50">R$ <?= number_format($limite_total_geral, 2, ',', '.') ?></h4>
                <div class="mt-1">
                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">
                        Livre: R$ <?= number_format($limite_total_geral - $utilizado_total_global, 2, ',', '.') ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="progress progress-thin mt-4">
            <div class="progress-bar bg-info" style="width: <?= min(100, $perc_geral) ?>%"></div>
        </div>
        
        <div class="d-flex flex-wrap justify-content-between mt-3 gap-2" style="font-size: 0.75rem;">
            <span class="opacity-75">
                <i class="bi bi-pie-chart-fill me-1"></i> <?= number_format($perc_geral, 1) ?>% ocupado
            </span>
            <span class="text-warning fw-bold bg-warning bg-opacity-10 px-2 py-1 rounded">
                Fatura <?= ucfirst($fmt_mes_longo->format($data_atual)) ?>: R$ <?= number_format($gasto_mes_consolidado, 2, ',', '.') ?>
            </span>
        </div>
    </div>

    <h6 class="fw-bold mb-3 px-1 text-muted small text-uppercase">Detalhamento por Cartão</h6>
    <div class="row g-3">
        <?php foreach ($resumo_por_cartao as $res): 
            $perc_card = ($res['limite'] > 0) ? ($res['uso_total'] / $res['limite']) * 100 : 0;
            
            // Cor da barra baseada no uso
            $bar_color = 'bg-primary';
            if($perc_card > 70) $bar_color = 'bg-warning';
            if($perc_card > 90) $bar_color = 'bg-danger';
        ?>
        <div class="col-12 col-md-6">
            <div class="card card-resumo shadow-sm p-3 bg-white h-100">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                        <div class="bg-light p-2 rounded-3 text-dark"><i class="bi bi-credit-card-2-front-fill"></i></div>
                        <div class="text-truncate">
                            <span class="fw-bold d-block text-dark text-truncate"><?= $res['nome'] ?></span>
                            <small class="text-muted" style="font-size: 0.65rem;">Limite: <?= number_format($res['limite'], 0, ',', '.') ?></small>
                        </div>
                    </div>
                    <a href="faturas.php?cartoid=<?= $res['id'] ?>&mes=<?= $mes_filtro ?>" 
                       class="btn btn-sm btn-outline-dark rounded-pill py-1 px-3 ms-2" 
                       style="font-size: 0.7rem; font-weight: 700; white-space: nowrap;">
                        FATURA <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                
                <div class="row g-2 mb-2">
                    <div class="col-6 border-end border-light">
                        <small class="text-muted d-block info-mini">Em uso (Total)</small>
                        <span class="fw-bold text-danger" style="font-size: 0.95rem;">R$ <?= number_format($res['uso_total'], 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted d-block info-mini">Neste Mês</small>
                        <span class="fw-bold text-dark" style="font-size: 0.95rem;">R$ <?= number_format($res['gasto_mes'], 2, ',', '.') ?></span>
                    </div>
                </div>

                <div class="progress" style="height: 6px; border-radius: 10px; background-color: #f1f5f9;">
                    <div class="progress-bar <?= $bar_color ?>" style="width: <?= min(100, $perc_card) ?>%"></div>
                </div>
                
                <div class="d-flex justify-content-between mt-2" style="font-size: 0.65rem;">
                    <span class="text-muted fw-bold"><?= number_format($perc_card, 0) ?>% tomado</span>
                    <span class="text-success fw-bold">Livre: <?= number_format($res['limite'] - $res['uso_total'], 2, ',', '.') ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>