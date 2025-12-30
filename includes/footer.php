</main> 

<div class="fab-wrapper" id="menuFlutuante">
    <button class="fab-main shadow-lg d-lg-none" onclick="toggleFabMenu()">
        <i class="bi bi-plus-lg"></i>
    </button>

    <div class="fab-list">
        <a href="cadastro_conta.php" class="fab-item" data-label="Nova Conta">
            <i class="bi bi-plus-circle text-success"></i>
        </a>
        
        <a href="index.php" class="fab-item" data-label="Início">
            <i class="bi bi-house-door text-primary"></i>
        </a>
        
        <a href="fluxo_caixa.php" class="fab-item" data-label="Fluxo de Caixa">
            <i class="bi bi-arrow-left-right" style="color: #6366f1;"></i>
        </a>
        
        <a href="faturas.php" class="fab-item" data-label="Minhas Faturas">
            <i class="bi bi-receipt-cutoff" style="color: #8b5cf6;"></i>
        </a>
        
        <a href="cadastro_cartao.php" class="fab-item" data-label="Cartões de Crédito">
            <i class="bi bi-credit-card-2-back" style="color: #ec4899;"></i>
        </a>
        
        <a href="categorias.php" class="fab-item" data-label="Categorias">
            <i class="bi bi-tags text-warning"></i>
        </a>

        <a href="metas_mensais.php" class="fab-item" data-label="Metas Mensais">
            <i class="bi bi-bullseye text-danger"></i>
        </a>
        
        <a href="dashboard.php" class="fab-item" data-label="Dashboards">
            <i class="bi bi-pie-chart text-info"></i>
        </a>
        
        <a href="perfil.php" class="fab-item" data-label="Meu Perfil">
            <i class="bi bi-person-gear" style="color: #f59e0b;"></i>
        </a>
    </div>
</div>

<footer class="py-4 mt-5 border-top bg-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0 text-muted small">
                    &copy; <?= date('Y') ?> Desenvolvido por 
                    <a href="https://erwise.com.br" target="_blank" class="fw-bold text-decoration-none text-secondary">erwise.com.br</a>
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                <a href="#" class="btn btn-sm btn-light rounded-circle shadow-sm me-2"><i class="bi bi-instagram text-danger"></i></a>
                <a href="#" class="btn btn-sm btn-light rounded-circle shadow-sm me-2"><i class="bi bi-whatsapp text-success"></i></a>
                <a href="#" class="btn btn-sm btn-light rounded-circle shadow-sm"><i class="bi bi-globe2 text-primary"></i></a>
            </div>
        </div>
    </div>
</footer>

<style>
/* --- CONFIGURAÇÃO MENU FLUTUANTE (MOBILE) --- */
.fab-wrapper { 
    position: fixed; 
    bottom: 30px; 
    right: 30px; 
    z-index: 9999; 
    display: flex; 
    flex-direction: column-reverse; 
    align-items: flex-end; 
}

.fab-main { 
    width: 60px; height: 60px; border-radius: 50%; 
    background: var(--primary-color); border: none; color: white; 
    font-size: 24px; transition: 0.3s; z-index: 2; 
}

.fab-wrapper.active .fab-main { transform: rotate(45deg); background: #dc3545; }

.fab-list { 
    margin-bottom: 20px; display: none; 
    flex-direction: column-reverse; gap: 12px; align-items: flex-end; 
}

.fab-wrapper.active .fab-list { display: flex; }

.fab-item { 
    width: 48px; height: 48px; border-radius: 14px; 
    background: white; display: flex; align-items: center; justify-content: center; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-decoration: none; 
    transition: 0.2s; border: 1px solid #f1f5f9; 
}

.fab-item:hover { transform: scale(1.1); background: #f8fafc; }

.fab-item::after { 
    content: attr(data-label); position: absolute; right: 60px; 
    background: #1e293b; color: white; padding: 5px 12px; 
    border-radius: 8px; font-size: 12px; white-space: nowrap; 
    opacity: 0; pointer-events: none; transition: 0.2s; 
}
.fab-item:hover::after { opacity: 1; }

/* --- AJUSTE MENU FIXO (COMPUTADOR / DESKTOP) --- */
@media (min-width: 992px) {
    .fab-wrapper {
        bottom: 50%;
        transform: translateY(50%);
        right: 25px;
    }

    .fab-list {
        display: flex !important; /* Sempre aberto */
        background: #fff;
        padding: 12px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }

    .fab-item {
        width: 42px; height: 42px; border-radius: 10px;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFabMenu() { 
    if (window.innerWidth < 992) {
        document.getElementById('menuFlutuante').classList.toggle('active'); 
    }
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('menuFlutuante');
    if (window.innerWidth < 992 && menu && !menu.contains(e.target)) {
        menu.classList.remove('active');
    }
});
</script>
</body>
</html>