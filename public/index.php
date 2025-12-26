<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$hoje = date('Y-m-d');
$tres_dias_depois = date('Y-m-d', strtotime('+3 days'));

// 1. ALERTAS DE VENCIMENTO PRÓXIMO
$stmt_alerta = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contasituacao = 'Pendente' AND c.contatipo = 'Saída'
    AND c.contavencimento <= ? ORDER BY c.contavencimento ASC");
$stmt_alerta->execute([$uid, $tres_dias_depois]);
$alertas = $stmt_alerta->fetchAll();

// 2. RESUMO DO MÊS
$sql = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as entradas,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as saidas
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$sql->execute([$uid, $mes_filtro]);
$resumo = $sql->fetch();

$tot_entradas = $resumo['entradas'] ?? 0;
$tot_saidas = $resumo['saidas'] ?? 0;
$saldo_atual = $tot_entradas - $tot_saidas;

// 3. LISTAGEM PRINCIPAL
$stmt = $pdo->prepare("SELECT c.*, cat.categoriadescricao FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? 
    ORDER BY c.contavencimento ASC");
$stmt->execute([$uid, $mes_filtro]);
$contas = $stmt->fetchAll();
?>
<?php if(isset($_GET['erro']) && $_GET['erro'] == 'ja_copiado'): ?>
    <div class="alert alert-warning border-0 shadow-sm small rounded-4 mb-4">
        <i class="bi bi-info-circle me-2"></i> O próximo mês já possui lançamentos. Cópia cancelada para evitar duplicidade.
    </div>
<?php endif; ?>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'copiado_sucesso'): ?>
    <div class="alert alert-success border-0 shadow-sm small rounded-4 mb-4">
        <i class="bi bi-check-circle me-2"></i> Lançamentos duplicados com sucesso para este mês!
    </div>
<?php endif; ?>
<style>
    :root { --p-color: #0d6efd; --s-color: #198754; --d-color: #dc3545; }
    body { background-color: #f4f7fa; font-family: 'Inter', sans-serif; }
    
    .card-balance { background: #fff; border-radius: 24px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
    .month-pill { 
        padding: 8px 16px; border-radius: 50px; background: #fff; border: 1px solid #e9ecef;
        color: #6c757d; font-weight: 600; font-size: 0.8rem; text-decoration: none; transition: 0.2s;
        white-space: nowrap;
    }
    .month-pill.active { background: var(--p-color); color: #fff; border-color: var(--p-color); }
    
    .list-item { 
        background: #fff; border-radius: 18px; padding: 14px; margin-bottom: 10px;
        border: 1px solid rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between;
    }
    .icon-box { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
        <div>
            <h5 class="fw-bold mb-0">Finance<span class="text-primary">Pro</span></h5>
            <small class="text-muted opacity-75">Resumo financeiro</small>
        </div>
        <a href="logout.php" class="text-dark fs-4"><i class="bi bi-person-circle"></i></a>
    </div>

    <div class="d-flex overflow-x-auto gap-2 mb-4 pb-1" style="scrollbar-width: none;">
        <?php for($i = -1; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $active = ($mes_filtro == $m) ? 'active' : '';
            $label = date('M y', strtotime($m."-01"));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $active ?>"><?= ucfirst($label) ?></a>
        <?php endfor; ?>
    </div>

    <div class="card-balance p-4 mb-4 text-center">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">Saldo Previsto</span>
        <h1 class="display-6 fw-bold my-2 <?= $saldo_atual >= 0 ? 'text-dark' : 'text-danger' ?>">
            R$ <?= number_format($saldo_atual, 2, ',', '.') ?>
        </h1>
        <div class="row mt-4 pt-3 border-top">
            <div class="col-6 border-end">
                <small class="text-success fw-bold d-block small mb-1">RECEITAS</small>
                <span class="fw-bold">R$ <?= number_format($tot_entradas, 2, ',', '.') ?></span>
            </div>
            <div class="col-6">
                <small class="text-danger fw-bold d-block small mb-1">DESPESAS</small>
                <span class="fw-bold">R$ <?= number_format($tot_saidas, 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <?php if(!empty($alertas)): ?>
        <h6 class="fw-bold text-danger small mb-3 px-1">CONTAS PRÓXIMAS</h6>
        <div class="d-flex gap-2 overflow-x-auto mb-4 pb-2" style="scrollbar-width: none;">
            <?php foreach($alertas as $a): ?>
                <div class="card border-0 shadow-sm bg-danger text-white p-3 rounded-4 flex-shrink-0" style="min-width: 200px;">
                    <small class="fw-bold opacity-75 d-block mb-1" style="font-size: 0.6rem;"><?= strtoupper($a['categoriadescricao']) ?></small>
                    <h6 class="fw-bold text-truncate mb-3" style="font-size: 0.9rem;"><?= $a['contadescricao'] ?></h6>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold small">R$ <?= number_format($a['contavalor'], 2, ',', '.') ?></span>
                        <a href="acoes_conta.php?acao=pagar&id=<?= $a['contasid'] ?>" class="btn btn-sm btn-light rounded-pill px-3 fw-bold" style="font-size: 0.6rem;">PAGAR</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
        <h6 class="fw-bold mb-0">Atividades</h6>
        <button onclick="confirmarCopia()" class="btn btn-link btn-sm text-decoration-none text-muted fw-bold p-0">
            <i class="bi bi-copy"></i> REPETIR MÊS
        </button>
    </div>

    <div class="mb-5 px-1">
        <?php if(empty($contas)): ?>
            <div class="p-5 text-center text-muted small bg-white rounded-4 border border-dashed">Nada por aqui.</div>
        <?php else: foreach($contas as $c): ?>
            <div class="list-item shadow-sm">
                <div class="d-flex align-items-center">
                    <div class="icon-box me-3 <?= $c['contatipo'] == 'Entrada' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                        <i class="bi <?= $c['contasituacao'] == 'Pago' ? 'bi-check-all' : ($c['contatipo'] == 'Entrada' ? 'bi-arrow-down-left' : 'bi-arrow-up-right') ?> fs-5"></i>
                    </div>
                    <div>
                        <span class="fw-bold text-dark d-block small <?= $c['contasituacao'] == 'Pago' ? 'text-decoration-line-through opacity-50' : '' ?>">
                            <?= $c['contadescricao'] ?>
                        </span>
                        <small class="text-muted" style="font-size: 0.65rem;">
                            <?= date('d/m', strtotime($c['contavencimento'])) ?> • <?= $a['categoriadescricao'] ?>
                        </small>
                    </div>
                </div>
                <div class="text-end">
                    <span class="fw-bold small <?= $c['contatipo'] == 'Entrada' ? 'text-success' : 'text-dark' ?>">
                        R$ <?= number_format($c['contavalor'], 2, ',', '.') ?>
                    </span>
                    <?php if($c['contasituacao'] == 'Pago'): ?>
                        <small class="d-block text-success fw-bold" style="font-size: 0.5rem;">CONCLUÍDO</small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
function confirmarCopia() {
    if (confirm("Deseja copiar todos os lançamentos para o mês seguinte?")) {
        window.location.href = "copiar_mes.php?mes_origem=<?= $mes_filtro ?>";
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>