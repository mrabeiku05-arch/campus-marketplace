<?php
require_once 'includes/header.php';
?>

<h2 style="font-size:1.8rem; font-weight:700; margin-bottom:1.5rem; letter-spacing:-0.02em;">Your Wishlist</h2>

<div class="glass" style="padding:2rem;">
    <div id="wishlist-items" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:1.5rem;"></div>
    <div id="wishlist-empty" style="text-align:center; padding:3rem; display:none;">
        <p style="font-size:1.2rem; color:var(--text-muted); margin-bottom:1rem;">Your wishlist is empty</p>
        <a href="index.php" class="btn btn-primary">Browse Products</a>
    </div>
</div>

<style>
    .wishlist-card { background:rgb(255,255,255); border:1px solid rgba(0,0,0,0.08); border-radius:16px; overflow:hidden; transition:all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); cursor:pointer; box-shadow:0 4px 16px rgba(0,0,0,0.04); }
    .wishlist-card:hover { border-color:rgba(124,58,237,0.3); transform:translateY(-6px); box-shadow:0 16px 40px rgba(0,0,0,0.08), 0 0 20px rgba(124,58,237,0.15); }
    .wishlist-card-img-wrap { overflow:hidden; }
    .wishlist-card img { width:100%; height:160px; object-fit:cover; transition:transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1); display:block; }
    .wishlist-card:hover img { transform:scale(1.08); }
    .wishlist-card-body { padding:1.25rem; }
    .wishlist-remove { background:rgba(255,59,48,0.1); border:1px solid rgba(255,59,48,0.2); color:#ff3b30; padding:8px 14px; border-radius:8px; cursor:pointer; font-size:0.8rem; font-weight:600; transition:all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); width:100%; }
    .wishlist-remove:hover { background:rgba(255,59,48,0.2); transform:translateY(-2px); box-shadow:0 6px 16px rgba(255,59,48,0.15); }
    .wishlist-add-cart { background:#7c3aed; color:#fff; border:none; padding:8px 14px; border-radius:8px; cursor:pointer; font-size:0.8rem; font-weight:600; width:100%; transition:all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
    .wishlist-add-cart:hover { background:#6d28d9; transform:translateY(-2px); box-shadow:0 6px 16px rgba(124,58,237,0.25); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function renderWishlist() {
        const list = cmWishlist.get();
        const container = document.getElementById('wishlist-items');
        const empty = document.getElementById('wishlist-empty');
        
        if (list.length === 0) {
            container.innerHTML = '';
            empty.style.display = 'block';
            return;
        }
        
        empty.style.display = 'none';
        container.innerHTML = list.map(item => `
            <div class="wishlist-card">
                <img src="${item.image || ''}" alt="${item.name}" onerror="this.style.background='rgba(0,0,0,0.3)'; this.style.display='flex';">
                <div class="wishlist-card-body">
                    <p style="font-weight:600; margin-bottom:4px;">${item.name}</p>
                    <p style="color:var(--primary); font-weight:700; font-size:1.1rem;">₵${parseFloat(item.price).toFixed(2)}</p>
                    <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.75rem;">
                        <button class="wishlist-add-cart" onclick="cmCart.add(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, '${item.image || ''}'); event.stopPropagation();">🛒 Add to Cart</button>
                        <button class="wishlist-remove" onclick="cmWishlist.toggle(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, '${item.image || ''}'); renderWishlist(); event.stopPropagation();">Remove</button>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    renderWishlist();
});
</script>

<?php require_once 'includes/footer.php'; ?>
