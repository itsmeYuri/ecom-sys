document.addEventListener('DOMContentLoaded', function () {
  const closeBtn = document.getElementById('promoClose');
  if (closeBtn) {
    closeBtn.addEventListener('click', async function () {
      try {
        await fetch('/shop-system/promo-dismiss.php', { method: 'POST' });
      } catch (e) {
        console.error(e);
      }
      const bar = document.getElementById('promoBar');
      if (bar) bar.remove();
    });
  }
});
