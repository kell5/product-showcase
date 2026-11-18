<?php
/**
 * 产品管理 API
 * 数据以 JSON 文件形式存储在服务器 data/ 目录
 * 图片以文件形式存储在 uploads/ 目录
 *
 * 首次部署请在服务器执行：php tools/set-admin-password.php
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// ─────────── CORS ───────────
// 后台页面与接口同源，默认不需要跨域。
// 若确实需要被其他域名调用，把域名加进下面数组（例：'https://www.lk-mcu.online'）。
$ALLOWED_ORIGINS = [];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$corsOk = ($origin !== '' && in_array($origin, $ALLOWED_ORIGINS, true));

if ($corsOk) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code($corsOk ? 200 : 403);
    exit;
}

// ─────────── 配置 ───────────
define('DATA_DIR', __DIR__ . '/data');
define('PRODUCTS_FILE', DATA_DIR . '/products.json');
// 机密数据统一用 .php 存储：被浏览器直接访问时 PHP 会执行它并输出空内容，
// 不会像 .json/.hash 那样把哈希和 token 原样吐出来。
define('PASSWORD_FILE', DATA_DIR . '/password.php');
define('TOKENS_FILE', DATA_DIR . '/tokens.php');
define('LIMIT_FILE', DATA_DIR . '/login-attempts.php');
define('LEGACY_PASSWORD_FILE', DATA_DIR . '/password.hash');
define('MESSAGES_FILE', DATA_DIR . '/messages.json');
define('MSG_LIMIT_FILE', DATA_DIR . '/msg-limit.php');
define('UPLOAD_DIR', __DIR__ . '/uploads');

define('MSG_MAX_KEEP', 500);        // 最多保留多少条留言
define('MSG_NAME_MAX', 20);         // 昵称长度上限
define('MSG_BODY_MAX', 500);        // 留言长度上限
define('MSG_INTERVAL', 20);         // 同一 IP 两次留言最小间隔（秒）
define('MSG_HOURLY', 5);            // 同一 IP 每小时最多留言数

define('TOKEN_TTL', 86400);                // 登录有效期 24 小时
define('MAX_LOGIN_FAILS', 5);              // 窗口内允许的失败次数
define('LOGIN_WINDOW', 600);               // 失败计数窗口 10 分钟
define('LOGIN_BLOCK', 900);                // 触发后封禁 15 分钟
define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// ─────────── JSON 文件读写 ───────────
function readJson($file, $default = []) {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

/** 加锁写入，避免并发请求互相覆盖。写不进去只返回 false，不抛 warning。 */
function writeJson($file, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * 读取机密数据（文件内容形如 `<?php return [...];`）。
 * 直接访问该文件时 PHP 只会执行并返回，不产生任何输出，因此不会泄漏。
 */
function readSecret($file, $default = null) {
    if (!file_exists($file)) return $default;
    // 改了文件内容后清掉 opcache，避免读到缓存里的旧值
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    $value = @include $file;
    return $value === false ? $default : $value;
}

function writeSecret($file, $value) {
    $code = "<?php\n// 本文件由 api.php 生成，请勿手动编辑，也不要放进 Git\nreturn " . var_export($value, true) . ";\n";
    $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $code, LOCK_EX) === false) return false;
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    @chmod($file, 0644);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    return true;
}

// ─────────── 通用工具 ───────────
function getProducts() {
    return readJson(PRODUCTS_FILE, []);
}

