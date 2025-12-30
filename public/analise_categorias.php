<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_inicio_default = date('Y-m', strtotime('-5 months'));
$mes_fim_default = date('Y-m');

$stmt = $pdo->prepare("SELECT categoriaid, categoriadescricao FROM categorias WHERE usuarioid = ? AND (categoriatipo = 'Despesa' OR categoriatipo = 'Saída') ORDER BY categoriadescricao ASC");
$stmt->execute([$uid]);
$todas_categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
    .app-container { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }
    .card-app { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.04); height: 100%; }
    .label-app { font-size: 0.7rem; font-weight: 800; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .input-app { background-color: #f8fafc; border: 2px solid #e2e8f0; padding: 10px 15px; border-radius: 12px; font-weight: 600; color: #334155; width: 100%; transition: 0.2s; }
    .input-app:focus { outline: none; background-color: #fff; border-color: #4361ee; }
    .kpi-value { font-size: 1.5rem; font-weight: 800; color: #1e293b; line-height: 1; }
    .kpi-label { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
    .icon-kpi { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 10px; }
    .progress-thick { height: 12px; border-radius: 10px; background: #e2e8f0; overflow: hidden; margin-top: 10px; }
    .meta-info { font-size: 0.85rem; font-weight: 600; color: #64748b; }
    .chart-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); display: none; justify-content: center; align-items: center; flex-direction: column; z-index: 10; border-radius: 14px; }
</style>

<div class="app-container">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <a href="index.php" class="text-decoration-none text-muted fw-bold small mb-2 d-inline-block"><i class="bi bi-arrow-left"></i> Voltar</a>
            <h3 class="fw-bold m-0 text-dark">Análise por Categoria</h3>
        </div>
    </div>

    <?php if (empty($todas_categorias)): ?>
        <div class="text-center py-5"><p class="text-muted">Cadastre categorias de despesa para começar.</p></div>
    <?php else: ?>

        <div class="card-app mb-4 bg-white">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="label-app">Categoria</label>
                    <select id="selectCategoria" class="input-app" onchange="carregarDados()">
                        <?php foreach($todas_categorias as $cat): ?>
                            <option value="<?= $cat['categoriaid'] ?>"><?= $cat['categoriadescricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="label-app">De</label>
                    <input type="month" id="evoInicio" class="input-app" value="<?= $mes_inicio_default ?>" onchange="carregarDados()">
                </div>
                <div class="col-6 col-md-3">
                    <label class="label-app">Até</label>
                    <input type="month" id="evoFim" class="input-app" value="<?= $mes_fim_default ?>" onchange="carregarDados()">
                </div>
                <div class="col-md-2">
                    <button onclick="carregarDados()" class="btn btn-dark w-100 fw-bold py-2" style="border-radius: 12px; height: 45px;"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
            </div>
        </div>

        <div id="boxMeta" class="card-app mb-4 bg-light border-0 d-none">
            <div class="d-flex justify-content-between align-items-end mb-2">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-bullseye me-2 text-danger"></i>Meta Acumulada</h6>
                <span class="meta-info" id="txtMetaStatus">...</span>
            </div>
            <div class="progress progress-thick">
                <div id="barMeta" class="progress-bar" role="progressbar" style="width: 0%; transition: 1s;"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 small text-muted">
                <span>Gasto Total: <strong id="txtMetaGasto" class="text-dark">R$ 0,00</strong></span>
                <span>Meta Total: <strong id="txtMetaTotal">R$ 0,00</strong></span>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-4">
                <div class="card-app p-3 d-flex flex-column align-items-center text-center">
                    <div class="icon-kpi bg-primary bg-opacity-10 text-primary"><i class="bi bi-wallet2"></i></div>
                    <div class="kpi-label">Total Gasto</div>
                    <div class="kpi-value" id="kpiTotal">...</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card-app p-3 d-flex flex-column align-items-center text-center">
                    <div class="icon-kpi bg-success bg-opacity-10 text-success"><i class="bi bi-cart-check"></i></div>
                    <div class="kpi-label">Lançamentos</div>
                    <div class="kpi-value" id="kpiQtd">...</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card-app p-3 d-flex flex-column align-items-center text-center">
                    <div class="icon-kpi bg-warning bg-opacity-10 text-warning"><i class="bi bi-calculator"></i></div>
                    <div class="kpi-label">Média / Compra</div>
                    <div class="kpi-value" id="kpiMedia">...</div>
                </div>
            </div>
        </div>

        <div class="card-app mb-4 position-relative">
            <div class="d-flex align-items-center mb-4">
                <h6 class="fw-bold text-dark m-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Histórico: Gasto vs Meta</h6>
            </div>
            <div style="height: 300px;">
                <canvas id="chartEvolucao"></canvas>
            </div>
            <div id="loaderMain" class="chart-overlay"><div class="spinner-border text-primary"></div></div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card-app position-relative">
                    <h6 class="fw-bold text-dark mb-4 ms-1">Dias da Semana</h6>
                    <div style="height: 250px;"><canvas id="chartSemana"></canvas></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-app position-relative">
                    <h6 class="fw-bold text-dark mb-4 ms-1">Momentos do Mês</h6>
                    <div style="height: 250px;"><canvas id="chartMesSemana"></canvas></div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($todas_categorias)): ?>

    let chartEv = null, chartDay = null, chartWeek = null;

    function carregarDados() {
        const catId = document.getElementById('selectCategoria').value;
        const ini = document.getElementById('evoInicio').value;
        const fim = document.getElementById('evoFim').value;
        if(!catId || !ini || !fim) return;

        document.getElementById('loaderMain').style.display = 'flex';
        // Feedback visual
        document.querySelectorAll('.kpi-value').forEach(e => e.style.opacity = 0.5);

        const fd = new FormData();
        fd.append('acao', 'evolucao_categoria');
        fd.append('categoria_id', catId);
        fd.append('mes_inicio', ini);
        fd.append('mes_fim', fim);

        fetch('ajax_analise.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(json => {
            if(json.status !== 'success') throw new Error(json.message);

            // KPIs e Barra
            document.getElementById('kpiTotal').innerText = "R$ " + json.kpi.total;
            document.getElementById('kpiQtd').innerText = json.kpi.qtd;
            document.getElementById('kpiMedia').innerText = "R$ " + json.kpi.media;
            document.querySelectorAll('.kpi-value').forEach(e => e.style.opacity = 1);
            
            atualizarMeta(json.meta, json.kpi.total);

            // Gráficos (Passa os arrays de meta e gasto)
            renderEvolucao(json.evolucao.labels, json.evolucao.gasto, json.evolucao.meta);
            renderDiasSemana(json.semana);
            renderSemanaMes(json.mes_semanas);
        })
        .catch(err => { console.error(err); alert("Erro: " + err.message); })
        .finally(() => { document.getElementById('loaderMain').style.display = 'none'; });
    }

    function atualizarMeta(meta, gastoFormatado) {
        const box = document.getElementById('boxMeta');
        const bar = document.getElementById('barMeta');
        const txtStatus = document.getElementById('txtMetaStatus');
        
        if(!meta.tem_meta) { box.classList.add('d-none'); return; }
        
        box.classList.remove('d-none');
        document.getElementById('txtMetaGasto').innerText = "R$ " + gastoFormatado;
        document.getElementById('txtMetaTotal').innerText = "R$ " + meta.total_periodo;
        
        let perc = meta.perc;
        bar.style.width = Math.min(perc, 100) + "%";
        bar.className = 'progress-bar ' + (perc > 100 ? 'bg-danger' : (perc > 75 ? 'bg-warning' : 'bg-success'));
        
        txtStatus.innerHTML = perc > 100 
            ? `<span class="text-danger fw-bold">Estourou (${perc.toFixed(0)}%)</span>` 
            : `<span class="text-success fw-bold">${perc.toFixed(0)}% Utilizado</span>`;
    }

    // --- GRÁFICO 1: EVOLUÇÃO COM LINHA DE META VERMELHA ---
    function renderEvolucao(labels, dataGasto, dataMeta) {
        const ctx = document.getElementById('chartEvolucao').getContext('2d');
        if(chartEv) chartEv.destroy();

        let grad = ctx.createLinearGradient(0,0,0,300);
        grad.addColorStop(0, 'rgba(67, 97, 238, 0.4)');
        grad.addColorStop(1, 'rgba(67, 97, 238, 0.0)');

        // Verifica se existe alguma meta > 0 para decidir se exibe a linha
        const temMeta = dataMeta.some(v => v > 0);

        const metaDataset = temMeta ? [{
            label: 'Meta Mensal',
            data: dataMeta, // Array com a meta de cada mês
            borderColor: '#ef4444', // Vermelho
            borderWidth: 2,
            borderDash: [6, 4],     // Tracejado
            pointRadius: 3,         // Bolinha pequena
            pointBackgroundColor: '#ef4444',
            fill: false,
            tension: 0.1 // Leve curvatura se a meta variar, ou reta se for fixa
        }] : [];

        chartEv = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Gasto Real',
                        data: dataGasto,
                        borderColor: '#4361ee',
                        backgroundColor: grad,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#4361ee'
                    },
                    ...metaDataset
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: temMeta } }, 
                scales: { 
                    y: { beginAtZero: true, grid: { borderDash: [5,5] }, ticks: { callback: v=>'R$ '+v } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderDiasSemana(d) {
        const ctx = document.getElementById('chartSemana').getContext('2d');
        if(chartDay) chartDay.destroy();
        chartDay = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                datasets: [{ label: 'Valor', data: d, backgroundColor: '#e2e8f0', hoverBackgroundColor: '#3b82f6', borderRadius: 6 }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false } } } }
        });
    }

    function renderSemanaMes(d) {
        const ctx = document.getElementById('chartMesSemana').getContext('2d');
        if(chartWeek) chartWeek.destroy();
        chartWeek = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Sem 1','Sem 2','Sem 3','Sem 4','Sem 5'],
                datasets: [{ data: d, backgroundColor: ['#4cc9f0','#4361ee','#3a0ca3','#7209b7','#f72585'], borderWidth: 0 }]
            },
            options: { cutout: '70%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true } } } }
        });
    }

    document.addEventListener('DOMContentLoaded', () => { setTimeout(carregarDados, 200); });

<?php endif; ?>
</script>

<?php require_once "../includes/footer.php"; ?>