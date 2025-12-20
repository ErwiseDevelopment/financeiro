<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// 1. Busca Entradas (Receitas)
$stmt_entradas = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Entrada'
    ORDER BY c.contavencimento ASC");
$stmt_entradas->execute([$uid, $mes_filtro]);
$entradas = $stmt_entradas->fetchAll();

// 2. Busca Saídas (Despesas)
$stmt_saidas = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída'
    ORDER BY c.contavencimento ASC");
$stmt_saidas->execute([$uid, $mes_filtro]);
$saidas = $stmt_saidas->fetchAll();

// Formatação do mês para exibição
$fmt_mes = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
$titulo_mes = $fmt_mes->format(strtotime($mes_filtro."-01"));
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="index.php" class="text-dark fs-4 me-3"><i class="bi bi-chevron-left"></i></a>
            <div>
                <h5 class="fw-bold mb-0 text-capitalize"><?= $titulo_mes ?></h5>
                <p class="text-muted small mb-0">Visão Bilateral de Caixa</p>
            </div>
        </div>
        <div class="dropdown">
            <button class="btn btn-outline-primary btn-sm rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Mudar Mês
            </button>
            <ul class="dropdown-menu shadow border-0">
                <?php for($i = -2; $i <= 2; $i++): 
                    $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
                ?>
                <li><a class="dropdown-item" href="?mes=<?= $m ?>"><?= date('m/Y', strtotime($m)) ?></a></li>
                <?php endfor; ?>
            </ul>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-end mb-3 px-2">
                <span class="form-label-caps text-success"><i class="bi bi-arrow-down-circle-fill me-1"></i> Entradas</span>
                <h6 class="fw-bold mb-0 text-success">
                    R$ <?= number_format(array_sum(array_column($entradas, 'contavalor')), 2, ',', '.') ?>
                </h6>
            </div>
            
            <div class="bg-white rounded-4 shadow-sm border overflow-hidden">
                <div class="list-group list-group-flush">
                    <?php if(empty($entradas)): ?>
                        <div class="p-4 text-center text-muted small">Nenhuma receita.</div>
                    <?php else: foreach($entradas as $e): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="fw-bold d-block <?= $e['contasituacao'] == 'Pago' ? 'text-decoration-line-through text-muted' : '' ?>"><?= $e['contadescricao'] ?></span>
                                    <small class="text-muted"><?= $e['categoriadescricao'] ?> • <?= date('d/m', strtotime($e['contavencimento'])) ?></small>
                                </div>
                                <span class="fw-bold text-success">R$ <?= number_format($e['contavalor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if($e['contasituacao'] == 'Pendente'): ?>
                                    <a href="acoes_conta.php?acao=pagar&id=<?= $e['contasid'] ?>" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;">RECEBER</a>
                                <?php else: ?>
                                    <a href="acoes_conta.php?acao=estornar&id=<?= $e['contasid'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;">ESTORNAR</a>
                                <?php endif; ?>
                                <a href="editar_conta.php?id=<?= $e['contasid'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;">EDITAR</a>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-end mb-3 px-2">
                <span class="form-label-caps text-danger"><i class="bi bi-arrow-up-circle-fill me-1"></i> Saídas</span>
                <h6 class="fw-bold mb-0 text-danger">
                    R$ <?= number_format(array_sum(array_column($saidas, 'contavalor')), 2, ',', '.') ?>
                </h6>
            </div>

            <div class="bg-white rounded-4 shadow-sm border overflow-hidden">
                <div class="list-group list-group-flush">
                    <?php if(empty($saidas)): ?>
                        <div class="p-4 text-center text-muted small">Nenhuma despesa.</div>
                    <?php else: foreach($saidas as $s): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="fw-bold d-block <?= $s['contasituacao'] == 'Pago' ? 'text-decoration-line-through text-muted' : '' ?>"><?= $s['contadescricao'] ?></span>
                                    <small class="text-muted"><?= $s['categoriadescricao'] ?> • <?= date('d/m', strtotime($s['contavencimento'])) ?></small>
                                </div>
                                <span class="fw-bold text-danger">R$ <?= number_format($s['contavalor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if($s['contasituacao'] == 'Pendente'): ?>
                                    <a href="acoes_conta.php?acao=pagar&id=<?= $s['contasid'] ?>" class="btn btn-sm btn-danger rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;">PAGAR</a>
                                <?php else: ?>
                                    <a href="acoes_conta.php?acao=estornar&id=<?= $s['contasid'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;">ESTORNAR</a>
                                <?php endif; ?>
                                <a href="editar_conta.php?id=<?= $s['contasid'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;">EDITAR</a>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>