function saveProducts($products) {
    return writeJson(PRODUCTS_FILE, array_values($products));
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateId() {
    return bin2hex(random_bytes(8));
}

function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function textLength($s) {
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

// ─────────── 密码 ───────────
function getStoredPassword() {
    $hash = readSecret(PASSWORD_FILE);
    if (is_string($hash) && trim($hash) !== '') return trim($hash);

    // 兜底：迁移还没成功（例如 data/ 目录不可写）时继续读旧文件，
    // 保证后台至少还能登录，不会因为迁移失败而整体不可用。
    if (file_exists(LEGACY_PASSWORD_FILE)) {
        $legacy = trim((string) @file_get_contents(LEGACY_PASSWORD_FILE));
        if ($legacy !== '') return $legacy;
    }
    return null;
}

/**
 * 校验密码。
 * 返回 true 通过 / false 不通过 / null 表示后台密码尚未初始化。
 * 兼容旧的 sha256 哈希，校验通过后自动升级为 bcrypt。
 */
function verifyPassword($pwd) {
    $stored = getStoredPassword();
    if ($stored === null) return null;

    if (preg_match('/^[a-f0-9]{64}$/i', $stored)) {
        $ok = hash_equals(strtolower($stored), hash('sha256', $pwd));
        if ($ok) {
            setPassword($pwd);
        }
        return $ok;
    }
    return password_verify($pwd, $stored);
}

function setPassword($pwd) {
    return writeSecret(PASSWORD_FILE, password_hash($pwd, PASSWORD_DEFAULT));
}

// ─────────── 登录限流 ───────────
function checkLoginLimit() {
    $ip = clientIp();
    $now = time();
    $store = readSecret(LIMIT_FILE, []);
    if (!is_array($store)) $store = [];
    $entry = $store[$ip] ?? null;

    if (is_array($entry) && ($entry['blocked_until'] ?? 0) > $now) {
        $wait = (int) ceil(($entry['blocked_until'] - $now) / 60);
        respond(['success' => false, 'message' => "尝试次数过多，请 {$wait} 分钟后再试"], 429);
    }
}

function recordLoginFail() {
    $ip = clientIp();
    $now = time();
    $store = readSecret(LIMIT_FILE, []);
    if (!is_array($store)) $store = [];

    // 清理过期记录，避免文件无限增长
    foreach ($store as $k => $v) {
        if ($now - ($v['first'] ?? 0) > LOGIN_WINDOW && ($v['blocked_until'] ?? 0) < $now) {
            unset($store[$k]);
        }
    }

    $entry = $store[$ip] ?? ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    if ($now - ($entry['first'] ?? 0) > LOGIN_WINDOW) {
        $entry = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    }
    $entry['count'] = ($entry['count'] ?? 0) + 1;
    if ($entry['count'] >= MAX_LOGIN_FAILS) {
        $entry['blocked_until'] = $now + LOGIN_BLOCK;
        $entry['count'] = 0;
        $entry['first'] = $now;
    }
    $store[$ip] = $entry;
    writeSecret(LIMIT_FILE, $store);
}

function clearLoginFail() {
    $ip = clientIp();
    $store = readSecret(LIMIT_FILE, []);
    if (!is_array($store)) $store = [];
    if (isset($store[$ip])) {
        unset($store[$ip]);
        writeSecret(LIMIT_FILE, $store);
    }
}

// ─────────── Token（支持多端同时登录） ───────────
function readTokens() {
    $now = time();
    $tokens = readSecret(TOKENS_FILE, []);
    if (!is_array($tokens)) $tokens = [];
    return array_values(array_filter($tokens, function ($t) use ($now) {
        return isset($t['hash'], $t['expires']) && $t['expires'] > $now;
    }));
}

function issueToken() {
    $token = bin2hex(random_bytes(32));
    $tokens = readTokens();
    $tokens[] = ['hash' => hash('sha256', $token), 'expires' => time() + TOKEN_TTL];
    if (count($tokens) > 20) {
        $tokens = array_slice($tokens, -20);
    }
    writeSecret(TOKENS_FILE, $tokens);
    return $token;
}

function verifyToken($token) {
    if (!is_string($token) || strlen($token) !== 64) return false;
    $target = hash('sha256', $token);
    foreach (readTokens() as $t) {
        if (hash_equals($t['hash'], $target)) return true;
    }
    return false;
}

function revokeToken($token) {
    if (!is_string($token) || strlen($token) !== 64) return;
    $target = hash('sha256', $token);
    $tokens = array_values(array_filter(readTokens(), function ($t) use ($target) {
        return !hash_equals($t['hash'], $target);
    }));
    writeSecret(TOKENS_FILE, $tokens);
}

function bearerToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization' && stripos($value, 'bearer ') === 0) {
            return trim(substr($value, 7));
        }
    }
    return '';
}

