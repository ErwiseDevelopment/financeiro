<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$filtro_tipo = $_GET['tipo'] ?? 'todos';

$data_atual = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_atual)->modify('-1 month')->format('Y-m');
$mes_proximo = (clone $data_atual)->modify('+1 month')->format('Y-m');

// 1. Limite Total
$stmt_limites = $pdo->prepare("SELECT SUM(cartolimite) as total_limite FROM cartoes WHERE usuarioid = ?");
$stmt_limites->execute([$uid]);
$limite_total = $stmt_limites->fetch()['total_limite'] ?? 0;

// 2. Uso Total Real (Tudo que está pendente no cartão)
$stmt_uso_real = $pdo->prepare("SELECT SUM(contavalor) as total_preso FROM contas 
                                WHERE usuarioid = ? AND cartoid IS NOT NULL AND contasituacao = 'Pendente' AND contatipo = 'Saída'");
$stmt_uso_real->execute([$uid]);
$total_uso_geral = $stmt_uso_real->fetch()['total_preso'] ?? 0;

// 3. SQL da Fatura do Mês
$sql_base = "SELECT c.*, car.cartonome, cat.categoriadescricao 
             FROM contas c
             JOIN cartoes car ON c.cartoid = car.cartoid
             JOIN categorias cat ON c.categoriaid = cat.categoriaid
             WHERE c.usuarioid = ? 
             AND COALESCE(c.competenciafatura, c.contacompetencia) = ? 
             AND c.cartoid IS NOT NULL";

if ($filtro_tipo == 'parcelados') $sql_base .= " AND c.contaparcela_total > 1";
if ($filtro_tipo == 'avulsos') $sql_base .= " AND c.contaparcela_total <= 1";

$sql_base .= " ORDER BY c.contavencimento ASC";

$stmt_contas = $pdo->prepare($sql_base);
$stmt_contas->execute([$uid, $mes_filtro]);
$contas = $stmt_contas->fetchAll();

$total_fatura_mes = 0;
foreach ($contas as $cta) { $total_fatura_mes += $cta['contavalor']; }

$limite_disponivel_real = $limite_total - $total_uso_geral;
?>

<style>
    /* Header Moderno com Gradiente */
    .card-fatura-header { 
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%); 
        border-radius: 24px; 
        padding: 25px; 
        color: white; 
        margin-bottom: 25px; 
        position: relative;
        overflow: hidden;
    }
    
    /* Decoração de Fundo */
    .card-fatura-header::before {
        content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%; pointer-events: none;
    }

    /* Navegação de Mês */
    .btn-nav-mes { 
        background: rgba(255,255,255,0.15); color: white; border: none; 
        border-radius: 12px; width: 36px; height: 36px; display: flex; 
        align-items: center; justify-content: center; transition: 0.2s; text-decoration: none; 
    }
    .btn-nav-mes:hover { background: rgba(255,255,255,0.3); color: white; transform: scale(1.05); }

    /* Cards de Info (Resumo) */
    .info-box { 
        background: rgba(255,255,255,0.1); 
        border-radius: 16px; 
        padding: 12px 15px; 
        border: 1px solid rgba(255,255,255,0.15); 
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .info-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; margin-bottom: 4px; display: block; }
    .info-value { font-size: 0.9rem; font-weight: 700; white-space: nowrap; }

    /* Lista de Itens */
    .item-conta { 
        background: white; border-radius: 16px; padding: 15px; margin-bottom: 10px; 
        border: 1px solid #f1f5f9; transition: transform 0.2s; 
    }
    .item-conta:active { transform: scale(0.98); background-color: #f8fafc; }
    
    .badge-cartao { 
        font-size: 0.6rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; 
        text-transform: uppercase; background-color: #e2e8f0; color: #475569; letter-spacing: 0.5px;
    }

    /* Responsividade */
    @media (max-width: 576px) {
        .card-fatura-header { padding: 20px; border-radius: 20px; }
        .display-5 { font-size: 2rem; } /* Reduz tamanho do valor total */
        .info-box { padding: 10px; }
        .info-value { font-size: 0.8rem; }
    }
</style>

<div class="container py-4 mb-5">
    
    <div class="card-fatura-header shadow-lg">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="faturas.php" class="text-white opacity-75 text-decoration-none d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> <span class="small fw-bold">Voltar</span>
            </a>
            
            <div class="d-flex align-items-center gap-3 bg-dark bg-opacity-25 px-2 py-1 rounded-4 border border-white border-opacity-10">
                <a href="?mes=<?= $mes_anterior ?>&tipo=<?= $filtro_tipo ?>" class="btn-nav-mes"><i class="bi bi-chevron-left"></i></a>
                <span class="fw-bold text-uppercase small" style="min-width: 80px; text-align: center;">
                    <?= (new IntlDateFormatter('pt_BR', 0, 0, null, null, "MMM yy"))->format($data_atual) ?>
                </span>
                <a href="?mes=<?= $mes_proximo ?>&tipo=<?= $filtro_tipo ?>" class="btn-nav-mes"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>

        <div class="text-center mb-4">
            <span class="opacity-75 small text-uppercase fw-bold ls-1">Fatura Total Consolidada</span>
            <h1 class="display-5 fw-bold mt-1 mb-0">R$ <?= number_format($total_fatura_mes, 2, ',', '.') ?></h1>
        </div>
        
        <div class="row g-2 text-center">
            <div class="col-4">
                <div class="info-box">
                    <span class="info-label text-warning">Em Uso</span>
                    <span class="info-value">R$ <?= number_format($total_uso_geral, 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="col-4">
                <div class="info-box">
                    <span class="info-label text-info">Livre</span>
                    <span class="info-value">R$ <?= number_format($limite_disponivel_real, 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="col-4">
                <div class="info-box border-0 bg-white bg-opacity-10">
                    <span class="info-label">Limite</span>
                    <span class="info-value">R$ <?= number_format($limite_total, 2, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2 px-1" style="scrollbar-width: none;">
        <a href="?mes=<?= $mes_filtro ?>&tipo=todos" class="btn <?= $filtro_tipo == 'todos' ? 'btn-dark' : 'btn-light border' ?> rounded-pill px-4 fw-bold small shadow-sm text-nowrap">Todos</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=parcelados" class="btn <?= $filtro_tipo == 'parcelados' ? 'btn-dark' : 'btn-light border' ?> rounded-pill px-4 fw-bold small shadow-sm text-nowrap">Parcelados</a>
        <a href="?mes=<?= $mes_filtro ?>&tipo=avulsos" class="btn <?= $filtro_tipo == 'avulsos' ? 'btn-dark' : 'btn-light border' ?> rounded-pill px-4 fw-bold small shadow-sm text-nowrap">À Vista</a>
    </div>

    <div class="list-contas">
        <?php if (empty($contas)): ?>
            <div class="text-center py-5">
                <div class="mb-3 opacity-25"><i class="bi bi-receipt fs-1"></i></div>
                <p class="text-muted fw-bold small">Nenhuma compra encontrada para este filtro.</p>
            </div>
        <?php else: ?>
            <small class="text-muted fw-bold text-uppercase ms-1 mb-2 d-block" style="font-size: 0.7rem;">Detalhamento</small>
            <?php foreach ($contas as $cta): ?>
                <div class="item-conta shadow-sm d-flex justify-content-between align-items-center">
                    
                    <div class="d-flex align-items-center overflow-hidden">
                        <div class="bg-light text-dark rounded-3 p-2 me-3 flex-shrink-0 border">
                            <i class="bi bi-bag-check fs-5"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge-cartao"><?= $cta['cartonome'] ?></span>
                                <span class="text-muted" style="font-size: 0.65rem; font-weight: 600;"><?= date('d/m', strtotime($cta['contavencimento'])) ?></span>
                            </div>
                            <h6 class="fw-bold mb-0 text-dark text-truncate" style="font-size: 0.9rem;"><?= $cta['contadescricao'] ?></h6>
                            <small class="text-muted" style="font-size: 0.7rem;"><?= $cta['categoriadescricao'] ?></small>
                        </div>
                    </div>

                    <div class="text-end ms-2 flex-shrink-0">
                        <span class="fw-bold d-block text-dark" style="font-size: 0.95rem;">R$ <?= number_format($cta['contavalor'], 2, ',', '.') ?></span>
                        <?php if($cta['contaparcela_total'] > 1): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-pill" style="font-size: 0.6rem;">
                                <?= $cta['contaparcela_num'] ?>/<?= $cta['contaparcela_total'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>