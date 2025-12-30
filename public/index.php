<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$hoje = date('Y-m-d');

// --- 1. SALDO ACUMULADO CONSOLIDADO (PASSADO) ---
// Representa o dinheiro real que sobrou de meses anteriores (apenas o que foi pago/recebido)
$stmt_passado = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) -
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as saldo_passado
    FROM contas WHERE usuarioid = ? AND contacompetencia < ?");
$stmt_passado->execute([$uid, $mes_filtro]);
$saldo_passado_consolidado = $stmt_passado->fetch()['saldo_passado'] ?? 0;

// --- 2. PENDÊNCIAS ATRASADAS (O QUE FICOU PARA TRÁS) ---
// Precisamos saber o total de entradas e saídas pendentes de meses anteriores para a projeção
$stmt_atraso_total = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e_atraso,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s_atraso
    FROM contas WHERE usuarioid = ? AND contacompetencia < ? AND contasituacao = 'Pendente'");
$stmt_atraso_total->execute([$uid, $mes_filtro]);
$atrasos = $stmt_atraso_total->fetch();
$e_atrasada = $atrasos['e_atraso'] ?? 0;
$s_atrasada = $atrasos['s_atraso'] ?? 0;

// --- 3. MOVIMENTAÇÃO DO MÊS ATUAL ---
$stmt_resumo = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e_total,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s_total,
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as e_paga,
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as s_paga,
    SUM(CASE WHEN cartoid IS NOT NULL AND contatipo = 'Saída' THEN contavalor ELSE 0 END) as total_cartao_mes
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$stmt_resumo->execute([$uid, $mes_filtro]);
$res = $stmt_resumo->fetch();

$e_total = $res['e_total'] ?? 0;
$s_total = $res['s_total'] ?? 0;
$e_paga = $res['e_paga'] ?? 0;
$s_paga = $res['s_paga'] ?? 0;

// --- CÁLCULOS DE PROJEÇÃO ---

// Saldo Real Agora: Saldo que veio do passado + o que já foi movimentado no mês atual
$saldo_real_agora = $saldo_passado_consolidado + ($e_paga - $s_paga);

// Projeção Final (O "pulo do gato"): 
// Saldo Consolidado + (Tudo que deve entrar no mês e atrasados) - (Tudo que deve sair no mês e atrasados)
$projecao_final_mes = $saldo_passado_consolidado + ($e_total + $e_atrasada) - ($s_total + $s_atrasada);

// --- 4. CARTÕES E LISTAGENS ---
$sql_limite = $pdo->prepare("SELECT SUM(cartolimite) as limite_total FROM cartoes WHERE usuarioid = ?");
$sql_limite->execute([$uid]);
$limite_geral = $sql_limite->fetch()['limite_total'] ?? 0;

