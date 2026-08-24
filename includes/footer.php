  </main>
</div>

<!-- BottomNavBar (Mobile Only) -->
<nav class="fixed bottom-0 w-full z-50 md:hidden bg-surface border-t border-outline-variant flex justify-around items-center h-16 pb-safe px-2">
  <a href="index.php" class="flex flex-col items-center justify-center <?= $page_active==='dashboard'?'text-primary font-bold':'text-on-surface-variant' ?> hover:bg-surface-container-highest active:scale-95 transition-transform duration-100 px-4 py-1 rounded-full">
    <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
    <span class="text-xs mt-1">Dashboard</span>
  </a>
  <a href="lots.php" class="flex flex-col items-center justify-center <?= $page_active==='carcasse'?'text-primary font-bold':'text-on-surface-variant' ?> hover:bg-surface-container-highest active:scale-95 transition-transform duration-100 px-4 py-1 rounded-full">
    <span class="material-symbols-outlined" data-icon="qr_code_scanner">qr_code_scanner</span>
    <span class="text-xs mt-1">Carcasse</span>
  </a>
  <a href="stocks.php" class="flex flex-col items-center justify-center <?= $page_active==='stocks'?'text-primary font-bold':'text-on-surface-variant' ?> hover:bg-surface-container-highest active:scale-95 transition-transform duration-100 px-4 py-1 rounded-full">
    <span class="material-symbols-outlined" data-icon="inventory_2">inventory_2</span>
    <span class="text-xs mt-1">Stocks</span>
  </a>
  <a href="traiteur.php" class="flex flex-col items-center justify-center <?= $page_active==='plats'?'text-primary font-bold':'text-on-surface-variant' ?> hover:bg-surface-container-highest active:scale-95 transition-transform duration-100 px-4 py-1 rounded-full">
    <span class="material-symbols-outlined" data-icon="restaurant_menu">restaurant_menu</span>
    <span class="text-xs mt-1">Plats</span>
  </a>
</nav>

</body></html>
