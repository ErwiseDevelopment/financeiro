<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$filtro_tipo = $_GET['tipo'] ?? 'todos';

$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

// 1. Limite Total (Soma de todos os cartões)
$stmt_limites = $pdo->prepare("SELECT SUM(cartolimite) as total_limite FROM cartoes WHERE usuarioid = ?");
$stmt_limites->execute([$uid]);
$limite_total = $stmt_limites->fetch()['total_limite'] ?? 0;

// 2. USO TOTAL REAL (O que você pediu: todas as parcelas pendentes de todos os meses)
$stmt_uso_real = $pdo->prepare("SELECT SUM(contavalor) as total_preso FROM contas 
                                WHERE usuarioid = ? AND cartoid IS NOT NULL AND contasituacao = 'Pendente'");
$stmt_uso_real->execute([$uid]);
$total_uso_geral = $stmt_uso_real->fetch()['total_preso'] ?? 0;

// 3. SQL da Fatura do Mês (Listagem da tela)
$sql_base = "SELECT c.*, car.cartonome, cat.categoriadescricao 
             FROM contas c
             JOIN cartoes car ON c.cartoid = car.cartoid
             JOIN categorias cat ON c.categoriaid = cat.categoriaid
             WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.cartoid IS NOT NULL";

if ($filtro_tipo == 'parcelados') $sql_base .= " AND c.contaparcela_total > 1";
if ($filtro_tipo == 'avulsos') $sql_base .= " AND c.contaparcela_total <= 1";

$sql_base .= " ORDER BY c.contavencimento ASC";

$stmt_contas = $pdo->prepare($sql_base);
$stmt_contas->execute([$uid, $mes_filtro]);
$contas = $stmt_contas->fetchAll();

$total_fatura_mes = 0;
foreach ($contas as $cta) { $total_fatura_mes += $cta['contavalor']; }

// Cálculo do Disponível Real baseado no comprometimento total
$limite_disponivel_real = $limite_total - $total_uso_geral;
?>

<style>
    .card-fatura-header { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 25px; padding: 30px; color: white; margin-bottom: 30px; }
    .btn-nav-mes { background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 12px; padding: 8px 15px; transition: 0.3s; text-decoration: none; }
    .btn-nav-mes:hover { background: rgba(255,255,255,0.2); color: white; }
    .item-conta { background: white; border-radius: 18px; padding: 15px 20px; margin-bottom: 12px; border: 1px solid #f1f5f9; transition: 0.3s; }
    .badge-cartao { font-size: 0.65rem; font-weight: 800; padding: 4px 10px; border-radius: 8px; text-transform: uppercase; }
    .info-box { background: rgba(255,255,255,0.05); border-radius: 15px; padding: 10px; border: 1px solid rgba(255,255,255,0.1); }
</style>

<div class="container py-4 mb-5">
    <div class="card-fatura-header shadow-lg text-center">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="text-white fs-4"><i class="bi bi-chevron-left"></i></a>
            <div class="d-flex align-items-center gap-2">
                <a href="?mes=<?= $mes_anterior ?>&tipo=<?= $filtro_tipo ?>" class="btn-nav-mes"><i class="bi bi-chevron-left"></i></a>
                <span class="fw-bold text-uppercase small" style="letter-spacing: 1px;">
                    <?= (new IntlDateFormatter('pt_BR', 0, 0, null, null, "MMMM yyyy"))->format($data_atual) ?>
                </span>
                <a href="?mes=<?= $mes_proximo ?>&tipo=<?= $filtro_tipo ?>" class="btn-nav-mes"><i class="bi bi-chevron-right"></i></a>
            </div>
            <div style="width: 24px;"></div>
        </div>

        <span class="opacity-75 small text-uppercase fw-bold" style="letter-spacing: 2px;">Fatura Deste Mês</span>
        <h1 class="display-5 fw-bold mt-1 mb-4">R$ <?= number_format($total_fatura_mes, 2, ',', '.') ?></h1>
        
        <div class="row g-2">
            <div class="col-4">
                <div class="info-box">
                    <small class="d-block opacity-75 mb-1" style="font-size: 0.6rem;">LIMITE TOTAL</small>
                    <span class="fw-bold small">R$ <?= number_format($limite_total, 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="col-4">
                <div class="info-box border-warning">
                    <small class="d-block text-warning mb-1" style="font-size: 0.6rem;">USO TOTAL (GERAL)</small>
                    <span class="fw-bold small">R$ <?= number_format($total_uso_geral, 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="col-4">
                <div class="info-box border-info">
                    <small class="d-block text-info mb-1" style="font-size: 0.6rem;">DISPONÍVEL REAL</small>
                    <span class="fw-bold small">R$ <?= number_format($limite_disponivel_real, 2, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2" style="scrollbar-width: none;">
        <a href="?mes=<?= $mes_filtro ?>&tipo=todos" class="btn <?= $filtro_tipo == 'todos' ? 'btn-dark' : 'btn-light border' ?> rounded-pill px-4 fw-bold small shadow-sm">Todos</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=parcelados" class="btn <?= $filtro_tipo == 'parcelados' ? 'btn-dark' : 'btn-light border' ?> rounded-pill px-4 fw-bold small shadow-sm">Parcelados</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=avulsos" class="btn <?= $filtro_tipo == 'avulsos' ? 'btn-dark' : 'btn-light border' ?> rounded-pill px-4 fw-bold small shadow-sm">Não Parcelados</a>
    </div>

    <div class="list-contas">
        <?php if (empty($contas)): ?>
            <div class="text-center py-5">
                <p class="text-muted">Nenhuma compra encontrada para este período.</p>
            </div>
        <?php else: ?>
            <?php foreach ($contas as $cta): ?>
                <div class="item-conta shadow-sm">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="bg-light text-dark rounded-3 p-2 me-3">
                                <i class="bi bi-cart3"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge-cartao bg-dark text-white"><?= $cta['cartonome'] ?></span>
                                    <span class="text-muted" style="font-size: 0.7rem;"><?= date('d/m', strtotime($cta['contavencimento'])) ?></span>
                                </div>
                                <h6 class="fw-bold mb-0 text-dark"><?= $cta['contadescricao'] ?></h6>
                                <small class="text-muted"><?= $cta['categoriadescricao'] ?></small>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold d-block">R$ <?= number_format($cta['contavalor'], 2, ',', '.') ?></span>
                            <?php if($cta['contaparcela_total'] > 1): ?>
                                <span class="text-primary fw-bold" style="font-size: 0.65rem;">P: <?= $cta['contaparcela_num'] ?>/<?= $cta['contaparcela_total'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>