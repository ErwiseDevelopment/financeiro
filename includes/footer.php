
<div class="fab-wrapper" id="menuFlutuante">
    <button class="fab-main shadow-lg" onclick="toggleFabMenu()">
        <i class="bi bi-plus-lg"></i>
    </button>
    <div class="fab-list">
        <a href="index.php" class="fab-item" data-label="Início"><i class="bi bi-house-door text-primary"></i></a>
        <a href="categorias.php" class="fab-item" data-label="Categorias"><i class="bi bi-tags text-warning"></i></a>
        <a href="cadastro_conta.php" class="fab-item" data-label="Nova Conta"><i class="bi bi-plus-circle text-success"></i></a>
        <a href="dashboard.php" class="fab-item" data-label="Dashboards">    <i class="bi bi-pie-chart text-info"></i></a>
        <a href="fluxo_caixa.php" class="fab-item" data-label="Fluxo de Caixa"> <i class="bi bi-arrow-left-right text-indigo" style="color: #6366f1;"></i>
        </a>
    </div>
</div>

<script>
function toggleFabMenu() { document.getElementById('menuFlutuante').classList.toggle('active'); }
document.addEventListener('click', function(e) {
    const menu = document.getElementById('menuFlutuante');
    if (!menu.contains(e.target)) menu.classList.remove('active');
});
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        console.log("MyFinance Pro carregado com sucesso.");
    </script>
</body>
</html>