function requireAuth() {
    if (!verifyToken(bearerToken())) {
        respond(['success' => false, 'message' => '未授权，请重新登录'], 401);
    }
}

// ─────────── 上传相关 ───────────
/** 按真实文件内容识别图片类型，返回扩展名；非图片返回 null */
function imageExt($path) {
    $map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
    ];
    // IMAGETYPE_WEBP 需要 PHP 7.1+，低版本直接不支持 webp
    if (defined('IMAGETYPE_WEBP')) {
        $map[IMAGETYPE_WEBP] = 'webp';
    }
    $info = @getimagesize($path);
    if (!$info || !isset($info[2])) return null;
    return $map[$info[2]] ?? null;
}

/** 只允许删除 uploads/ 目录内的文件，防止路径穿越 */
function safeUnlinkUpload($relative) {
    $root = realpath(UPLOAD_DIR);
    if ($root === false) return;
    $target = realpath(UPLOAD_DIR . '/' . basename($relative));
    if ($target === false) return;
    if (strpos($target, $root . DIRECTORY_SEPARATOR) !== 0) return;
    if (is_file($target)) @unlink($target);
}

// ─────────── 留言 ───────────
function getMessages() {
    $list = readJson(MESSAGES_FILE, []);
    return is_array($list) ? $list : [];
}

function saveMessages($list) {
    return writeJson(MESSAGES_FILE, array_values($list));
}

function clipText($s, $max) {
    $s = (string) $s;
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return substr($s, 0, $max);
}

/** 明显的垃圾留言：堆链接 */
function looksLikeSpam($text) {
    return preg_match_all('#(https?://|www\.)#i', $text) >= 3;
}

/**
 * 留言频率限制。返回 null 表示通过，返回字符串表示应拒绝并给出提示。
 * 只在这里记录 IP 的哈希，不写进留言数据本身。
 */
function messageRateLimit() {
    $now = time();
    $key = substr(hash('sha256', clientIp() . '|guestbook'), 0, 16);
    $store = readSecret(MSG_LIMIT_FILE, []);
    if (!is_array($store)) $store = [];

    $entry = $store[$key] ?? ['last' => 0, 'times' => []];
    $times = array_filter((array) ($entry['times'] ?? []), function ($t) use ($now) {
        return $now - (int) $t < 3600;
    });
    $times = array_values($times);

    $last = (int) ($entry['last'] ?? 0);
    if ($last > 0 && $now - $last < MSG_INTERVAL) {
        return '留言太快了，请 ' . (MSG_INTERVAL - ($now - $last)) . ' 秒后再提交';
    }
    if (count($times) >= MSG_HOURLY) {
        return '这段时间留言有点多，请稍后再试';
    }

    $times[] = $now;
    $store[$key] = ['last' => $now, 'times' => $times];

    // 清理一天前的记录，避免文件无限增长
    foreach ($store as $k => $v) {
        $raw = array_filter((array) ($v['times'] ?? []), 'is_numeric');
        if (!$raw || $now - max(array_map('intval', $raw)) > 86400) {
            unset($store[$k]);
        }
    }

    writeSecret(MSG_LIMIT_FILE, $store);
    return null;
}

/** 对外只暴露展示需要的字段 */
function publicMessage($m) {
    return [
        'id'        => $m['id'] ?? '',
        'name'      => $m['name'] ?? '',
        'content'   => $m['content'] ?? '',
        'createdAt' => $m['createdAt'] ?? '',
    ];
}

// ─────────── 旧格式机密文件迁移 ───────────
/**
 * 旧版本把密码哈希、token、登录计数存成 .hash / .json，
 * 这些文件能被浏览器直接下载。这里在每次请求时检查一次，
 * 搬到 .php 存储（访问时只执行不输出）并删除旧文件。
 * 迁移完成后这段逻辑没有任何副作用。
 */
