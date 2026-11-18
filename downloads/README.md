# 网站下载资源

> ⚠️ APK 已不再提交到 Git（单文件约 50MB，会让仓库体积迅速膨胀且无法回收）。
> 服务器上的 `downloads/lightmesh-app-release.apk` 不受自动部署影响，会一直保留。

## 当前文件

| 文件 | 说明 | 大小 | SHA256 |
| --- | --- | --- | --- |
| `lightmesh-app-release.apk` | LightMesh 灯联网 Android release APK，用于 ESP32 BLE Mesh 灯控、门铃、网关与 OTA 演示 | 51,859,075 bytes | `C3AB925909250FF5D90202646390D9F1537FB07CBBB041E31A70FDCEFF921725` |

## 如何发布新版本 APK

**方式 A：直接传到服务器（最简单）**

```bash
scp -i ~/.ssh/lk_mcu_sftp.pem lightmesh-app-release.apk \
    root@114.55.208.72:/www/wwwroot/114.55.208.72/downloads/
```

或直接用宝塔面板的文件管理上传覆盖同名文件。`deploy.yml` 里已排除 `downloads/*.apk`，
自动部署既不会删除它，也不会覆盖它。

**方式 B：走 GitHub Release**

把 APK 上传到仓库的 Releases 页面，再把页面上的下载链接改成 Release 地址即可。

生产分发前建议补正式签名、版本号、渠道和隐私合规说明。
