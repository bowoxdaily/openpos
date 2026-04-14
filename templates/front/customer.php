<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Self Checkout</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f8f8f8; }
        header { background: #0073aa; color: #fff; padding: 1em; text-align: center; }
        .categories { display: flex; flex-wrap: wrap; gap: 10px; padding: 1em; }
        .category { background: #fff; border-radius: 5px; padding: 0.5em 1em; cursor: pointer; border: 1px solid #ddd; }
        .products { display: flex; flex-wrap: wrap; gap: 10px; padding: 1em; }
        .product { background: #fff; border-radius: 5px; padding: 1em; width: 45%; box-sizing: border-box; border: 1px solid #ddd; }
        .product button { margin-top: 10px; }
        #cart { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1px solid #ddd; padding: 1em; }
        #cart-items { max-height: 120px; overflow-y: auto; margin-bottom: 1em; }
        #send-kitchen { background: #0073aa; color: #fff; border: none; padding: 1em; width: 100%; border-radius: 5px; font-size: 1em; cursor: pointer; }
    </style>
</head>
<body>
    <header>
        <h1>Self Checkout</h1>
    </header>
    <section class="categories" id="categories">
        <!-- Categories will be loaded here -->
    </section>
    <section class="products" id="products">
        <!-- Products will be loaded here -->
    </section>
    <div id="cart">
        <h3>View Cart</h3>
        <div id="cart-items">
            <!-- Cart items will be listed here -->
        </div>
        <button id="send-kitchen">Send to Kitchen</button>
    </div>
    <script>
        // Dummy data for categories and products
        const categories = [
            { id: 1, name: 'Beverages' },
            { id: 2, name: 'Appetizers' },
            { id: 3, name: 'Main Course' },
            { id: 4, name: 'Desserts' }
        ];
        const products = [
            { id: 101, name: 'Coke', price: 2, category: 1 },
            { id: 102, name: 'Orange Juice', price: 3, category: 1 },
            { id: 201, name: 'Spring Rolls', price: 5, category: 2 },
            { id: 301, name: 'Grilled Chicken', price: 12, category: 3 },
            { id: 401, name: 'Ice Cream', price: 4, category: 4 }
        ];
        let selectedCategory = null;
        let cart = [];

        function renderCategories() {
            const container = document.getElementById('categories');
            container.innerHTML = '';
            categories.forEach(cat => {
                const div = document.createElement('div');
                div.className = 'category';
                div.textContent = cat.name;
                div.onclick = () => {
                    selectedCategory = cat.id;
                    renderProducts();
                };
                container.appendChild(div);
            });
        }

        function renderProducts() {
            const container = document.getElementById('products');
            container.innerHTML = '';
            products.filter(p => !selectedCategory || p.category === selectedCategory)
                .forEach(prod => {
                    const div = document.createElement('div');
                    div.className = 'product';
                    div.innerHTML = `<strong>${prod.name}</strong><br>$${prod.price.toFixed(2)}<br>
                        <button onclick="addToCart(${prod.id})">Add to Cart</button>`;
                    container.appendChild(div);
                });
        }

        window.addToCart = function(productId) {
            const prod = products.find(p => p.id === productId);
            const item = cart.find(i => i.id === productId);
            if (item) {
                item.qty += 1;
            } else {
                cart.push({ ...prod, qty: 1 });
            }
            renderCart();
        };

        function renderCart() {
            const container = document.getElementById('cart-items');
            container.innerHTML = '';
            if (cart.length === 0) {
                container.innerHTML = '<em>Cart is empty</em>';
                return;
            }
            cart.forEach(item => {
                const div = document.createElement('div');
                div.textContent = `${item.name} x${item.qty} - $${(item.price * item.qty).toFixed(2)}`;
                container.appendChild(div);
            });
        }

        document.getElementById('send-kitchen').onclick = function() {
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }
            // Here you would send the cart to the kitchen (AJAX or form submit)
            alert('Order sent to kitchen!');
            cart = [];
            renderCart();
        };

        renderCategories();
        renderProducts();
        renderCart();
    </script>
</body>
</html>