function migrateLegacySecrets() {
    try {
        // 密码哈希：data/password.hash -> data/password.php
        if (!file_exists(PASSWORD_FILE) && file_exists(LEGACY_PASSWORD_FILE)) {
            $legacy = trim((string) @file_get_contents(LEGACY_PASSWORD_FILE));
            if ($legacy !== '') {
                writeSecret(PASSWORD_FILE, $legacy);
            }
        }
        // 只有新文件确实写成功，才删除可被下载的旧文件
        if (file_exists(PASSWORD_FILE) && file_exists(LEGACY_PASSWORD_FILE)) {
            @unlink(LEGACY_PASSWORD_FILE);
        }

        // token 与登录计数：data/*.json -> data/*.php
        foreach (['tokens', 'login-attempts'] as $name) {
            $old = DATA_DIR . '/' . $name . '.json';
            $new = DATA_DIR . '/' . $name . '.php';
            if (!file_exists($new) && file_exists($old)) {
                $data = readJson($old, []);
                if (is_array($data) && $data) {
                    writeSecret($new, $data);
                }
            }
            if (file_exists($new) && file_exists($old)) {
                @unlink($old);
            }
        }
    } catch (Throwable $e) {
        // 迁移是尽力而为，失败绝不能影响接口本身
    }
}

migrateLegacySecrets();

