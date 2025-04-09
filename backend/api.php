<?php
header("Content-Type: application/json");
require_once 'config.php';

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// API Endpoints
if ($path_parts[1] === 'api.php') {
    array_shift($path_parts);
}

try {
    switch ($path_parts[0]) {
        case 'menu':
            handleMenuRequest($conn, $request_method);
            break;
        case 'order':
            handleOrderRequest($conn, $request_method, $path_parts);
            break;
        case 'cash':
            handleCashRequest($conn, $request_method, $path_parts);
            break;
        case 'orders':
            handleOrdersRequest($conn, $request_method, $path_parts);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleMenuRequest($conn, $method) {
    if ($method === 'GET') {
        $result = $conn->query("SELECT * FROM menu WHERE available = TRUE");
        $menu = [];
        while ($row = $result->fetch_assoc()) {
            $menu[] = $row;
        }
        echo json_encode($menu);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleOrderRequest($conn, $method, $path_parts) {
    if ($method === 'POST' && empty($path_parts[1])) {
        // Create new order
        $data = json_decode(file_get_contents('php://input'), true);
        $customer_id = sanitize_input($data['customer_id']);
        $items = json_encode($data['items']);
        $payment_method = sanitize_input($data['payment_method']);
        
        $status = ($payment_method === 'cash') ? 'Pending Approval' : 'In Preparation';
        
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, items, status, payment_method) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $customer_id, $items, $status, $payment_method);
        $stmt->execute();
        
        $order_id = $stmt->insert_id;
        echo json_encode(['order_id' => $order_id, 'status' => $status]);
        
    } elseif ($method === 'PUT' && isset($path_parts[2]) && $path_parts[1] === 'status') {
        // Update order status
        $order_id = intval($path_parts[2]);
        $data = json_decode(file_get_contents('php://input'), true);
        $status = sanitize_input($data['status']);
        
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleCashRequest($conn, $method, $path_parts) {
    if ($method === 'POST' && isset($path_parts[1])) {
        if ($path_parts[1] === 'approve') {
            // Approve cash order
            $order_id = intval($path_parts[2]);
            $data = json_decode(file_get_contents('php://input'), true);
            $amount = floatval($data['amount']);
            
            // Update order status
            $stmt = $conn->prepare("UPDATE orders SET status = 'In Preparation' WHERE id = ? AND status = 'Pending Approval'");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            
            // Log cash deposit
            $stmt = $conn->prepare("INSERT INTO cash_logs (order_id, amount, type) VALUES (?, ?, 'deposit')");
            $stmt->bind_param("id", $order_id, $amount);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
        } elseif ($path_parts[1] === 'deposit') {
            // Record cash deposit
            $data = json_decode(file_get_contents('php://input'), true);
            $amount = floatval($data['amount']);
            
            $stmt = $conn->prepare("INSERT INTO cash_logs (amount, type) VALUES (?, 'deposit')");
            $stmt->bind_param("d", $amount);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
        } elseif ($path_parts[1] === 'withdraw') {
            // Record cash withdrawal
            $data = json_decode(file_get_contents('php://input'), true);
            $amount = floatval($data['amount']);
            $reason = sanitize_input($data['reason']);
            
            $stmt = $conn->prepare("INSERT INTO cash_logs (amount, type, reason) VALUES (?, 'withdrawal', ?)");
            $stmt->bind_param("ds", $amount, $reason);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
        }
    } elseif ($method === 'GET') {
        if (empty($path_parts[1])) {
            // Get all cash logs
            $result = $conn->query("SELECT * FROM cash_logs ORDER BY created_at DESC");
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            echo json_encode($logs);
        } elseif ($path_parts[1] === 'balance') {
            // Calculate current balance
            $deposits = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM cash_logs WHERE type = 'deposit'")->fetch_assoc();
            $withdrawals = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM cash_logs WHERE type = 'withdrawal'")->fetch_assoc();
            $balance = $deposits['total'] - $withdrawals['total'];
            echo json_encode($balance);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleOrdersRequest($conn, $method, $path_parts) {
    if ($method === 'GET') {
        $status = isset($_GET['status']) ? sanitize_input($_GET['status']) : null;
        $payment_method = isset($_GET['payment_method']) ? sanitize_input($_GET['payment_method']) : null;
        $customer_id = isset($_GET['customer_id']) ? sanitize_input($_GET['customer_id']) : null;
        $date = isset($_GET['date']) ? sanitize_input($_GET['date']) : null;

        $query = "SELECT * FROM orders WHERE 1=1";
        $params = [];
        $types = "";

        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if ($payment_method) {
            $query .= " AND payment_method = ?";
            $params[] = $payment_method;
            $types .= "s";
        }

        if ($customer_id) {
            $query .= " AND customer_id = ?";
            $params[] = $customer_id;
            $types .= "s";
        }

        if ($date) {
            $query .= " AND DATE(created_at) = ?";
            $params[] = $date;
            $types .= "s";
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        echo json_encode($orders);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
?>