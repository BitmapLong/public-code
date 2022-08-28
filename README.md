# 公用代码
记录一些实用小代码

redis_cluster.php   redis集群管理,具备自动创建,停止,启动

[需要]

  redis >= 7.0
  
  php >= 5.6
  
[使用方法]

```
  参数介绍:
    --action
      start  启动/自动创建
        --port  监听端口(最少6个)   6379 6380 6381 ... (多个端口) 或 6379-6390 (范围端口)
        --bind  邦定的ip,默认为 0.0.0.0 可选
        --pass  密码 默认为空 可选
        --path_root redis集群配置文件路径  默认为脚本路径
        --replicas  为每个创建的主服务器创建多少个副本   默认为 1
      stop  停止
      luck  查看redis集群进程
      redisnum 查看redis集群节点数量
 ```
 
[使用例子]

```
linux> php redis_cluster.php --action start --port 6379-6384 --pass abc123!
```
