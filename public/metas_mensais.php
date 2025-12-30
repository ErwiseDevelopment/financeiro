<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// Navegação de Datas
$data_obj = new DateTime($mes_filtro . "-01");
$mes_anterior = (clone $data_obj)->modify('-1 month')->format('Y-m');
$mes_proximo  = (clone $data_obj)->modify('+1 month')->format('Y-m');

$msg = '';
$msg_type = '';

// --- AÇÃO 1: SALVAR METAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_metas') {
    $metas_post = $_POST['metas'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        $stmtInsert = $pdo->prepare("
            INSERT INTO categorias_metas (usuarioid, categoriaid, competencia, valor) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");

        foreach ($metas_post as $cat_id => $valor_bruto) {
            $valor = $valor_bruto ? str_replace(['.', ','], ['', '.'], $valor_bruto) : 0;
            $stmtInsert->execute([$uid, $cat_id, $mes_filtro, $valor]);
        }
        
        $pdo->commit();
        $msg = "Planejamento atualizado com sucesso!";
        $msg_type = "success";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Erro ao salvar: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// --- AÇÃO 2: REPLICAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'replicar') {
    try {
        $sql = "INSERT INTO categorias_metas (usuarioid, categoriaid, competencia, valor)
                SELECT usuarioid, categoriaid, ?, valor
                FROM categorias_metas
                WHERE usuarioid = ? AND competencia = ?
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
        
        $stmtRep = $pdo->prepare($sql);
        $stmtRep->execute([$mes_proximo, $uid, $mes_filtro]);
        
        echo "<script>window.location.href='?mes=$mes_proximo&msg=replicado';</script>";
        exit;
    } catch (Exception $e) {
        $msg = "Erro ao replicar: " . $e->getMessage();
        $msg_type = "danger";
    }
}

if(isset($_GET['msg']) && $_GET['msg'] == 'replicado') {
    $msg = "Metas copiadas com sucesso do mês anterior!";
    $msg_type = "primary";
}

// --- BUSCAR DADOS (QUERY AJUSTADA) ---
// Agora filtra estritamente por contacompetencia = :mes
$stmt = $pdo->prepare("
    SELECT 
        c.categoriaid, 
        c.categoriadescricao,
        COALESCE(m.valor, 0) as meta_mes,
        (SELECT COALESCE(SUM(contavalor), 0) 
         FROM contas 
         WHERE categoriaid = c.categoriaid 
         AND usuarioid = :uid 
         AND contatipo = 'Saída' 
         AND contacompetencia = :mes 
        ) as gasto_real
    FROM categorias c
    LEFT JOIN categorias_metas m ON c.categoriaid = m.categoriaid AND m.competencia = :mes
    WHERE c.usuarioid = :uid 
    AND (c.categoriatipo = 'Despesa' OR c.categoriatipo = 'Ambos' OR c.categoriatipo = 'Saída')
    ORDER BY c.categoriadescricao ASC
");

$stmt->execute(['uid' => $uid, 'mes' => $mes_filtro]);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totais
$total_meta = 0;
$total_gasto_com_meta = 0;

foreach($lista as $l) {
    if($l['meta_mes'] > 0) {
        $total_meta += $l['meta_mes'];
        $total_gasto_com_meta += $l['gasto_real'];
    }
}
$perc_geral = ($total_meta > 0) ? ($total_gasto_com_meta / $total_meta) * 100 : 0;
?>

<style>
    :root { --primary: #4361ee; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --dark: #1e293b; }
    body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; }
    
    .card-stat { border: none; border-radius: 20px; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.02); height: 100%; transition: 0.3s; }
    .card-stat:hover { transform: translateY(-3px); }
    
    .form-control-custom { background: #f1f5f9; border: none; padding: 12px 15px; border-radius: 12px; font-weight: 700; color: #1e293b; text-align: right; }
    .form-control-custom:focus { background: #fff; box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1); outline: none; }
    .form-control-custom::placeholder { color: #cbd5e1; font-weight: 400; }

    .nav-pill-custom { background: #fff; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 50px; color: #64748b; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
    .progress-custom { height: 8px; border-radius: 10px; background: #f1f5f9; overflow: hidden; }
</style>

<div class="container py-4">

    <?php if($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <?php if($msg_type == 'success'): ?><i class="bi bi-check-circle-fill me-2"></i><?php endif; ?>
            <?= $msg ?> 
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <a href="index.php" class="text-decoration-none text-muted small fw-bold mb-1"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
            <h4 class="fw-bold m-0 text-dark">Metas Mensais</h4>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <a href="?mes=<?= $mes_anterior ?>" class="btn btn-white border rounded-circle shadow-sm" style="width: 40px; height: 40px; display: grid; place-items: center;"><i class="bi bi-chevron-left"></i></a>
            
            <div class="bg-white border px-4 py-2 rounded-pill shadow-sm text-center" style="min-width: 180px;">
                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.65rem;">Competência</small>
                <span class="fw-bold text-primary"><?= ucfirst((new IntlDateFormatter('pt_BR',0,0,null,null,'MMMM yyyy'))->format($data_obj)) ?></span>
            </div>

            <a href="?mes=<?= $mes_proximo ?>" class="btn btn-white border rounded-circle shadow-sm" style="width: 40px; height: 40px; display: grid; place-items: center;"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card-stat p-4 bg-dark text-white d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div>
                        <small class="opacity-50 text-uppercase fw-bold">Orçamento Total Planejado</small>
                        <h2 class="fw-bold m-0">R$ <?= number_format($total_meta, 2, ',', '.') ?></h2>
                    </div>
                    <div class="text-end">
                        <small class="opacity-50 text-uppercase fw-bold">Executado (Com Meta)</small>
                        <h4 class="fw-bold m-0 text-warning">R$ <?= number_format($total_gasto_com_meta, 2, ',', '.') ?></h4>
                    </div>
                </div>
                
                <div class="progress mb-2" style="height: 8px; background: rgba(255,255,255,0.1);">
                    <div class="progress-bar <?= $perc_geral > 100 ? 'bg-danger' : ($perc_geral > 80 ? 'bg-warning' : 'bg-success') ?>" 
                         style="width: <?= min(100, $perc_geral) ?>%"></div>
                </div>
                <small class="opacity-75"><?= number_format($perc_geral, 1) ?>% do orçamento comprometido</small>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card-stat p-4 d-flex flex-column justify-content-center align-items-center text-center">
                <form method="POST" onsubmit="return confirm('ATENÇÃO: Isso irá substituir todas as metas de <?= $mes_proximo ?> pelas metas atuais. Deseja continuar?');" class="w-100">
                    <input type="hidden" name="acao" value="replicar">
                    <div class="mb-2 text-primary bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-copy fs-4"></i>
                    </div>
                    <h6 class="fw-bold mt-2">Replicar para Próximo Mês</h6>
                    <p class="small text-muted mb-3">Copie estas metas para <?= date('m/Y', strtotime($mes_proximo)) ?></p>
                    <button type="submit" class="btn btn-outline-primary rounded-pill w-100 fw-bold">
                        Copiar Agora
                    </button>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" id="formMetas">
        <input type="hidden" name="acao" value="salvar_metas">
        
        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
            <h6 class="fw-bold text-muted text-uppercase small m-0">Definição de Teto por Categoria</h6>
            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                <i class="bi bi-check-lg me-1"></i> Salvar Tudo
            </button>
        </div>

        <div class="row g-3">
            <?php foreach($lista as $item): 
                $tem_meta = ($item['meta_mes'] > 0);
                $perc = $tem_meta ? ($item['gasto_real'] / $item['meta_mes']) * 100 : 0;
                $saldo = $item['meta_mes'] - $item['gasto_real'];
                
                // Cores dinâmicas
                $status_cor = ($perc > 100) ? 'bg-danger' : (($perc > 80) ? 'bg-warning' : 'bg-success');
                if(!$tem_meta) $status_cor = 'bg-secondary';
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card-stat p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-muted" style="width: 32px; height: 32px;">
                                <i class="bi bi-tag-fill small"></i>
                            </div>
                            <span class="fw-bold text-dark small"><?= $item['categoriadescricao'] ?></span>
                        </div>
                        <span class="badge bg-light text-dark border fw-normal">
                            Gasto: R$ <?= number_format($item['gasto_real'], 2, ',', '.') ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="small text-muted fw-bold mb-1 d-block">Meta Mensal (R$)</label>
                        <input type="text" 
                               name="metas[<?= $item['categoriaid'] ?>]" 
                               class="form-control form-control-custom money-mask" 
                               value="<?= $tem_meta ? number_format($item['meta_mes'], 2, ',', '.') : '' ?>" 
                               placeholder="Sem teto">
                    </div>

                    <?php if($tem_meta): ?>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <small class="fw-bold text-muted" style="font-size: 0.7rem;"><?= number_format($perc, 0) ?>%</small>
                            <small class="fw-bold <?= $saldo < 0 ? 'text-danger' : 'text-success' ?>" style="font-size: 0.7rem;">
                                <?= $saldo < 0 ? 'Excedeu' : 'Resta' ?> R$ <?= number_format(abs($saldo), 0, ',', '.') ?>
                            </small>
                        </div>
                        <div class="progress progress-custom">
                            <div class="progress-bar <?= $status_cor ?>" style="width: <?= min(100, $perc) ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center gap-2 opacity-50 mt-3">
                            <i class="bi bi-info-circle small"></i>
                            <small class="fw-bold" style="font-size: 0.75rem;">Sem teto definido</small>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="fixed-bottom p-3 d-md-none bg-white border-top shadow-lg">
            <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold py-3 shadow">
                Salvar Alterações
            </button>
        </div>
    </form>
</div>

<script>
    document.querySelectorAll('.money-mask').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if(value === '') return;
            value = (value / 100).toFixed(2) + '';
            value = value.replace(".", ",");
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
            e.target.value = value;
        });
    });
</script>

<?php require_once "../includes/footer.php"; ?>