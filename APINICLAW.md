# ApiniClaw 账号服务

这是从 [BuildAdmin](https://github.com/build-admin/buildadmin) `v2` fork 的账号后台，给桌面端用。桌面应用、OpenClaw 和安装包更新不在这个仓库。

## 桌面端登录

不使用 BuildAdmin 默认的用户名密码。桌面端调用：

`POST /api/user/emailCheckIn`

请求头按 BuildAdmin 前台接口习惯。本机调试可加 `server: 1`，或在地址后加 `?server=1`。

发验证码：

```json
{ "tab": "send", "email": "you@example.com" }
```

校验并登录。邮箱不存在会自动注册会员：

```json
{ "tab": "login", "email": "you@example.com", "captcha": "123456" }
```

成功时响应是 BuildAdmin 的 `{ code, msg, data }`。令牌在 `data.userInfo.token` 和 `data.userInfo.refresh_token`。

没配 SMTP 且 `app_debug` 打开时，发码接口的 `data.debug_code` 会带上验证码，方便本地试。

## 还没接上的

安装助手的模型转发、关于页、模型预设。登录这条先能跑通。

上游：`git@github.com:build-admin/buildadmin.git`，分支 `v2`。
