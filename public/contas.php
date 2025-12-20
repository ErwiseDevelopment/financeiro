<?php
// ... conexão e autenticação ...
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$stmt = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
                       FROM contas c 
                       JOIN categorias cat ON c.categoriaid = cat.categoriaid 
                       WHERE c.usuarioid = ? AND c.contacompetencia = ? 
                       ORDER BY c.contavencimento ASC");
$stmt->execute([$uid, $mes_filtro]);
$contas = $stmt->fetchAll();
?>

<div class="container mt-3">
    <div class="list-group list-group-flush shadow-sm rounded-4 overflow-hidden">
        <?php foreach($contas as $c): ?>
            <div class="list-group-item bg-dark border-secondary py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-bold text-uppercase"><?= $c['categoriadescricao'] ?></small>
                        <h6 class="mb-0 text-white fw-bold"><?= $c['contadescricao'] ?></h6>
                        <small class="text-secondary">
                            Venc: <?= date('d/m', strtotime($c['contavencimento'])) ?> 
                            <?php if($c['contaparcela_total'] > 1): ?>
                                <span class="badge bg-secondary ms-1"><?= $c['contaparcela_atual'] ?>/<?= $c['contaparcela_total'] ?></span>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold <?= $c['contatipo'] == 'Entrada' ? 'text-success' : 'text-danger' ?>">
                            <?= $c['contatipo'] == 'Entrada' ? '+' : '-' ?> R$ <?= number_format($c['contavalor'], 2, ',', '.') ?>
                        </div>
                        <?php if($c['contasituacao'] == 'Pendente'): ?>
                            <a href="acoes_conta.php?acao=pagar&id=<?= $c['contasid'] ?>" class="btn btn-sm btn-outline-primary mt-1 rounded-pill px-3">Pagar</a>
                        <?php else: ?>
                            <span class="badge bg-success mt-1"><i class="bi bi-check-all"></i> PAGO</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>