$sql_uso_total = $pdo->prepare("SELECT SUM(contavalor) as total_pendente FROM contas 
                               WHERE usuarioid = ? AND cartoid IS NOT NULL AND contasituacao = 'Pendente'");
$sql_uso_total->execute([$uid]);
$total_uso_cartao = $sql_uso_total->fetch()['total_pendente'] ?? 0;

// Listagem de atrasadas para o box vermelho
$stmt_atrasadas = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia < ? AND c.contasituacao = 'Pendente' 
    ORDER BY c.contavencimento ASC");
$stmt_atrasadas->execute([$uid, $mes_filtro]);
$contas_atrasadas = $stmt_atrasadas->fetchAll();

// Listagem principal do mês
$stmt_contas = $pdo->prepare("SELECT c.*, cat.categoriadescricao FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.cartoid IS NULL ORDER BY c.contavencimento ASC");
$stmt_contas->execute([$uid, $mes_filtro]);
$contas_lista = $stmt_contas->fetchAll();

$stmt_agrup_cartao = $pdo->prepare("SELECT car.cartonome, car.cartoid, SUM(c.contavalor) as total_fatura 
    FROM contas c JOIN cartoes car ON c.cartoid = car.cartoid
    WHERE c.usuarioid = ? AND c.contacompetencia = ? GROUP BY car.cartoid, car.cartonome");
$stmt_agrup_cartao->execute([$uid, $mes_filtro]);
$cartoes_resumo = $stmt_agrup_cartao->fetchAll();
?>

<style>
    :root { --primary: #4361ee; --bg: #f8fafc; --danger: #ef4444; --success: #10b981; --warning: #f59e0b; }
    body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
    .card-main { background: white; border-radius: 24px; padding: 22px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.02); }
    .card-credit { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; border-radius: 24px; padding: 22px; text-decoration: none; display: block; transition: transform 0.2s; }
    .card-credit:hover { transform: translateY(-3px); color: white; }
    .month-pill { padding: 8px 16px; border-radius: 12px; background: white; border: 1px solid #e2e8f0; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.8rem; white-space: nowrap; }
    .month-pill.active { background: var(--primary); color: white; border-color: var(--primary); }
    .transaction-item { background: white; border-radius: 16px; padding: 14px 18px; margin-bottom: 10px; border: 1px solid rgba(0,0,0,0.05); display: flex; align-items: center; transition: all 0.2s; }
    .atrasada-item { border-left: 4px solid var(--danger); background: #fff1f2; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 8px; }
    .btn-action { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none; }
    .projection-badge { font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; background: #e0e7ff; color: #4361ee; margin-top: 4px; display: inline-block; }
</style>

<div class="container py-4">
    
    <div class="d-flex overflow-x-auto gap-2 mb-4 pb-2" style="scrollbar-width: none;">
        <?php for($i = -1; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $label = ucfirst((new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMM yy'))->format(strtotime($m."-01")));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $mes_filtro == $m ? 'active' : '' ?>"><?= $label ?></a>
        <?php endfor; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-7">
            <div class="card-main h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Saldo Real Agora</small>
                        <h2 class="fw-bold mb-0 <?= $saldo_real_agora >= 0 ? 'text-dark' : 'text-danger' ?>">
                            R$ <?= number_format($saldo_real_agora, 2, ',', '.') ?>
                        </h2>
                    </div>
                    <div class="text-end">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Projeção Fim do Mês</small>
                        <h5 class="fw-bold mb-0 text-primary">R$ <?= number_format($projecao_final_mes, 2, ',', '.') ?></h5>
                        <?php if($s_atrasada > 0): ?>
                            <div class="projection-badge">Inclui R$ <?= number_format($s_atrasada, 2, ',', '.') ?> vencidos</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-2 border-top pt-3 mt-2">
                    <div class="col-6 border-end">
                        <small class="text-muted d-block small">Total Entradas</small>
                        <span class="fw-bold text-success">+ R$ <?= number_format($e_total + $e_atrasada, 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted d-block small">Total Saídas</small>
                        <span class="fw-bold text-danger">- R$ <?= number_format($s_total + $s_atrasada, 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-5">
            <a href="faturas.php" class="card-credit h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <small class="opacity-75 fw-bold text-uppercase" style="font-size: 0.6rem;">Limite em Uso (Cartões)</small>
                    <i class="bi bi-credit-card-2-front fs-4 opacity-50"></i>
                </div>
                <h4 class="fw-bold mb-3">R$ <?= number_format($total_uso_cartao, 2, ',', '.') ?></h4>
                <div class="progress bg-white bg-opacity-20 mb-3" style="height: 5px; border-radius: 10px;">
                    <div class="progress-bar bg-white" style="width: <?= ($limite_geral > 0) ? min(($total_uso_cartao/$limite_geral)*100, 100) : 0 ?>%"></div>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="opacity-75">Faturas <?= date('m/y', strtotime($mes_filtro)) ?>:</span>
                    <span class="fw-bold">R$ <?= number_format($res['total_cartao_mes'] ?? 0, 2, ',', '.') ?></span>
                </div>
            </a>
        </div>
    </div>

    <div class="px-1">
        
        <?php if(!empty($contas_atrasadas)): ?>
            <h6 class="text-danger fw-bold mt-4 mb-3 small text-uppercase"><i class="bi bi-exclamation-octagon-fill"></i> Pendências de Meses Anteriores</h6>
            <?php foreach($contas_atrasadas as $ca): ?>
                <div class="transaction-item atrasada-item shadow-sm">
                    <div class="flex-grow-1">
                        <span class="fw-bold small d-block"><?= $ca['contadescricao'] ?></span>
                        <small class="text-muted" style="font-size: 0.7rem;">
                            Venceu em: <?= date('d/m/y', strtotime($ca['contavencimento'])) ?> • <?= $ca['categoriadescricao'] ?>
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold d-block small text-danger">R$ <?= number_format($ca['contavalor'], 2, ',', '.') ?></span>
                        <a href="acoes_conta.php?acao=pagar&id=<?= $ca['contasid'] ?>" class="btn-action text-danger">Pagar Agora</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h6 class="fw-bold text-muted mt-4 mb-3 small text-uppercase">Movimentações de <?= date('M/y', strtotime($mes_filtro)) ?></h6>
        <?php if(empty($contas_lista) && empty($cartoes_resumo)): ?>
            <div class="text-center py-4 opacity-50">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <small>Nenhuma conta registrada para este mês.</small>
            </div>
        <?php endif; ?>

        <?php foreach($contas_lista as $c): $pago = ($c['contasituacao'] == 'Pago'); ?>
            <div class="transaction-item shadow-sm">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center">
                        <span class="status-dot <?= $pago ? 'bg-success' : 'bg-warning' ?>"></span>
                        <span class="fw-bold small <?= $pago ? 'text-muted text-decoration-line-through' : '' ?>"><?= $c['contadescricao'] ?></span>
                    </div>
                    <small class="text-muted" style="font-size: 0.7rem; margin-left: 16px;">
                        <?= date('d/m', strtotime($c['contavencimento'])) ?> • <?= $c['categoriadescricao'] ?>
                    </small>
                </div>
                <div class="text-end">
                    <span class="fw-bold d-block small <?= $c['contatipo'] == 'Entrada' ? 'text-success' : 'text-dark' ?>">
                        <?= $c['contatipo'] == 'Entrada' ? '+' : '-' ?> R$ <?= number_format($c['contavalor'], 2, ',', '.') ?>
                    </span>
                    <a href="acoes_conta.php?acao=pagar&id=<?= $c['contasid'] ?>" class="btn-action" style="color: <?= $pago ? 'var(--success)' : 'var(--primary)' ?>;">
                        <?= $pago ? 'Pago' : 'Dar Baixa' ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(!empty($cartoes_resumo)): ?>
            <h6 class="fw-bold text-muted mt-4 mb-3 small text-uppercase">Resumo por Cartão (Faturas)</h6>
            <?php foreach($cartoes_resumo as $cart): ?>
                <div class="transaction-item shadow-sm" style="border-left: 4px solid var(--warning);">
                    <div class="flex-grow-1">
                        <span class="fw-bold small d-block"><?= $cart['cartonome'] ?></span>
                        <small class="text-muted" style="font-size: 0.7rem;">Total da fatura neste mês</small>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold d-block small text-dark">R$ <?= number_format($cart['total_fatura'], 2, ',', '.') ?></span>
                        <a href="faturas.php?cartoid=<?= $cart['cartoid'] ?>&mes=<?= $mes_filtro ?>" class="btn-action text-warning">Ver Detalhes</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>