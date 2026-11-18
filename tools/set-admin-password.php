<?php
/**
 * 设置后台管理员密码（命令行执行，不要通过浏览器访问）
 *
 * 用法：
 *   php tools/set-admin-password.php '你的新密码'
 *
 * 首次部署必须执行一次，否则后台无法登录。
 * 之后忘记密码也可以直接重新执行本脚本覆盖。
 *
 * 密码写入 data/password.php（内容形如 `<?php return '...';`）。
 * 用 .php 而不是 .hash/.json，是为了即使 nginx 没有额外配置，
 * 别人直接访问这个文件也只会执行它、拿不到哈希。
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

$newPassword = $argv[1] ?? '';

if ($newPassword === '') {
    fwrite(STDERR, "用法: php tools/set-admin-password.php '新密码'\n");
    exit(1);
}

$len = function_exists('mb_strlen') ? mb_strlen($newPassword) : strlen($newPassword);
if ($len < 8) {
    fwrite(STDERR, "密码至少 8 位。\n");
    exit(1);
}

$dataDir = dirname(__DIR__) . '/data';
if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true)) {
    fwrite(STDERR, "无法创建 data 目录：{$dataDir}\n");
    exit(1);
}

/** 写一个 `<?php return <值>;` 形式的文件 */
function writeSecretFile($path, $value) {
    $code = "<?php\n// 本文件由 tools/set-admin-password.php 生成，请勿手动编辑，也不要放进 Git\nreturn " . var_export($value, true) . ";\n";
    if (file_put_contents($path, $code, LOCK_EX) === false) {
        fwrite(STDERR, "写入失败：{$path}，请检查目录权限。\n");
        return false;
    }
    @chmod($path, 0644);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    return true;
}

if (!writeSecretFile($dataDir . '/password.php', password_hash($newPassword, PASSWORD_DEFAULT))) {
    exit(1);
}

// 清掉旧格式的密码文件与所有已签发的登录状态
@unlink($dataDir . '/password.hash');
if (file_exists($dataDir . '/tokens.php') || file_exists($dataDir . '/tokens.json')) {
    writeSecretFile($dataDir . '/tokens.php', []);
    @unlink($dataDir . '/tokens.json');
}

echo "✅ 后台密码已更新（bcrypt 加盐存储）。\n";
echo "   文件：data/password.php\n";
echo "   所有旧登录会话已失效，请重新登录后台。\n";
