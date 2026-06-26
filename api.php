<?php
/**
 * 产品管理 API
 * 数据以 JSON 文件形式存储在服务器 data/ 目录
 * 图片以文件形式存储在 uploads/ 目录
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─────────── 配置 ───────────
define('DATA_DIR', __DIR__ . '/data');
define('PRODUCTS_FILE', DATA_DIR . '/products.json');
define('PASSWORD_FILE', DATA_DIR . '/password.hash');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('DEFAULT_PASSWORD', 'admin123');

// 确保目录存在
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// ─────────── 工具函数 ───────────
function getProducts() {
    if (!file_exists(PRODUCTS_FILE)) return [];
    $data = json_decode(file_get_contents(PRODUCTS_FILE), true);
    return is_array($data) ? $data : [];
}

function saveProducts($products) {
    file_put_contents(PRODUCTS_FILE, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getPasswordHash() {
    if (file_exists(PASSWORD_FILE)) {
        return trim(file_get_contents(PASSWORD_FILE));
    }
    $hash = hash('sha256', DEFAULT_PASSWORD);
    file_put_contents(PASSWORD_FILE, $hash);
    return $hash;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateId() {
    return bin2hex(random_bytes(8));
}

// ─────────── 路由 ───────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // 登录验证
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true);
        $pwd = $input['password'] ?? '';
        $inputHash = hash('sha256', $pwd);
        $storedHash = getPasswordHash();
        if ($inputHash === $storedHash) {
            // 生成简单 token（有效期24小时）
            $token = bin2hex(random_bytes(32));
            $tokenData = ['token' => $token, 'expires' => time() + 86400];
            file_put_contents(DATA_DIR . '/token.json', json_encode($tokenData));
            respond(['success' => true, 'token' => $token]);
        } else {
            respond(['success' => false, 'message' => '密码错误'], 401);
        }
        break;

    // 验证 token
    case 'verify':
        $token = $_GET['token'] ?? '';
        if (verifyToken($token)) {
            respond(['valid' => true]);
        } else {
            respond(['valid' => false], 401);
        }
        break;

    // 获取产品列表（公开）
    case 'list':
        respond(['products' => getProducts()]);
        break;

    // 添加产品（需认证）
    case 'add':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $product = buildProduct($input);
        $product['id'] = generateId();
        $product['createdAt'] = date('c');
        $product['updatedAt'] = date('c');
        $products = getProducts();
        array_unshift($products, $product);
        saveProducts($products);
        respond(['success' => true, 'product' => $product]);
        break;

    // 更新产品（需认证）
    case 'update':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? '';
        $products = getProducts();
        $found = false;
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                $updated = buildProduct($input);
                $updated['id'] = $id;
                $updated['createdAt'] = $p['createdAt'] ?? date('c');
                $updated['updatedAt'] = date('c');
                // 保留已有图片（如果前端未修改）
                if (empty($updated['images']) && !empty($p['images'])) {
                    $updated['images'] = $p['images'];
                }
                $p = $updated;
                $found = true;
                break;
            }
        }
        unset($p);
        if (!$found) respond(['success' => false, 'message' => '产品不存在'], 404);
        saveProducts($products);
        respond(['success' => true]);
        break;

    // 删除产品（需认证）
    case 'delete':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? '';
        $products = getProducts();
        $newProducts = array_values(array_filter($products, function($p) use ($id) {
            return $p['id'] !== $id;
        }));
        // 删除关联的图片文件
        foreach ($products as $p) {
            if ($p['id'] === $id && !empty($p['images'])) {
                foreach ($p['images'] as $img) {
                    $path = __DIR__ . '/' . $img;
                    if (file_exists($path)) unlink($path);
                }
            }
        }
        saveProducts($newProducts);
        respond(['success' => true]);
        break;

    // 上传图片（需认证）
    case 'upload':
        requireAuth();
        if (empty($_FILES['image'])) {
            respond(['success' => false, 'message' => '未收到文件'], 400);
        }
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            respond(['success' => false, 'message' => '不支持的图片格式'], 400);
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            respond(['success' => false, 'message' => '图片不能超过20MB'], 400);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = generateId() . '.' . $ext;
        $targetPath = UPLOAD_DIR . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            respond(['success' => true, 'url' => 'uploads/' . $filename]);
        } else {
            respond(['success' => false, 'message' => '上传失败'], 500);
        }
        break;

    // 修改密码（需认证）
    case 'change_password':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $oldPwd = $input['oldPassword'] ?? '';
        $newPwd = $input['newPassword'] ?? '';
        $oldHash = hash('sha256', $oldPwd);
        $storedHash = getPasswordHash();
        if ($oldHash !== $storedHash) {
            respond(['success' => false, 'message' => '当前密码错误'], 400);
        }
        $newHash = hash('sha256', $newPwd);
        file_put_contents(PASSWORD_FILE, $newHash);
        respond(['success' => true]);
        break;

    // 导出数据
    case 'export':
        requireAuth();
        respond(['products' => getProducts()]);
        break;

    // 导入数据（需认证）
    case 'import':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $input['products'] ?? [];
        if (!is_array($data)) respond(['success' => false, 'message' => '格式错误'], 400);
        saveProducts($data);
        respond(['success' => true, 'count' => count($data)]);
        break;

    // 清空数据（需认证）
    case 'clear':
        requireAuth();
        saveProducts([]);
        respond(['success' => true]);
        break;

    // 获取 Hero 图片（公开）
    case 'get_hero':
        $heroFile = DATA_DIR . '/hero.json';
        if (file_exists($heroFile)) {
            $heroData = json_decode(file_get_contents($heroFile), true);
            respond(['success' => true, 'images' => $heroData['images'] ?? []]);
        } else {
            respond(['success' => true, 'images' => []]);
        }
        break;

    // 保存 Hero 图片（需认证）
    case 'set_hero':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $images = $input['images'] ?? [];
        file_put_contents(DATA_DIR . '/hero.json', json_encode(['images' => $images], JSON_UNESCAPED_UNICODE));
        respond(['success' => true]);
        break;

    default:
        respond(['message' => 'Smart Hardware Lab API', 'actions' => ['list','login','add','update','delete','upload','get_hero','set_hero']], 200);
}

// ─────────── 辅助函数 ───────────
function verifyToken($token) {
    $tokenFile = DATA_DIR . '/token.json';
    if (!file_exists($tokenFile)) return false;
    $data = json_decode(file_get_contents($tokenFile), true);
    return $data && $data['token'] === $token && $data['expires'] > time();
}

function requireAuth() {
    $headers = getallheaders();
    $token = '';
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
    } elseif (isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    if (!verifyToken($token)) {
        respond(['success' => false, 'message' => '未授权，请重新登录'], 401);
    }
}

function buildProduct($input) {
    return [
        'name'        => trim($input['name'] ?? ''),
        'subtitle'    => trim($input['subtitle'] ?? ''),
        'category'    => trim($input['category'] ?? '其他'),
        'tags'        => $input['tags'] ?? [],
        'description' => trim($input['description'] ?? ''),
        'specs'       => trim($input['specs'] ?? ''),
        'level'       => in_array($input['level'] ?? '', ['featured','normal']) ? $input['level'] : 'normal',
        'images'      => $input['images'] ?? [],
    ];
}
