<?php
/**
 * 设置后台管理员密码（命令行执行，不要通过浏览器访问）
 *
 * 用法：
 *   php tools/set-admin-password.php '你的新密码'
 *
 * 首次部署必须执行一次，否则后台无法登录。
 * 之后忘记密码也可以直接重新执行本脚本覆盖。
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

$hashFile = $dataDir . '/password.hash';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

if (file_put_contents($hashFile, $hash, LOCK_EX) === false) {
    fwrite(STDERR, "写入失败：{$hashFile}，请检查目录权限。\n");
    exit(1);
}
chmod($hashFile, 0640);

// 密码变更后让所有已签发的登录状态失效
$tokensFile = $dataDir . '/tokens.json';
if (file_exists($tokensFile)) {
    file_put_contents($tokensFile, '[]', LOCK_EX);
}

echo "✅ 后台密码已更新（bcrypt 加盐存储）。\n";
echo "   文件：data/password.hash\n";
echo "   所有旧登录会话已失效，请重新登录后台。\n";
