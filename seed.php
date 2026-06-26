<?php
/**
 * 初始化脚本 — 将所有现有产品写入 data/products.json
 * 运行一次后请删除此文件！
 * 访问: http://114.55.208.72/seed.php
 */

define('DATA_DIR', __DIR__ . '/data');
define('PRODUCTS_FILE', DATA_DIR . '/products.json');

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

$products = [
    // ═══════ 三大核心产品 ═══════
    [
        'id' => 'gesture-arm',
        'name' => '体感操控机械臂',
        'subtitle' => 'GesturePilot — 基于MPU6050体感手套 + NRF24L01无线传输，驱动多路SG90舵机实现手势操控机械臂',
        'category' => '机器人',
        'tags' => ['STM32', 'MPU6050', 'NRF24L01', 'SG90舵机', 'OLED', '体感交互'],
        'description' => '戴上手套，挥动手臂 — MPU6050六轴传感器实时采集手部姿态，NRF24L01无线传输至STM32控制板，驱动多路SG90舵机实现机械臂的抬升、旋转与夹取，OLED屏幕同步显示运行状态。',
        'specs' => "主控芯片|STM32F103C8T6\n姿态传感器|MPU6050 六轴加速度/陀螺仪\n无线模块|NRF24L01 — 2.4GHz ISM 频段\n舵机型号|SG90 微型舵机 × 多路\n舵机角度范围|0° – 180°\n通信协议|SPI (NRF24L01)、I2C (MPU6050 / OLED)\n显示屏|0.96″ OLED — 128×64 像素\n串口波特率|115200 bps\n供电|5V USB / 锂电池",
        'level' => 'featured',
        'staticPage' => 'product-gesture-arm.html',
        'images' => [],
        'createdAt' => '2026-01-01T00:00:00+08:00',
        'updatedAt' => date('c'),
    ],
    [
        'id' => 'sound-core',
        'name' => '嵌入式音频终端',
        'subtitle' => 'SoundCore — 基于STM32F103与VS1053音频解码模块，支持MP3播放、录音、SD卡存储与LCD实时交互',
        'category' => '音频设备',
        'tags' => ['STM32F103', 'VS1053', 'FatFS', 'SD卡', 'LCD显示', '录音'],
        'description' => 'STM32F103精英开发板搭载VS1053专业音频解码芯片，实现MP3/WAV多格式播放、高质量录音、实时频谱显示，FatFS文件系统管理SD卡音频库，LCD屏幕提供完整的操控界面。',
        'specs' => "主控芯片|STM32F103ZET6 (精英开发板)\n音频解码|VS1053B — 支持 MP3/WAV/OGG/FLAC\n采样率|最高 48kHz / 16bit 立体声\n存储介质|MicroSD 卡 (FatFS 文件系统)\n显示屏|LCD 彩色显示屏\n通信接口|SPI (VS1053 + SD卡)、UART 调试\n录音格式|WAV — PCM编码\n操控方式|物理按键 (播放/暂停/上下曲/音量)\n供电|5V USB",
        'level' => 'featured',
        'staticPage' => 'product-sound-core.html',
        'images' => [],
        'createdAt' => '2026-01-02T00:00:00+08:00',
        'updatedAt' => date('c'),
    ],
    [
        'id' => 'vision-tracker',
        'name' => '智能视觉追踪臂',
        'subtitle' => 'VisionTracker — K210摄像头实时颜色追踪 + 增量式PID算法，驱动四轴舵机精准追随目标物体',
        'category' => '机器人',
        'tags' => ['K210', '增量式PID', 'EMA滤波', 'UART', '四轴舵机', '色块追踪'],
        'description' => 'K210板载摄像头实时进行颜色学习与色块追踪，坐标经EMA指数滤波平滑后通过UART发送至STM32，增量式PID算法精准驱动四路舵机，机械臂持续追随目标物体运动。',
        'specs' => "视觉处理|K210 — 双核 RISC-V 64bit / 400MHz\n主控芯片|STM32 系列\n摄像头帧率|30 fps (QVGA)\n追踪算法|颜色学习 + blob 检测 + EMA 滤波\n控制算法|增量式 PID (Kp / Ki / Kd 可调)\n舵机通道|4 路 PWM 舵机 (0°–180°)\n通信协议|UART — 115200 bps\n扩展外设|RGB 灯带、蜂鸣器、舵机扩展接口\n供电|5V USB / 锂电池组",
        'level' => 'featured',
        'staticPage' => 'product-vision-tracker.html',
        'images' => [],
        'createdAt' => '2026-01-03T00:00:00+08:00',
        'updatedAt' => date('c'),
    ],

    // ═══════ 四月项目 ═══════
    ['id'=>'esp-iot','name'=>'ESP物联网项目','subtitle'=>'基于ESP系列的物联网数据采集与远程控制平台','category'=>'物联网','tags'=>['ESP32','WiFi','MQTT','物联网'],'description'=>'基于ESP系列芯片实现传感器数据采集、WiFi联网、MQTT云端通信与远程控制的物联网综合项目。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-01T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'digit-recognition','name'=>'手写数字识别','subtitle'=>'嵌入式端侧AI手写数字识别系统','category'=>'视觉识别','tags'=>['K210','AI','神经网络','视觉'],'description'=>'基于K210或类似AI芯片实现手写数字的端侧推理识别，从图像采集到模型部署的完整流程。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-02T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'ht32-control','name'=>'HT32嵌入式控制','subtitle'=>'基于HT32系列MCU的嵌入式控制应用','category'=>'工业控制','tags'=>['HT32','嵌入式','GPIO','定时器'],'description'=>'基于HT32系列微控制器的嵌入式控制项目，涵盖GPIO控制、定时器应用、中断处理等核心技术。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-03T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'patrol-robot','name'=>'智能巡检机器人','subtitle'=>'自主导航的智能巡检机器人平台','category'=>'机器人','tags'=>['STM32','电机驱动','超声波','巡线'],'description'=>'具备自主巡线、避障、路径规划能力的智能巡检机器人，搭载多种传感器实现环境感知。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-04T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'openmv-car','name'=>'OpenMV视觉小车','subtitle'=>'基于OpenMV的智能视觉循迹小车','category'=>'机器人','tags'=>['OpenMV','视觉','循迹','PID'],'description'=>'OpenMV摄像头实现色块/线条识别，驱动小车完成循迹、目标跟踪等视觉任务。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-05T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'fruit-detection','name'=>'果蔬成熟度检测','subtitle'=>'基于图像识别的果蔬成熟度智能检测','category'=>'视觉识别','tags'=>['K210','图像识别','分类','农业'],'description'=>'利用视觉识别技术对果蔬的颜色、形态进行分析，自动判断成熟度等级。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-06T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'env-monitor','name'=>'环境监测系统','subtitle'=>'多传感器融合的环境参数监测平台','category'=>'物联网','tags'=>['ESP32','DHT11','MQ-2','OLED'],'description'=>'集成温湿度、气体、光照等传感器，实时采集环境数据并通过OLED/云端展示与报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-07T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'target-tracking','name'=>'目标视觉追踪','subtitle'=>'基于视觉的运动目标实时追踪系统','category'=>'视觉识别','tags'=>['K210','目标追踪','PID','舵机'],'description'=>'摄像头实时检测并追踪运动目标，驱动舵机云台保持目标始终在画面中心。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-08T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'vibration-alert','name'=>'震动检测预警','subtitle'=>'基于加速度传感器的震动检测与预警','category'=>'工业控制','tags'=>['STM32','ADXL345','报警','监测'],'description'=>'利用加速度传感器检测异常震动，超阈值自动触发蜂鸣器/短信报警，适用于设备安全监测。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-09T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'race-timer','name'=>'赛事计时系统','subtitle'=>'高精度红外对管赛事计时终端','category'=>'工业控制','tags'=>['STM32','红外对管','定时器','LCD'],'description'=>'利用红外对管检测通过信号，高精度定时器实现毫秒级计时，LCD显示成绩与排名。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-10T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'haptic-glove','name'=>'体感模拟手套','subtitle'=>'多传感器体感数据采集手套','category'=>'机器人','tags'=>['MPU6050','弯曲传感器','蓝牙','手势'],'description'=>'集成IMU与弯曲传感器的体感手套，实时采集手部姿态与手指弯曲数据，无线传输控制。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-11T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'flame-vision','name'=>'火焰视觉识别','subtitle'=>'基于视觉的火焰检测与报警系统','category'=>'安防监控','tags'=>['K210','火焰检测','报警','视觉'],'description'=>'利用摄像头对火焰的颜色和形态特征进行识别，实时检测火情并触发报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-12T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'wireless-remote','name'=>'无线遥控终端','subtitle'=>'多通道无线遥控器设计','category'=>'物联网','tags'=>['NRF24L01','摇杆','STM32','无线'],'description'=>'基于NRF24L01的多通道无线遥控器，摇杆+按键操控，适用于机器人、小车等远程控制。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-13T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'smart-fishtank','name'=>'多端互联智能鱼缸','subtitle'=>'WiFi联网的智能鱼缸管理系统','category'=>'智能家居','tags'=>['ESP32','WiFi','水温','自动喂食'],'description'=>'自动控制水温、灯光、喂食，手机APP远程监控鱼缸状态，支持定时任务与异常报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-14T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'library-fire','name'=>'图书馆火焰监测','subtitle'=>'图书馆专用火焰与烟雾监测系统','category'=>'安防监控','tags'=>['STM32','MQ-2','火焰传感器','报警'],'description'=>'针对图书馆场景的火焰与烟雾监测系统，多传感器融合，超阈值自动报警与联动消防。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-15T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'child-detect','name'=>'车载防滞留系统','subtitle'=>'基于人体检测的车内防滞留报警装置','category'=>'安防监控','tags'=>['STM32','红外','温度','GSM'],'description'=>'利用红外人体检测与温度监测，判断车内是否有人滞留，超温自动开窗通风并短信报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-16T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'smart-parking','name'=>'智能停车管理','subtitle'=>'基于超声波的车位检测与管理系统','category'=>'物联网','tags'=>['STM32','超声波','OLED','车位'],'description'=>'超声波传感器检测车位占用状态，LED引导空闲车位，OLED显示剩余车位信息。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-17T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'face-access','name'=>'人脸识别门禁','subtitle'=>'K210人脸识别智能门禁终端','category'=>'安防监控','tags'=>['K210','人脸识别','舵机','门禁'],'description'=>'K210实现人脸录入与比对，识别通过后驱动舵机开锁，OLED显示识别结果与状态。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-18T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'rfid-warehouse','name'=>'RFID仓储管理','subtitle'=>'基于RFID的智能仓储出入库管理','category'=>'物联网','tags'=>['STM32','RFID','RC522','LCD'],'description'=>'RFID射频标签实现物料的自动识别与出入库登记，LCD显示库存状态，支持查询与统计。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-19T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'smart-locker','name'=>'智能储物柜','subtitle'=>'多柜门智能储物管理终端','category'=>'智能家居','tags'=>['STM32','电磁锁','矩阵键盘','LCD'],'description'=>'矩阵键盘输入密码或刷卡开柜，电磁锁控制多个柜门，LCD显示操作提示与状态。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-20T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'express-sort','name'=>'智能快递分拣','subtitle'=>'基于视觉/RFID的快递自动分拣系统','category'=>'工业控制','tags'=>['STM32','舵机','传送带','分拣'],'description'=>'通过视觉或RFID识别快递信息，驱动传送带与舵机将快递自动分拣到对应区域。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-21T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'device-monitor','name'=>'设备状态监测','subtitle'=>'工业设备运行状态实时监测终端','category'=>'工业控制','tags'=>['STM32','电流','振动','WiFi'],'description'=>'采集设备的电流、振动、温度等运行参数，异常时报警，支持WiFi远程查看状态。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-22T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'game-console','name'=>'掌上游戏终端','subtitle'=>'STM32驱动LCD的嵌入式小游戏终端','category'=>'其他','tags'=>['STM32','LCD','按键','游戏'],'description'=>'STM32驱动LCD屏幕实现贪吃蛇、俄罗斯方块等经典小游戏，按键操控，蜂鸣器音效。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-23T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'face-express-box','name'=>'人脸识别快递柜','subtitle'=>'人脸识别取件的智能快递柜','category'=>'安防监控','tags'=>['K210','人脸识别','电磁锁','快递'],'description'=>'刷脸取件的智能快递柜，K210实现人脸注册与比对，匹配成功自动开对应柜门。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-24T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'remote-light','name'=>'远程灯光控制','subtitle'=>'WiFi远程灯光开关与调色控制','category'=>'智能家居','tags'=>['ESP32','WiFi','PWM','APP'],'description'=>'通过WiFi连接手机APP，实现远程灯光开关、亮度调节与RGB调色。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-25T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'logistics-gps','name'=>'物流定位系统','subtitle'=>'GPS+GSM的物流实时定位追踪','category'=>'物联网','tags'=>['STM32','GPS','GSM','定位'],'description'=>'GPS模块获取实时位置，通过GSM/4G上传至云端，实现物流运输的全程追踪。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-26T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'led-strip','name'=>'流水灯带控制','subtitle'=>'WS2812 RGB灯带炫彩控制','category'=>'其他','tags'=>['STM32','WS2812','PWM','灯效'],'description'=>'驱动WS2812可编程灯带实现流水、呼吸、彩虹等多种炫彩灯效，支持按键切换模式。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-27T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'motor-drive','name'=>'电机驱动控制','subtitle'=>'直流/步进电机驱动与PID调速','category'=>'工业控制','tags'=>['STM32','L298N','PID','编码器'],'description'=>'L298N驱动直流/步进电机，编码器反馈转速，PID算法实现精准调速与位置控制。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-28T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'auto-patrol-car','name'=>'自动巡检小车','subtitle'=>'多传感器自主巡线避障小车','category'=>'机器人','tags'=>['STM32','红外','超声波','电机'],'description'=>'红外传感器巡线、超声波避障、电机驱动底盘的自动巡检小车，支持多种运动模式。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-29T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'area-detect','name'=>'区域目标识别','subtitle'=>'特定区域的目标检测与计数','category'=>'视觉识别','tags'=>['OpenMV','目标检测','计数','区域'],'description'=>'摄像头对特定区域进行目标检测与计数，适用于人流统计、物料计数等场景。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-04-30T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'password-locker','name'=>'密码储物柜','subtitle'=>'矩阵键盘密码开锁储物柜','category'=>'智能家居','tags'=>['STM32','矩阵键盘','电磁锁','OLED'],'description'=>'矩阵键盘输入密码，密码正确驱动电磁锁开柜，OLED显示操作状态，支持密码修改。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-05-01T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'micro-expression','name'=>'微表情识别','subtitle'=>'基于AI的面部微表情情绪识别','category'=>'视觉识别','tags'=>['K210','AI','表情识别','分类'],'description'=>'AI模型识别面部微表情，实现喜怒哀乐等情绪的自动分类，应用于心理辅助等场景。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-05-02T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'license-plate','name'=>'车牌智能识别','subtitle'=>'基于视觉的车牌自动识别系统','category'=>'视觉识别','tags'=>['K210','OCR','车牌','图像处理'],'description'=>'摄像头采集车牌图像，经图像处理与字符识别实现车牌号的自动识别与记录。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-05-03T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'temp-terminal','name'=>'智能测温终端','subtitle'=>'非接触式红外测温终端','category'=>'医疗辅助','tags'=>['STM32','MLX90614','OLED','报警'],'description'=>'MLX90614红外测温模块实现非接触体温测量，OLED显示温度值，超温自动报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-05-04T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'auto-express-sort','name'=>'自动快递分拣','subtitle'=>'传送带式自动快递分拣线','category'=>'工业控制','tags'=>['STM32','传送带','光电','舵机'],'description'=>'光电传感器检测包裹到位，主控判断目的地后驱动舵机将包裹推入对应分拣通道。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-05-05T00:00:00+08:00','updatedAt'=>date('c')],

    // ═══════ 三月项目 ═══════
    ['id'=>'bike-lock','name'=>'自行车电子锁','subtitle'=>'蓝牙/密码双模式电子车锁','category'=>'智能家居','tags'=>['STM32','蓝牙','电磁锁','低功耗'],'description'=>'支持蓝牙解锁与密码解锁的电子自行车锁，低功耗设计，支持异常震动报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-01T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'material-counter','name'=>'便携式物料计数器','subtitle'=>'红外对管的便携物料计数装置','category'=>'工业控制','tags'=>['STM32','红外对管','LCD','计数'],'description'=>'红外对管检测物料通过，自动计数并在LCD上显示，支持数据清零与统计导出。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-02T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'iv-alarm','name'=>'便携式输液报警器','subtitle'=>'输液余量检测与滴速报警装置','category'=>'医疗辅助','tags'=>['STM32','红外','蜂鸣器','医疗'],'description'=>'红外传感器检测输液瓶余量与滴速，液位过低或滴速异常时蜂鸣器报警提醒护理人员。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-03T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'joystick-arm','name'=>'摇杆控制机械臂','subtitle'=>'双摇杆遥控多自由度机械臂','category'=>'机器人','tags'=>['STM32','摇杆','舵机','PWM'],'description'=>'双摇杆模块分别控制机械臂各关节运动，PWM舵机实现多自由度联动操控。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-04T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'face-door-lock','name'=>'人脸识别智能门锁','subtitle'=>'K210人脸识别的家用智能门锁','category'=>'安防监控','tags'=>['K210','人脸识别','舵机','门锁'],'description'=>'K210人脸识别模块集成到门锁系统，刷脸开门，支持多人注册与陌生人报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-05T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'smart-kitchen','name'=>'智能厨房系统','subtitle'=>'烟雾/燃气监测的智能厨房终端','category'=>'智能家居','tags'=>['STM32','MQ-2','MQ-4','风扇','报警'],'description'=>'MQ系列传感器监测烟雾与可燃气体浓度，超标自动启动排风扇并声光报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-06T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'smart-heater','name'=>'智慧热水器控制','subtitle'=>'温度PID调控的智能热水器终端','category'=>'智能家居','tags'=>['STM32','DS18B20','PID','继电器'],'description'=>'DS18B20测温，PID算法精准控温，继电器控制加热元件，LCD设置目标温度。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-07T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'env-station','name'=>'智慧环境监测站','subtitle'=>'多参数环境数据采集站','category'=>'物联网','tags'=>['ESP32','DHT11','BH1750','MQ-135'],'description'=>'采集温湿度、光照、空气质量等环境参数，WiFi上传云端，支持手机远程查看。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-08T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'mini-safe','name'=>'微型智能保险箱','subtitle'=>'指纹/密码双重验证微型保险箱','category'=>'安防监控','tags'=>['STM32','指纹','矩阵键盘','电磁锁'],'description'=>'指纹模块+矩阵键盘双重验证，电磁锁控制保险箱开闭，异常开启触发报警。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-09T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'face-safe','name'=>'人脸识别保险箱','subtitle'=>'K210人脸验证的高安全保险箱','category'=>'安防监控','tags'=>['K210','人脸识别','电磁锁','保险箱'],'description'=>'K210人脸识别作为保险箱的生物特征验证方式，只有注册用户才能开启。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-10T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'smart-door-lock','name'=>'智能门锁系统','subtitle'=>'多模式解锁的智能门锁','category'=>'智能家居','tags'=>['STM32','RFID','密码','蓝牙'],'description'=>'支持密码、RFID刷卡、蓝牙等多种解锁方式的智能门锁系统。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-11T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'digital-lock','name'=>'电子密码锁','subtitle'=>'矩阵键盘电子密码锁','category'=>'智能家居','tags'=>['STM32','矩阵键盘','舵机','OLED'],'description'=>'4×4矩阵键盘输入密码，匹配成功舵机开锁，OLED显示状态，支持密码修改与锁定。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-12T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'pid-temp','name'=>'PID精准控温系统','subtitle'=>'PID算法精准温度控制平台','category'=>'工业控制','tags'=>['STM32','PID','DS18B20','PWM'],'description'=>'PID闭环算法实现温度的精准控制，DS18B20反馈实际温度，PWM调节加热功率。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-13T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'thermostat','name'=>'恒温控制器','subtitle'=>'目标温度恒温维持控制器','category'=>'工业控制','tags'=>['STM32','NTC','继电器','LCD'],'description'=>'NTC热敏电阻测温，继电器控制加热/制冷设备，自动维持设定的目标温度。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-14T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'k210-face-lock','name'=>'K210人脸门锁','subtitle'=>'K210人脸识别一体化门锁方案','category'=>'安防监控','tags'=>['K210','人脸识别','舵机','一体化'],'description'=>'K210一体化人脸识别门锁方案，从人脸注册、比对到舵机开锁的完整流程。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-15T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'vision-patrol','name'=>'视觉巡检小车','subtitle'=>'搭载摄像头的智能巡检小车','category'=>'机器人','tags'=>['K210','巡线','视觉','电机'],'description'=>'K210摄像头实现视觉巡线与目标识别，替代传统红外巡线，提升环境适应能力。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-16T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'stm32h7-dev','name'=>'STM32H7高性能开发','subtitle'=>'STM32H7系列高性能嵌入式开发','category'=>'工业控制','tags'=>['STM32H7','高性能','DSP','多媒体'],'description'=>'基于STM32H7高性能MCU的开发项目，利用其强大的运算能力处理复杂算法与多媒体任务。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-03-17T00:00:00+08:00','updatedAt'=>date('c')],

    // ═══════ 综合项目 ═══════
    ['id'=>'condensate','name'=>'冷凝水处理系统','subtitle'=>'空调冷凝水回收利用系统','category'=>'智能家居','tags'=>['STM32','水泵','液位','继电器'],'description'=>'检测冷凝水液位，自动控制水泵将冷凝水回收利用，实现节能环保。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-01T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'nursing-bed','name'=>'智慧护理床','subtitle'=>'多功能智能护理床控制系统','category'=>'医疗辅助','tags'=>['STM32','电机','压力传感器','语音'],'description'=>'电机驱动床体升降与翻转，压力传感器检测体位，支持语音控制与一键呼叫。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-02T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'auto-feeder','name'=>'自动喂鱼器','subtitle'=>'定时自动投食的智能喂鱼装置','category'=>'智能家居','tags'=>['Arduino','舵机','RTC','定时'],'description'=>'RTC实时时钟定时触发，舵机驱动投食机构自动投放鱼食，支持多时段设置。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-03T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'dorm-security','name'=>'宿舍安防系统','subtitle'=>'多传感器宿舍安全监测系统','category'=>'安防监控','tags'=>['STM32','烟雾','门磁','GSM'],'description'=>'集成烟雾、门磁、人体红外等传感器，异常时声光报警并短信通知。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-04T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'heart-rate','name'=>'智能心率检测仪','subtitle'=>'光电式心率实时检测终端','category'=>'医疗辅助','tags'=>['STM32','MAX30102','OLED','滤波'],'description'=>'MAX30102光电式心率传感器采集脉搏信号，滤波算法提取心率值，OLED实时显示。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-05T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'smart-irrigation','name'=>'智能灌溉系统','subtitle'=>'土壤湿度感知的自动灌溉','category'=>'物联网','tags'=>['ESP32','土壤湿度','水泵','WiFi'],'description'=>'土壤湿度传感器检测墒情，低于阈值自动启动水泵灌溉，WiFi远程监控。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-06T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'bio-incubator','name'=>'智能生物培养箱','subtitle'=>'温湿度精控的生物培养环境','category'=>'工业控制','tags'=>['STM32','PID','DHT11','加热片'],'description'=>'PID算法精准控制培养箱内温湿度，创造稳定的生物培养环境，LCD实时显示参数。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-07T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'transport-car','name'=>'智能运载定位车','subtitle'=>'GPS定位的自主运载小车','category'=>'机器人','tags'=>['STM32','GPS','电机','遥控'],'description'=>'GPS定位+电机驱动的智能运载小车，支持路径规划与手动遥控切换。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-08T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'mold-alert','name'=>'霉变预警系统','subtitle'=>'温湿度监测的粮仓霉变预警','category'=>'物联网','tags'=>['STM32','DHT11','蜂鸣器','阈值'],'description'=>'监测粮仓温湿度，当条件达到霉变风险阈值时自动报警，防止粮食损失。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-09T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'desktop-pet','name'=>'桌面电子宠物','subtitle'=>'LCD动画显示的桌面电子宠物','category'=>'其他','tags'=>['STM32','LCD','动画','按键'],'description'=>'LCD屏幕显示可爱的电子宠物动画，按键与宠物互动（喂食、玩耍），表情随状态变化。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-10T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'accessible-water','name'=>'无障碍饮水终端','subtitle'=>'感应式无障碍自动饮水装置','category'=>'医疗辅助','tags'=>['STM32','红外','水泵','无障碍'],'description'=>'红外感应手势自动出水，无需按压操作，方便行动不便人士使用。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-11T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'blind-spot-radar','name'=>'盲区雷达监测','subtitle'=>'超声波盲区障碍物检测系统','category'=>'安防监控','tags'=>['STM32','超声波','蜂鸣器','LED'],'description'=>'多路超声波传感器扫描盲区，检测到障碍物时声光分级预警，辅助安全通行。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-12T00:00:00+08:00','updatedAt'=>date('c')],
    ['id'=>'smart-aquarium','name'=>'智慧鱼缸系统','subtitle'=>'温度/灯光/喂食一体化鱼缸','category'=>'智能家居','tags'=>['Arduino','DS18B20','舵机','继电器'],'description'=>'集成水温监控、自动照明、定时喂食的一体化智能鱼缸管理系统。','specs'=>'','level'=>'normal','images'=>[],'createdAt'=>'2026-02-13T00:00:00+08:00','updatedAt'=>date('c')],
];

file_put_contents(PRODUCTS_FILE, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo '<h2 style="font-family:sans-serif;color:#333;">初始化完成！</h2>';
echo '<p style="font-family:sans-serif;color:#666;">共写入 <strong>' . count($products) . '</strong> 个产品到 data/products.json</p>';
echo '<p style="font-family:sans-serif;color:#e44;"><strong>请立即删除此文件 (seed.php)！</strong></p>';
echo '<p><a href="admin.html" style="font-family:sans-serif;">→ 进入管理后台</a></p>';
