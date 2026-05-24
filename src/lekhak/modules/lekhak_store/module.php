<?php
namespace Lekhak\Modules\LekhakStore;

/**
 * A lightweight alternative to full commerce for selling simple digital or physical goods.
 * @configure admin/config/lekhak_store
 */

class LekhakModuleCommerce {
    private $name = 'lekhak_store';
    private $title = 'lekhak_store';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_commerce_products (
                sku VARCHAR(100) PRIMARY KEY,
                title VARCHAR(255),
                price DECIMAL(10, 2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'USD',
                status INTEGER DEFAULT 1
            )");
            
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_commerce_orders (
                order_id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER,
                session_id VARCHAR(100),
                status VARCHAR(50) DEFAULT 'cart',
                total DECIMAL(10, 2) DEFAULT 0.00,
                created_at DATETIME
            )");
            
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_commerce_line_items (
                line_item_id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER,
                sku VARCHAR(100),
                quantity INTEGER DEFAULT 1,
                unit_price DECIMAL(10, 2),
                FOREIGN KEY(order_id) REFERENCES lekhak_commerce_orders(order_id) ON DELETE CASCADE
            )");

            // Seed a product for testing if empty
            $res = $db->execute_query("SELECT sku FROM lekhak_commerce_products LIMIT 1");
            if (empty($res)) {
                $db->execute_query("INSERT INTO lekhak_commerce_products (sku, title, price) VALUES (?, ?, ?)", 
                    ['TEST-01', 'Lekhak CMS Pro License', 99.00]);
            }
        } catch (\Exception $e) {}
        return true;
    }

    /**
     * Retrieves the current cart order for the user/session.
     */
    public function getCartOrder() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $session_id = session_id();
        if (!$session_id) {
            session_start();
            $session_id = session_id();
        }
        
        $customer_id = $_SESSION['spp_user_id'] ?? 0;
        
        // Find existing cart
        $order = $db->execute_query("SELECT * FROM lekhak_commerce_orders WHERE (session_id = ? OR (customer_id = ? AND customer_id > 0)) AND status = 'cart' LIMIT 1", [$session_id, $customer_id]);
        
        if (empty($order)) {
            // Create cart
            $db->execute_query("INSERT INTO lekhak_commerce_orders (customer_id, session_id, created_at) VALUES (?, ?, ?)", [$customer_id, $session_id, date('Y-m-d H:i:s')]);
            $order_id = $db->getLastInsertId();
            return ['order_id' => $order_id, 'total' => 0.00];
        }
        
        return $order[0];
    }

    /**
     * API to add a product to the cart.
     */
    public function addToCart($sku, $quantity = 1) {
        $db = new \SPPMod\SPPDB\SPPDB();
        $product = $db->execute_query("SELECT * FROM lekhak_commerce_products WHERE sku = ? AND status = 1 LIMIT 1", [$sku]);
        
        if (empty($product)) throw new \Exception("Product not found or unavailable.");
        
        $order = $this->getCartOrder();
        
        // Check if line item exists
        $lineItem = $db->execute_query("SELECT * FROM lekhak_commerce_line_items WHERE order_id = ? AND sku = ? LIMIT 1", [$order['order_id'], $sku]);
        
        if (!empty($lineItem)) {
            $db->execute_query("UPDATE lekhak_commerce_line_items SET quantity = quantity + ? WHERE line_item_id = ?", [$quantity, $lineItem[0]['line_item_id']]);
        } else {
            $db->execute_query("INSERT INTO lekhak_commerce_line_items (order_id, sku, quantity, unit_price) VALUES (?, ?, ?, ?)", 
                [$order['order_id'], $sku, $quantity, $product[0]['price']]);
        }
        
        $this->recalculateOrderTotal($order['order_id']);
    }

    private function recalculateOrderTotal($order_id) {
        $db = new \SPPMod\SPPDB\SPPDB();
        $total = $db->execute_query("SELECT SUM(quantity * unit_price) as total FROM lekhak_commerce_line_items WHERE order_id = ?", [$order_id]);
        $val = $total[0]['total'] ?? 0.00;
        $db->execute_query("UPDATE lekhak_commerce_orders SET total = ? WHERE order_id = ?", [$val, $order_id]);
        return $val;
    }

    /**
     * Expose a Cart block to the CMS
     */
    public function hook_block_alter(&$blocks) {
        $blocks['commerce_cart'] = [
            'title' => 'Shopping Cart',
            'handler' => function() {
                try {
                    $order = $this->getCartOrder();
                    $db = new \SPPMod\SPPDB\SPPDB();
                    $items = $db->execute_query("SELECT SUM(quantity) as items FROM lekhak_commerce_line_items WHERE order_id = ?", [$order['order_id']]);
                    $count = $items[0]['items'] ?? 0;
                    
                    return '<div class="commerce-cart-block" style="padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-align: center;">
                                <strong>Cart</strong><br>
                                ' . (int)$count . ' items - $' . number_format($order['total'], 2) . '
                            </div>';
                } catch (\Exception $e) {
                    return '<!-- Cart Error -->';
                }
            }
        ];
    }


    // VirtueMart Extension
    public static function hook_commerce_checkout_alter(&$checkout_pane) {
        // Enable Guest Checkout workflow
        $checkout_pane['guest_checkout'] = true;
    }
    public static function hook_commerce_currency_convert($amount, $from, $to) {
        // Multi-currency live conversion hook
        $rates = ["USD" => 1, "EUR" => 0.85, "INR" => 83.0];
        return ($amount / $rates[$from]) * $rates[$to];
    }


    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
  'enabled' => 
  [
    'type' => 'checkbox',
    'title' => 'Enable advanced features',
    'default' => true,
  ],
  'log_level' => 
  [
    'type' => 'select',
    'title' => 'Log Level',
    'options' => 
    [
      'info' => 'Info',
      'warning' => 'Warning',
      'error' => 'Error',
    ],
    'default' => 'warning',
  ],
];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_store',
    'title' => 'lekhak_store',
    'instance' => new LekhakModuleCommerce()
];
