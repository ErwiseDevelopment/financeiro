<div class="fab-wrapper" id="menuFlutuante">
    <button class="fab-main shadow-lg" onclick="toggleFabMenu()" aria-label="Abrir Menu">
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

        <a href="dashboard.php" class="fab-item" data-label="Dashboards">
            <i class="bi bi-pie-chart text-info"></i>
        </a>

        <a href="perfil.php" class="fab-item" data-label="Meu Perfil">
            <i class="bi bi-person-gear" style="color: #f59e0b;"></i>
        </a>

        <a href="logout.php" class="fab-item" data-label="Sair">
            <i class="bi bi-box-arrow-right text-danger"></i>
        </a>
    </div>
</div>



<footer class="py-4 mt-5 border-top bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0" style="color: #a0aec0; font-size: 0.85rem;">
                    &copy; <?= date('Y') ?> 
                    Desenvolvido por <strong style="color: #718096;"><a href="https://erwise.com.br" target="_blank" style="color: inherit; text-decoration: none;">erwise.com.br</a></strong>
                </p>
            </div>
            
            <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                <a href="https://www.instagram.com/erwisedev" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm me-2" title="Instagram">
                    <i class="bi bi-instagram text-danger"></i>
                </a>
                <a href="https://api.whatsapp.com/send?phone=5511934008521&text=Olha%20meu%20financeiro!" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm me-2" title="WhatsApp">
                    <i class="bi bi-whatsapp text-success"></i>
                </a>
                <a href="https://erwise.com.br" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Website">
                    <i class="bi bi-globe2 text-primary"></i>
                </a>
            </div>
        </div>
    </div>
</footer>

<style>
.fab-wrapper { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; flex-direction: column-reverse; align-items: flex-end; }
.fab-main { width: 60px; height: 60px; border-radius: 50%; background: #0d6efd; border: none; color: white; font-size: 24px; transition: 0.3s; z-index: 2; }
.fab-wrapper.active .fab-main { transform: rotate(45deg); background: #dc3545; }
.fab-list { margin-bottom: 20px; display: none; flex-direction: column-reverse; gap: 15px; align-items: flex-end; transition: 0.3s; }
.fab-wrapper.active .fab-list { display: flex; }
.fab-item { width: 45px; height: 45px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); text-decoration: none; position: relative; transition: 0.2s; }
.fab-item:hover { transform: scale(1.1); }
.fab-item::after { content: attr(data-label); position: absolute; right: 55px; background: rgba(0,0,0,0.7); color: white; padding: 4px 10px; border-radius: 6px; font-size: 12px; white-space: nowrap; opacity: 0; pointer-events: none; transition: 0.2s; }
.fab-item:hover::after { opacity: 1; }
.fab-item i { font-size: 20px; }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFabMenu() { document.getElementById('menuFlutuante').classList.toggle('active'); }
// Fechar ao clicar fora
document.addEventListener('click', function(e) {
    const menu = document.getElementById('menuFlutuante');
    if (menu && !menu.contains(e.target)) menu.classList.remove('active');
});
</script>