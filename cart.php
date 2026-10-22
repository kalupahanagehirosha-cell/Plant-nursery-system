<?php
/*
 * FILE: cart.php (Shopping Cart)
 * WHAT IT DOES: Shows all items in the customer's shopping cart including both
 *               plants and landscaping services. Customers can select/deselect items,
 *               change plant quantities, remove items, and see the total price before checkout.
 */
// Include database config and require the user to be logged in
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

/* ============================================================
 * FETCH CART ITEMS - Plants and Landscaping Services
 * ============================================================ */
// Fetch plant cart items with details
$stmt = $pdo->prepare("SELECT ci.*, p.name, p.price, p.image, p.stock_quantity 
    FROM cart_items ci JOIN plants p ON ci.plant_id = p.id 
    WHERE ci.user_id = ? ORDER BY ci.added_at DESC");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// Fetch service cart items with details
$service_items = [];
try {
    $svc_stmt = $pdo->prepare("SELECT sci.*, ls.name as service_name, ls.base_price, ls.image as service_image, ls.duration_hours
        FROM service_cart_items sci JOIN landscaping_services ls ON sci.service_id = ls.id 
        WHERE sci.user_id = ? ORDER BY sci.added_at DESC");
    $svc_stmt->execute([$user_id]);
    $service_items = $svc_stmt->fetchAll();
} catch (PDOException $e) {}

// Check if cart is completely empty
$cart_empty = empty($cart_items) && empty($service_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - LIYA SEWANA</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ===== Cart Selection Checkbox ===== */
        .cart-item { position:relative; }
        .cart-item.selected { border:2px solid var(--primary); background:#f0faf4; }
        .cart-select { position:absolute; top:15px; right:15px; width:22px; height:22px; accent-color:var(--primary); cursor:pointer; }
        
        /* ===== Quantity Controls ===== */
        .qty-box { display:flex; align-items:center; gap:0; border:2px solid #e0e0e0; border-radius:8px; overflow:hidden; }
        .qty-btn { width:36px; height:36px; border:none; background:var(--light); color:var(--primary-dark); font-size:1.2rem; font-weight:700; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; justify-content:center; }
        .qty-btn:hover { background:var(--primary); color:white; }
        .qty-btn:disabled { opacity:0.4; cursor:not-allowed; }
        .qty-val { width:45px; text-align:center; font-size:1rem; font-weight:700; border:none; border-left:1px solid #e0e0e0; border-right:1px solid #e0e0e0; background:white; }
        
        /* ===== Bottom Cart Summary (Sticky) ===== */
        .cart-bottom { position:fixed; bottom:0; left:0; right:0; background:white; box-shadow:0 -4px 20px rgba(0,0,0,0.1); padding:15px 2rem; z-index:900; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
        .cart-bottom .select-all-area { display:flex; align-items:center; gap:8px; }
        .cart-bottom .total-area { display:flex; align-items:center; gap:20px; }
        .cart-bottom .total-price { font-size:1.3rem; font-weight:800; color:var(--primary-dark); }
        
        /* ===== Body padding for sticky bottom bar ====== */
        .cart-page-body { padding-bottom:100px; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main>
    <section class="section cart-page-body">
        <div class="section-title">
            <h2>🛒 My Shopping Cart</h2>
            <div class="underline"></div>
        </div>

        <?php if ($cart_empty): ?>
            <!-- Empty Cart State -->
            <div class="empty-state">
                <div class="icon">🛒</div>
                <h3>Your cart is empty</h3>
                <p>Browse our plant collection or landscaping services and add items to your cart</p>
                <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                    <a href="plants.php" class="btn btn-primary">🌱 Browse Plants</a>
                    <a href="landscaping.php" class="btn btn-outline-green">🏡 Landscaping Services</a>
                </div>
            </div>
        <?php else: ?>

            <!-- Cart Items List -->
            <div class="cart-container" id="cartList">
                <?php if (!empty($cart_items)): ?>
                <!-- Plant Items Section -->
                <div style="margin-bottom:1.5rem;">
                    <h3 style="color:var(--primary-dark); margin-bottom:0.8rem; font-size:1.1rem;">🌱 Plants</h3>
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item" id="cartItem_<?php echo $item['id']; ?>" data-type="plant" data-id="<?php echo $item['id']; ?>" data-price="<?php echo $item['price']; ?>">
                        
                        <!-- Selection Checkbox -->
                        <input type="checkbox" class="cart-select item-check" 
                               data-type="plant"
                               value="<?php echo $item['id']; ?>" 
                               onchange="updateSelection()"
                               <?php echo $item['stock_quantity'] > 0 ? 'checked' : 'disabled'; ?>>

                        <!-- Plant Image -->
                        <div class="cart-img">
                            <?php if ($item['image'] && file_exists(__DIR__ . '/' . $item['image'])): ?>
                                <img src="<?php echo $item['image']; ?>" alt="<?php echo sanitize($item['name']); ?>">
                            <?php else: ?>
                                <div style="font-size:2rem; display:flex; align-items:center; justify-content:center; height:100%;">🌱</div>
                            <?php endif; ?>
                        </div>

                        <!-- Plant Info -->
                        <div class="cart-info" style="flex:1;">
                            <h4 style="color:var(--primary-dark); margin-bottom:4px;"><?php echo sanitize($item['name']); ?></h4>
                            <p style="color:var(--gray); font-size:0.85rem; margin-bottom:4px;">
                                <?php echo formatCurrency($item['price']); ?> each
                            </p>
                            <?php if ($item['stock_quantity'] <= 0): ?>
                                <p style="color:var(--danger); font-size:0.8rem; font-weight:600;">❌ Out of stock</p>
                            <?php elseif ($item['stock_quantity'] < 5): ?>
                                <p style="color:#856404; font-size:0.8rem;">⚠️ Only <?php echo $item['stock_quantity']; ?> left</p>
                            <?php else: ?>
                                <p style="color:var(--success); font-size:0.8rem;">✓ In stock (<?php echo $item['stock_quantity']; ?>)</p>
                            <?php endif; ?>
                        </div>

                        <!-- Quantity Controls: - / count / + -->
                        <div class="qty-box">
                            <button class="qty-btn" onclick="changeQty(<?php echo $item['id']; ?>, -1)" <?php echo $item['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>−</button>
                            <input type="text" class="qty-val" id="qty_<?php echo $item['id']; ?>" value="<?php echo $item['quantity']; ?>" readonly>
                            <button class="qty-btn" onclick="changeQty(<?php echo $item['id']; ?>, 1)" <?php echo $item['quantity'] >= $item['stock_quantity'] ? 'disabled' : ''; ?>>+</button>
                        </div>

                        <!-- Line Total Price -->
                        <div style="min-width:110px; text-align:right;">
                            <div class="cart-price" id="price_<?php echo $item['id']; ?>" style="font-size:1.1rem; color:var(--primary); font-weight:700;">
                                <?php echo formatCurrency($item['price'] * $item['quantity']); ?>
                            </div>
                            <button onclick="removeItem(<?php echo $item['id']; ?>)" style="background:none; border:none; color:var(--danger); font-size:0.8rem; cursor:pointer; margin-top:4px;">✕ Remove</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($service_items)): ?>
                <!-- Service Items Section -->
                <div>
                    <h3 style="color:var(--primary-dark); margin-bottom:0.8rem; font-size:1.1rem;">🏡 Landscaping Services</h3>
                <?php foreach ($service_items as $svc): ?>
                    <div class="cart-item" id="svcCartItem_<?php echo $svc['id']; ?>" data-type="service" data-id="<?php echo $svc['id']; ?>" data-price="<?php echo $svc['base_price']; ?>" style="border-left:4px solid var(--secondary);">
                        
                        <!-- Selection Checkbox -->
                        <input type="checkbox" class="cart-select item-check" 
                               data-type="service"
                               value="<?php echo $svc['id']; ?>" 
                               onchange="updateSelection()" checked>

                        <!-- Service Image -->
                        <div class="cart-img">
                            <?php if ($svc['service_image'] && file_exists(__DIR__ . '/' . $svc['service_image'])): ?>
                                <img src="<?php echo $svc['service_image']; ?>" alt="<?php echo sanitize($svc['service_name']); ?>">
                            <?php else: ?>
                                <div style="font-size:2rem; display:flex; align-items:center; justify-content:center; height:100%;">🏡</div>
                            <?php endif; ?>
                        </div>

                        <!-- Service Info -->
                        <div class="cart-info" style="flex:1;">
                            <h4 style="color:var(--primary-dark); margin-bottom:4px;"><?php echo sanitize($svc['service_name']); ?></h4>
                            <p style="color:var(--primary); font-size:0.9rem; font-weight:600; margin-bottom:4px;">
                                <?php echo formatCurrency($svc['base_price']); ?>
                            </p>
                            <p style="color:var(--gray); font-size:0.8rem;">
                                📅 <?php echo date('M d, Y', strtotime($svc['booking_date'])); ?> | ⏰ <?php echo $svc['time_slot']; ?>
                            </p>
                            <p style="color:var(--gray); font-size:0.8rem;">📍 <?php echo sanitize($svc['location']); ?></p>
                            <?php if ($svc['property_size']): ?>
                                <p style="color:var(--gray); font-size:0.8rem;">📐 <?php echo sanitize($svc['property_size']); ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Line Total Price -->
                        <div style="min-width:110px; text-align:right;">
                            <div class="cart-price" style="font-size:1.1rem; color:var(--primary); font-weight:700;">
                                <?php echo formatCurrency($svc['base_price']); ?>
                            </div>
                            <button onclick="removeServiceItem(<?php echo $svc['id']; ?>)" style="background:none; border:none; color:var(--danger); font-size:0.8rem; cursor:pointer; margin-top:4px;">✕ Remove</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sticky Bottom Bar: Select All + Total + Place Order Button -->
            <div class="cart-bottom">
                <div class="select-all-area">
                    <input type="checkbox" id="selectAll" checked onchange="toggleSelectAll()" style="width:20px; height:20px; accent-color:var(--primary);">
                    <label for="selectAll" style="font-weight:600; cursor:pointer;">Select All</label>
                    <span style="color:var(--gray); font-size:0.85rem; margin-left:5px;" id="selectedCount"></span>
                </div>
                <div class="total-area">
                    <div>
                        <span style="color:var(--gray); font-size:0.85rem;">Total:</span>
                        <span class="total-price" id="selectedTotal">Rs. 0.00</span>
                        <span id="freeDeliveryMsg" style="display:none; color:var(--success); font-size:0.75rem; font-weight:600; margin-left:5px;">🚚 FREE Delivery</span>
                    </div>
                    <button class="btn btn-primary btn-lg" id="placeOrderBtn" onclick="goToCheckout()" disabled>
                        Place Order
                    </button>
                </div>
            </div>

        <?php endif; ?>
    </section>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="js/script.js"></script>
    <script>
    /* ============================================================
     * CART STATE MANAGEMENT
     * Track selected items and quantities for order placement
     * Supports both plant items and service items
     * ============================================================ */
    
    /* Build cart data from server-rendered elements */
    let cartData = {};
    let serviceData = {};
    
    document.querySelectorAll('.cart-item[data-type="plant"]').forEach(item => {
        let id = item.dataset.id;
        let price = parseFloat(item.dataset.price);
        let qty = parseInt(document.getElementById('qty_' + id).value);
        cartData[id] = { price, qty, checked: true };
    });
    
    document.querySelectorAll('.cart-item[data-type="service"]').forEach(item => {
        let id = item.dataset.id;
        let price = parseFloat(item.dataset.price);
        serviceData[id] = { price, checked: true };
    });

    /* ============================================================
     * SELECT / DESELECT individual cart item
     * Updates totals and button state
     * ============================================================ */
    function updateSelection() {
        let checks = document.querySelectorAll('.item-check');
        let allChecked = true;
        let count = 0;
        
        checks.forEach(c => {
            let type = c.dataset.type;
            let id = c.value;
            let card = document.getElementById(type === 'service' ? 'svcCartItem_' + id : 'cartItem_' + id);
            if (c.checked) {
                card.classList.add('selected');
                count++;
            } else {
                card.classList.remove('selected');
                allChecked = false;
            }
            if (type === 'plant') cartData[id].checked = c.checked;
            else serviceData[id].checked = c.checked;
        });

        /* Update select all checkbox state */
        document.getElementById('selectAll').checked = allChecked;
        recalculateTotal();
    }

    /* ============================================================
     * SELECT / DESELECT ALL items at once
     * ============================================================ */
    function toggleSelectAll() {
        let checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.item-check').forEach(c => {
            if (!c.disabled) {
                c.checked = checked;
                let type = c.dataset.type;
                let id = c.value;
                let card = document.getElementById(type === 'service' ? 'svcCartItem_' + id : 'cartItem_' + id);
                card.classList.toggle('selected', checked);
                if (type === 'plant') cartData[id].checked = checked;
                else serviceData[id].checked = checked;
            }
        });
        recalculateTotal();
    }

    /* ============================================================
     * CHANGE QUANTITY (+/- buttons) for plant items
     * Updates quantity, price display, and total
     * ============================================================ */
    function changeQty(id, delta) {
        let current = parseInt(document.getElementById('qty_' + id).value);
        let newQty = current + delta;
        
        /* Don't go below 1 */
        if (newQty < 1) return;

        /* Send AJAX to update on server */
        fetch('ajax/update_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `cart_item_id=${id}&quantity=${newQty}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                /* Update local state */
                cartData[id].qty = newQty;
                document.getElementById('qty_' + id).value = newQty;
                
                /* Update line item price */
                let linePrice = cartData[id].price * newQty;
                document.getElementById('price_' + id).textContent = 'Rs. ' + linePrice.toFixed(2);
                
                /* Recalculate grand total */
                recalculateTotal();
            }
        });
    }

    /* ============================================================
     * REMOVE plant item from cart
     * ============================================================ */
    function removeItem(id) {
        if (!confirm('Remove this item from cart?')) return;
        
        fetch('ajax/remove_from_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `cart_item_id=${id}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
        });
    }

    /* ============================================================
     * REMOVE service item from cart
     * ============================================================ */
    function removeServiceItem(id) {
        if (!confirm('Remove this service from cart?')) return;
        
        fetch('ajax/remove_service_from_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `service_cart_item_id=${id}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
        });
    }

    /* ============================================================
     * RECALCULATE TOTAL for selected items
     * Includes both plant and service items
     * ============================================================ */
    function recalculateTotal() {
        let total = 0;
        let count = 0;
        let hasSelected = false;

        /* Add plant item totals */
        for (let id in cartData) {
            if (cartData[id].checked) {
                total += cartData[id].price * cartData[id].qty;
                count++;
                hasSelected = true;
            }
        }
        
        /* Add service item totals */
        for (let id in serviceData) {
            if (serviceData[id].checked) {
                total += serviceData[id].price;
                count++;
                hasSelected = true;
            }
        }

        /* Add delivery fee only if there are plant items selected (under Rs.20000) */
        let plantTotal = 0;
        for (let id in cartData) {
            if (cartData[id].checked) {
                plantTotal += cartData[id].price * cartData[id].qty;
            }
        }
        let deliveryFee = plantTotal > 0 && plantTotal <= 20000 ? 500 : 0;
        let grandTotal = total + deliveryFee;

        /* Show free delivery message when plant total exceeds Rs.20,000 */
        let freeMsg = document.getElementById('freeDeliveryMsg');
        if (freeMsg) {
            freeMsg.style.display = (plantTotal >= 20000) ? 'inline' : 'none';
        }

        /* Update display elements */
        document.getElementById('selectedTotal').textContent = 'Rs. ' + grandTotal.toFixed(2);
        document.getElementById('selectedCount').textContent = hasSelected ? `(${count} item${count > 1 ? 's' : ''} selected)` : '';
        document.getElementById('placeOrderBtn').disabled = !hasSelected;
    }

    /* ============================================================
     * GO TO CHECKOUT
     * Stores selected items and redirects to checkout page
     * ============================================================ */
    function goToCheckout() {
        let selectedPlantIds = [];
        let selectedServiceIds = [];
        
        for (let id in cartData) {
            if (cartData[id].checked) {
                selectedPlantIds.push(id);
            }
        }
        for (let id in serviceData) {
            if (serviceData[id].checked) {
                selectedServiceIds.push(id);
            }
        }
        
        if (selectedPlantIds.length === 0 && selectedServiceIds.length === 0) {
            showAlert('Please select at least one item', 'danger');
            return;
        }
        
        /* Store selected IDs in hidden form and submit */
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = 'checkout.php?step=1';
        
        let plantInput = document.createElement('input');
        plantInput.type = 'hidden';
        plantInput.name = 'selected_items';
        plantInput.value = selectedPlantIds.join(',');
        form.appendChild(plantInput);
        
        let svcInput = document.createElement('input');
        svcInput.type = 'hidden';
        svcInput.name = 'selected_service_items';
        svcInput.value = selectedServiceIds.join(',');
        form.appendChild(svcInput);
        
        document.body.appendChild(form);
        form.submit();
    }

    /* Initial calculation on page load */
    recalculateTotal();
    </script>
</body>
</html>