// ─────────── 路由 ───────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // 登录验证
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $pwd = (string) ($input['password'] ?? '');

        checkLoginLimit();

        if ($pwd === '') {
            respond(['success' => false, 'message' => '请输入密码'], 400);
        }

        $result = verifyPassword($pwd);
        if ($result === null) {
            respond([
                'success' => false,
                'message' => '后台密码未初始化，请在服务器执行：php tools/set-admin-password.php',
            ], 503);
        }
        if ($result === false) {
            recordLoginFail();
            respond(['success' => false, 'message' => '密码错误'], 401);
        }

        clearLoginFail();
        respond(['success' => true, 'token' => issueToken(), 'expiresIn' => TOKEN_TTL]);
        break;

    // 退出登录（吊销当前 token）
    case 'logout':
        revokeToken(bearerToken());
        respond(['success' => true]);
        break;

    // 验证 token
    case 'verify':
        if (verifyToken(bearerToken())) {
            respond(['valid' => true]);
        }
        respond(['valid' => false], 401);
        break;

    // 获取产品列表（公开）
    case 'list':
        respond(['products' => getProducts()]);
        break;

    // 添加产品（需认证）
    case 'add':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $product = buildProduct($input);
        if ($product['name'] === '') {
            respond(['success' => false, 'message' => '产品名称不能为空'], 400);
        }
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
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
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
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = $input['id'] ?? '';
        $products = getProducts();
        foreach ($products as $p) {
            if ($p['id'] === $id && !empty($p['images'])) {
                foreach ($p['images'] as $img) {
                    safeUnlinkUpload($img);
                }
            }
        }
        $newProducts = array_values(array_filter($products, function ($p) use ($id) {
            return $p['id'] !== $id;
        }));
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
        if (!empty($file['error'])) {
            respond(['success' => false, 'message' => '文件上传失败（错误码 ' . (int) $file['error'] . '）'], 400);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            respond(['success' => false, 'message' => '非法的上传请求'], 400);
        }
        if ($file['size'] > MAX_UPLOAD_BYTES) {
            respond(['success' => false, 'message' => '图片不能超过 20MB'], 400);
        }
        // 用文件真实内容判断类型，不信任客户端传来的 MIME / 文件名
        $ext = imageExt($file['tmp_name']);
        if ($ext === null) {
            respond(['success' => false, 'message' => '仅支持 JPG / PNG / GIF / WEBP 图片'], 400);
        }
        $filename = generateId() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
            respond(['success' => true, 'url' => 'uploads/' . $filename]);
        }
        respond(['success' => false, 'message' => '上传失败'], 500);
        break;

    // 修改密码（需认证）
    case 'change_password':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $oldPwd = (string) ($input['oldPassword'] ?? '');
        $newPwd = (string) ($input['newPassword'] ?? '');

        if (textLength($newPwd) < 8) {
            respond(['success' => false, 'message' => '新密码至少 8 位'], 400);
        }
        if (verifyPassword($oldPwd) !== true) {
            recordLoginFail();
            respond(['success' => false, 'message' => '当前密码错误'], 400);
        }
        setPassword($newPwd);
        // 改密后让所有旧会话失效
        writeSecret(TOKENS_FILE, []);
        respond(['success' => true, 'relogin' => true]);
        break;

    // 导出数据
    case 'export':
        requireAuth();
        respond(['products' => getProducts()]);
        break;

    // 导入数据（需认证）
    case 'import':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
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

    // 获取留言（公开，最多 50 条，新的在前）
    case 'list_messages':
        $all = getMessages();
        $out = [];
        foreach (array_slice($all, 0, 50) as $m) {
            $out[] = publicMessage($m);
        }
        respond(['success' => true, 'messages' => $out, 'total' => count($all)]);
        break;

    // 提交留言（公开，带频率限制与蜜罐）
    case 'add_message':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        // 蜜罐字段：真人看不到，机器人会填。填了就假装成功但不入库。
        if (!empty($input['website'])) {
            respond(['success' => true, 'message' => '留言已提交']);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $body = trim((string) ($input['content'] ?? ''));
        $name = clipText(preg_replace('/\s+/u', ' ', $name), MSG_NAME_MAX);
        $body = clipText($body, MSG_BODY_MAX);

        if ($name === '') {
            respond(['success' => false, 'message' => '请填写昵称'], 400);
        }
        if (textLength($body) < 2) {
            respond(['success' => false, 'message' => '留言内容太短了'], 400);
        }
        if (looksLikeSpam($body)) {
            respond(['success' => false, 'message' => '留言内容包含过多链接，已拒绝'], 400);
        }

        $limited = messageRateLimit();
        if ($limited !== null) {
            respond(['success' => false, 'message' => $limited], 429);
        }

        $message = [
            'id'        => generateId(),
            'name'      => $name,
            'content'   => $body,
            'createdAt' => date('c'),
        ];

        $messages = getMessages();
        array_unshift($messages, $message);
        if (count($messages) > MSG_MAX_KEEP) {
            $messages = array_slice($messages, 0, MSG_MAX_KEEP);
        }
        if (!saveMessages($messages)) {
            respond(['success' => false, 'message' => '留言保存失败，请稍后再试'], 500);
        }
        respond(['success' => true, 'message' => publicMessage($message)]);
        break;

    // 删除留言（需认证）
    case 'delete_message':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (string) ($input['id'] ?? '');
        if ($id === '') {
            respond(['success' => false, 'message' => '缺少留言 ID'], 400);
        }
        $messages = getMessages();
        $left = array_values(array_filter($messages, function ($m) use ($id) {
            return ($m['id'] ?? '') !== $id;
        }));
        if (count($left) === count($messages)) {
            respond(['success' => false, 'message' => '留言不存在'], 404);
        }
        saveMessages($left);
        respond(['success' => true, 'deleted' => $id]);
        break;

    // 获取 Hero 图片（公开）
    case 'get_hero':
        $hero = readJson(DATA_DIR . '/hero.json', []);
        respond(['success' => true, 'images' => $hero['images'] ?? []]);
        break;

    // 保存 Hero 图片（需认证）
    case 'set_hero':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        writeJson(DATA_DIR . '/hero.json', ['images' => $input['images'] ?? []]);
        respond(['success' => true]);
        break;

    default:
        respond([
            'message' => 'Smart Hardware Lab API',
            'actions' => ['list', 'login', 'logout', 'verify', 'add', 'update', 'delete', 'upload', 'get_hero', 'set_hero', 'list_messages', 'add_message', 'delete_message'],
        ]);
}

// ─────────── 辅助函数 ───────────
function buildProduct($input) {
    return [
        'name'        => trim($input['name'] ?? ''),
        'subtitle'    => trim($input['subtitle'] ?? ''),
        'category'    => trim($input['category'] ?? '其他'),
        'tags'        => is_array($input['tags'] ?? null) ? array_slice($input['tags'], 0, 20) : [],
        'description' => trim($input['description'] ?? ''),
        'specs'       => trim($input['specs'] ?? ''),
        'level'       => in_array($input['level'] ?? '', ['featured', 'normal'], true) ? $input['level'] : 'normal',
        'images'      => is_array($input['images'] ?? null) ? array_slice($input['images'], 0, 10) : [],
    ];
}
