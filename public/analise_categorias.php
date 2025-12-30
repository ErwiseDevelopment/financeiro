<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];

// Datas padrão
$mes_inicio_default = date('Y-m', strtotime('-5 months'));
$mes_fim_default = date('Y-m');

// Lista de Categorias
$stmt = $pdo->prepare("SELECT categoriaid, categoriadescricao FROM categorias WHERE usuarioid = ? AND (categoriatipo = 'Despesa') ORDER BY categoriadescricao ASC");
$stmt->execute([$uid]);
$todas_categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
    
    .app-container { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }
    
    /* Cards */
    .card-app { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.04); height: 100%; transition: transform 0.2s; }
    .card-app:hover { transform: translateY(-2px); }
    
    /* Inputs */
    .label-app { font-size: 0.7rem; font-weight: 800; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .input-app { background-color: #f8fafc; border: 2px solid #e2e8f0; padding: 10px 15px; border-radius: 12px; font-weight: 600; color: #334155; width: 100%; transition: 0.2s; }
    .input-app:focus { outline: none; background-color: #fff; border-color: #4361ee; }

    /* KPIs */
    .kpi-value { font-size: 1.5rem; font-weight: 800; color: #1e293b; line-height: 1; }
    .kpi-label { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
    .icon-kpi { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 10px; }

    /* Overlay */
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
        <div class="text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted">Cadastre categorias de despesa para começar.</p>
        </div>
    <?php else: ?>

        <div class="card-app mb-4 bg-white">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="label-app">Categoria Analisada</label>
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
                    <button onclick="carregarDados()" class="btn btn-dark w-100 fw-bold py-2" style="border-radius: 12px; height: 45px;">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
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
            <h6 class="fw-bold text-dark mb-4 ms-1"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Evolução Mensal</h6>
            <div style="height: 300px;">
                <canvas id="chartEvolucao"></canvas>
            </div>
            <div id="loaderMain" class="chart-overlay"><div class="spinner-border text-primary"></div></div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card-app position-relative">
                    <h6 class="fw-bold text-dark mb-4 ms-1"><i class="bi bi-calendar-day me-2 text-primary"></i>Gastos por Dia da Semana</h6>
                    <div style="height: 250px;">
                        <canvas id="chartSemana"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-app position-relative">
                    <h6 class="fw-bold text-dark mb-4 ms-1"><i class="bi bi-pie-chart me-2 text-primary"></i>Concentração no Mês</h6>
                    <div style="height: 250px;">
                        <canvas id="chartMesSemana"></canvas>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($todas_categorias)): ?>

    // Instâncias Globais dos Gráficos
    let chartEv = null;
    let chartDay = null;
    let chartWeek = null;

    function carregarDados() {
        const catId = document.getElementById('selectCategoria').value;
        const ini = document.getElementById('evoInicio').value;
        const fim = document.getElementById('evoFim').value;

        if(!catId || !ini || !fim) return;

        // UI Loading
        document.getElementById('loaderMain').style.display = 'flex';
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

            // 1. Atualiza KPIs
            document.getElementById('kpiTotal').innerText = "R$ " + json.kpi.total;
            document.getElementById('kpiQtd').innerText = json.kpi.qtd;
            document.getElementById('kpiMedia').innerText = "R$ " + json.kpi.media;
            document.querySelectorAll('.kpi-value').forEach(e => e.style.opacity = 1);

            // 2. Renderiza Gráficos
            renderEvolucao(json.evolucao.labels, json.evolucao.data);
            renderDiasSemana(json.semana);
            renderSemanaMes(json.mes_semanas);

        })
        .catch(err => {
            console.error(err);
            alert("Erro ao carregar: " + err.message);
        })
        .finally(() => {
            document.getElementById('loaderMain').style.display = 'none';
        });
    }

    // --- GRÁFICO 1: EVOLUÇÃO (LINHA) ---
    function renderEvolucao(labels, data) {
        const ctx = document.getElementById('chartEvolucao').getContext('2d');
        if(chartEv) chartEv.destroy();

        let grad = ctx.createLinearGradient(0,0,0,300);
        grad.addColorStop(0, 'rgba(67, 97, 238, 0.4)');
        grad.addColorStop(1, 'rgba(67, 97, 238, 0.0)');

        chartEv = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Valor (R$)',
                    data: data,
                    borderColor: '#4361ee',
                    backgroundColor: grad,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#4361ee'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { borderDash: [5,5] }, ticks: { callback: v=>'R$ '+v } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // --- GRÁFICO 2: DIAS DA SEMANA (BARRA) ---
    function renderDiasSemana(dataValues) {
        const ctx = document.getElementById('chartSemana').getContext('2d');
        if(chartDay) chartDay.destroy();

        chartDay = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                datasets: [{
                    label: 'Total Gasto',
                    data: dataValues,
                    backgroundColor: [
                        '#e2e8f0', '#3b82f6', '#3b82f6', '#3b82f6', '#3b82f6', '#3b82f6', '#e2e8f0'
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { display: false },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // --- GRÁFICO 3: SEMANAS DO MÊS (RADAR OU BARRA) ---
    function renderSemanaMes(dataValues) {
        const ctx = document.getElementById('chartMesSemana').getContext('2d');
        if(chartWeek) chartWeek.destroy();

        chartWeek = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4', 'Semana 5'],
                datasets: [{
                    data: dataValues,
                    backgroundColor: ['#4cc9f0', '#4361ee', '#3a0ca3', '#7209b7', '#f72585'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, usePointStyle: true } }
                }
            }
        });
    }

    // Inicialização
    document.addEventListener('DOMContentLoaded', () => { setTimeout(carregarDados, 200); });

<?php endif; ?>
</script>

<?php require_once "../includes/footer.php"; ?>