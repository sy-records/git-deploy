# Git Deploy

[![Latest Stable Version](https://poser.pugx.org/sy-records/git-deploy/v)](//packagist.org/packages/sy-records/git-deploy) [![Total Downloads](https://poser.pugx.org/sy-records/git-deploy/downloads)](//packagist.org/packages/sy-records/git-deploy) [![Latest Unstable Version](https://poser.pugx.org/sy-records/git-deploy/v/unstable)](//github.com/sy-records/git-deploy) 
[![License](https://poser.pugx.org/sy-records/git-deploy/license)](LICENSE)

🍭 Using WebHooks to automatically pull code.

## Support

* [x] GitHub
* [x] Gitee

## 依赖

* php >= 5.6
* ext-swoole

## 安装

```shell
composer create-project sy-records/git-deploy
```

## 配置

1. 修改配置文件`config.json`

`server`对应的是`Swoole\Http\Server`的相关配置

* `ip`：IP地址  
* `port`：端口  
* `mode`：启动模式 `SWOOLE_BASE/SWOOLE_PROCESS`  
* `settings`：Server的配置  

> 正式运行时需要启动守护进程，将`daemonize`修改为`1`

```json
"server": {
  "ip": "0.0.0.0",
  "port": 9666,
  "mode": 1,
  "settings": {
    "worker_num": 1,
    "daemonize": 0
  }
},
```

`sites`对应的是项目的仓库等信息

分为`github`和`gitee`，`key`是仓库名称，支持多个仓库。

* `secret`/`password`：密钥/密码；`github`使用`secret`，`gitee`的 WebHook 密码使用`password`，签名密钥使用`secret`
* `ref`：分支  
* `event_name`：事件名称；`github`为`push`，`gitee`为`push_hooks`
* `shells`：需要执行的脚本

```json
"sites": {
  "github": {
      "sy-records/git-deploy": {
        "secret": "password",
        "ref": "refs/heads/master",
        "event_name": "push",
        "shells": [
          "git -C /yourpath/git-deploy pull"
        ]
      }
  },
  "gitee": {
      "sy-records/git-deploy": {
        "password": "password",
        "ref": "refs/heads/master",
        "event_name": "push_hooks",
        "shells": [
          "git -C /yourpath/git-deploy pull"
        ]
      }
  }
}
```

2. 填写WebHook

URL：`http://ip:port/github` or `http://ip:port/gitee`  

Secret/PassWord：对应`config.json`中的`secret/password`

## 启动

```shell
php start.php
```
