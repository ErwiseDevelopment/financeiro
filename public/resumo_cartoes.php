<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$filtro_tipo = $_GET['tipo'] ?? 'todos'; // todos, parcelados, avulsos

// Lógica de navegação de meses
$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

// 1. Busca todos os cartões e seus limites
$stmt_cards = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ?");
$stmt_cards->execute([$uid]);
$meus_cartoes = $stmt_cards->fetchAll();

$limite_total_geral = 0;
$utilizado_geral = 0;
$resumo_por_cartao = [];

foreach ($meus_cartoes as $cartao) {
    $limite_total_geral += $cartao['cartolimite'];
    
    // Filtro SQL dinâmico
    $sql_filtro = "";
    if ($filtro_tipo == 'parcelados') $sql_filtro = " AND contaparcela_total > 1";
    if ($filtro_tipo == 'avulsos') $sql_filtro = " AND contaparcela_total <= 1";

    // Soma o que foi gasto neste cartão na competência selecionada
    $stmt_gasto = $pdo->prepare("SELECT SUM(contavalor) as total FROM contas 
                                 WHERE usuarioid = ? AND cartoid = ? AND contacompetencia = ? $sql_filtro");
    $stmt_gasto->execute([$uid, $cartao['cartoid'], $mes_filtro]);
    $total_gasto = $stmt_gasto->fetch()['total'] ?? 0;
    
    $utilizado_geral += $total_gasto;
    
    $resumo_por_cartao[] = [
        'nome' => $cartao['cartonome'],
        'limite' => $cartao['cartolimite'],
        'gasto' => $total_gasto,
        'id' => $cartao['cartoid'],
        'cor' => $cartao['cartocor'] ?? '#212529' // Se você tiver uma coluna de cor
    ];
}

$perc_geral = ($limite_total_geral > 0) ? ($utilizado_geral / $limite_total_geral) * 100 : 0;
?>

<style>
    .month-nav { background: #fff; border-radius: 15px; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #eee; }
    .card-resumo { border-radius: 20px; border: none; transition: 0.3s; }
    .filter-chip { padding: 8px 15px; border-radius: 50px; font-size: 0.8rem; text-decoration: none; color: #6c757d; background: #f8f9fa; border: 1px solid #dee2e6; transition: 0.2s; }
    .filter-chip.active { background: #212529; color: #fff; border-color: #212529; }
    .progress-thin { height: 6px; border-radius: 10px; }
</style>

<div class="container py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
    <div class="d-flex align-items-center">
        <a href="faturas.php" class="btn-back-app me-3">
            <i class="bi bi-chevron-left"></i>
        </a>
        
        <div>
            <h4 class="fw-bold m-0" style="font-size: 1.1rem;">Análise de Crédito</h4>
            <small class="text-muted" style="font-size: 0.75rem;">Visão consolidada dos cartões</small>
        </div>
    </div>

    <div class="month-nav shadow-sm border-0 bg-white">
        <a href="?mes=<?= $mes_anterior ?>&tipo=<?= $filtro_tipo ?>" class="text-decoration-none">
            <i class="bi bi-chevron-left text-dark"></i>
        </a>
        <span class="mx-2 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
            <?= (new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, "MMM yyyy"))->format($data_atual) ?>
        </span>
        <a href="?mes=<?= $mes_proximo ?>&tipo=<?= $filtro_tipo ?>" class="text-decoration-none">
            <i class="bi bi-chevron-right text-dark"></i>
        </a>
    </div>
</div>

    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2" style="scrollbar-width: none;">
        <a href="?mes=<?= $mes_filtro ?>&tipo=todos" class="filter-chip <?= $filtro_tipo == 'todos' ? 'active' : '' ?>">Todos</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=parcelados" class="filter-chip <?= $filtro_tipo == 'parcelados' ? 'active' : '' ?>">Parcelados</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=avulsos" class="filter-chip <?= $filtro_tipo == 'avulsos' ? 'active' : '' ?>">Compras Avulsas</a>
    </div>

    <div class="card card-resumo shadow-sm bg-dark text-white p-4 mb-4">
        <span class="small opacity-75 text-uppercase fw-bold">Gasto Total Consolidado</span>
        <h2 class="fw-bold mb-3">R$ <?= number_format($utilizado_geral, 2, ',', '.') ?></h2>
        
        <div class="d-flex justify-content-between small mb-1">
            <span>Uso do Limite Global</span>
            <span>R$ <?= number_format($limite_total_geral, 2, ',', '.') ?></span>
        </div>
        <div class="progress progress-thin bg-secondary">
            <div class="progress-bar bg-info" style="width: <?= min(100, $perc_geral) ?>%"></div>
        </div>
        <small class="mt-2 d-block opacity-75" style="font-size: 0.7rem;">
            Você comprometeu <?= number_format($perc_geral, 1) ?>% do seu limite total disponível.
        </small>
    </div>

    <h6 class="fw-bold mb-3">Distribuição por Cartão</h6>
    <div class="row g-3">
        <?php foreach ($resumo_por_cartao as $res): 
            $perc_card = ($res['limite'] > 0) ? ($res['gasto'] / $res['limite']) * 100 : 0;
        ?>
        <div class="col-12 col-md-6">
            <div class="card card-resumo shadow-sm p-3 bg-white border">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle me-2" style="width: 12px; height: 12px; background: #212529;"></div>
                        <span class="fw-bold"><?= $res['nome'] ?></span>
                    </div>
                    <a href="faturas.php?cartoid=<?= $res['id'] ?>&mes=<?= $mes_filtro ?>" class="btn btn-sm btn-light rounded-pill px-3">Ver Fatura</a>
                </div>
                
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <small class="text-muted d-block">Gasto no mês</small>
                        <span class="fw-bold h5 mb-0">R$ <?= number_format($res['gasto'], 2, ',', '.') ?></span>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Limite</small>
                        <small class="fw-bold">R$ <?= number_format($res['limite'], 2, ',', '.') ?></small>
                    </div>
                </div>

                <div class="progress progress-thin mt-3">
                    <div class="progress-bar bg-dark" style="width: <?= min(100, $perc_card) ?>%"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>