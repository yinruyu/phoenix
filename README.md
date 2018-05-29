### phoenix 框架指南
### 框架开发
    设置 export "PHOENIX_ENV=dev" //表示phonex 目录 /home/{$USER}/devspace/phoenix
### 依赖 phoenix框架开的项目需要设置
    export "PHOENIX_ENV=product" //表示phonex 目录 /data/x/tools/phoenix
    export "PRJ_ENV=dev" //表示环境变量读取开发环境
### 意义
    根据环境变量进行强制约束，避免因为启动命令传入错误环境参数导致不可预知的灾难。

### web接口启动/停止
     px [conf|start|stop] -s api -e dev
     
     conf   生成fpm、nginx、php_ini的配置文件
     start  fpm生成socket,nginx 配置生效
     stop   关闭fpm,nginx项目配置文件失效
### daemon 守护进程启动/停止
     px     [conf|start|stop] -s console -e dev
     conf   生成php_ini文件
     start  启动守护进程，守护脚本
     stop   关闭守护进程，守护脚本退出
     
### rc 项目基于git生成tag
     px     [rc]
