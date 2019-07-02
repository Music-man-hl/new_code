# 安装
代码拉取到本地后需要安装依赖库,执行:
``` 
composer install 
```

Redis缓存机制 
===============
> 线上/测试（o/t） 前/后台（f/b） 

> 普通Key（ 线上 + 前台 + 缓存类型 + md5(key) ）
 + of:captcha_md5(key)  验证码缓存的key
 + of:ip:md5(ip) 每小时限制请求接口多少次
 + of:vaild:ip:md5(ip) 每天限制验证码接口多少次
 + of:dbConfigs 数据库配置
 + of:token:md5(微妙+,+rand6位)用户的auth_token和refresh_token 
 
> 第三方Key （缓存类型 + md5(key) 主要所有的站通用）
 + third_md5(key)

File缓存机制
===============
> 缓存类型 + md5(key)

Hook规范
===============
> hook目录下建立工厂方法调用各自目录下的类
> 目录下的类静态调用logic和model实现复用