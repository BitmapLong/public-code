<?php
ini_set('memory_limit', '256M'); //升级为256M内存
class CliParser
{
    private $idx    = 0;
    private $args   = array();
    private $script = '';

    public function __construct()
    {
    }

    public function parse()
    {
        if (!isset($_SERVER['argv'])) return;

        $this->script = $_SERVER['argv'][0];
        unset($_SERVER['argv'][0]);

        if (!array_walk($_SERVER['argv'], array($this, 'doParse'))) return;
        $args = array();
        foreach ($this->args as $val) {
            if (is_string($val)) {
                $args[$val] = true;
            } else if (is_array($val)) {
                foreach ($val as $k => $v) {
                    if (is_string($k)){
                        if(is_numeric($v)){
                            $args[$k] = (int)$v;
                        }else{
                            $args[$k] = $v;
                        }
                    }
                }
            }
        }
        $this->args = $args;
        unset($args);
    }

    public function getArgs($key)
    {
        if ( $this->hasArgs($key) ) {
            return $this->args[$key];
        }

        return false;
    }

    public function hasArgs($key)
    {
        return array_key_exists($key, $this->args);
    }

    public function getAllArgs()
    {
        return $this->args;
    }

    public function getScript()
    {
        return $this->script;
    }

    private function doParse($item)
    {
        $key = '';
        $val = '';

        if ( $item[0] == '-' && $item[1] == '-' && $item[2] != '-' ) { //--item
            $key = substr($item, 2);
        } else if ( $item[0] == '-' && $item[1] != '-' ) { //-item
            $key = substr($item, 1, 1);
        } else if( $item[0] != '-' ) {
            $val = $item;
        }
        
        if ( $key != '' ) {
            $this->args[++$this->idx] = $key;
        }else if ( $val != '' && $this->idx > 0 ) {
            $_key = $this->args[$this->idx];
            if ( is_string($_key) ) {
                $this->args[$this->idx] = array($_key => $val);
            }else if(is_array($_key)){
                $key = array_key_last($_key);
                if(is_array($_key[$key])){
                    $_key[$key][] = $val;
                }else{
                    $_key[$key] = array($_key[$key], $val);
                }
                $this->args[$this->idx] = $_key;
            }
        }
    }
}

function mkdirs($path){
	if (!is_dir(dirname($path))){
		mkdirs(dirname($path));
	}
	if(!file_exists($path)){
		mkdir($path);
	}
}

function createRedisConfigFile($port, $path_root, $temp){
    $path = $path_root . '/' . $port;
    mkdirs($path);
    mkdirs($path.'/db');
    $temp = str_replace('{port}', $port, $temp);
    if(file_put_contents($path . '/redis.conf', $temp)){
        //启动redis进程
        exec('/www/server/redis/src/redis-server  '.$path.'/redis.conf', $output, $code);
        if(count($output) > 0) print_r(join(PHP_EOL, $output).PHP_EOL);
        if($code != 0) die('['.$port.'] 启动redis进程失败! 请检查错误原因!' . PHP_EOL);
        echo 'redis [' . $port . '] 启动成功 √' . PHP_EOL;
    }else{
        die('redis 配置文件写入失败!' . PHP_EOL);
    }
}

define('TEMP', <<<TEMP
#默认ip为0.0.0.0
bind {bind}
#端口
port {port}
#访问密码
requirepass {pass}
#启用集群模式
cluster-enabled yes
#集群的配置
cluster-config-file {path_root}/{port}/nodes.conf
#超时时间
cluster-node-timeout {timeout}
#如果设置成0，则无论从节点与主节点失联多久，从节点都会尝试升级成主节点。如果设置成正数，则cluster-node-timeout乘以cluster-slave-validity-factor得到的时间，是从节点与主节点失联后，此从节点数据有效的最长时间，超过这个时间，从节点不会启动故障迁移。假设cluster-node-timeout=5，cluster-slave-validity-factor=10，则如果从节点跟主节点失联超过50秒，此从节点不能成为主节点。注意，如果此参数配置为非0，将可能出现由于某主节点失联却没有从节点能顶上的情况，从而导致集群不能正常工作，在这种情况下，只有等到原来的主节点重新回归到集群，集群才恢复运作。
cluster-slave-validity-factor 10
#主节点需要的最小从节点数，只有达到这个数，主节点失败时，它从节点才会进行迁移。更详细介绍可以看本教程后面关于副本迁移到部分。
cluster-migration-barrier 1
#在部分key所在的节点不可用时，如果此参数设置为"yes"(默认值), 则整个集群停止接受操作；如果此参数设置为”no”，则集群依然为可达节点上的key提供读操作。
cluster-require-full-coverage no

appendonly yes
#后台运行
daemonize yes
#非保护模式
protected-mode no
#进程pid存放文件
pidfile  {path_root}/{port}/redis.pid
#日志级别
#    debug:会打印生成大量信息，适用于开发/测试阶段
#    verbose:包含很多不太有用的信息，但是不像debug级别那么混乱
#    notice:适度冗长，适用于生产环境
#    warning:仅记录非常重要、关键的警告消息
#loglevel warning
#日志存储路径
#logfile  {path_root}/{port}/redis.log
#持久化数据存储路径
dir  {path_root}/{port}/db
#持久化数据存储文件名
dbfilename dump.rdp
TEMP
);


$args = new CliParser();
$args->parse(); //解析参数

if(!$args->hasArgs('action')){
    die('需要指定动作 action' . PHP_EOL);
}
$action = $args->getArgs('action');

switch ($action) {
    case 'start':
        $temp = TEMP;
        $bind = '0.0.0.0';
        $port = 6379;
        $pass = '';
        $timeout = 15000;
        $replicas = 1;
        $path_root = dirname(__FILE__);
        if($args->hasArgs('port')){
            $port = $args->getArgs('port');
            if(is_string($port) && preg_match('/([0-9]+)-([0-9]+)/', $port, $m)){
                $port = array();
                for($i = (int)$m[1]; $i < (int)$m[2] + 1; $i++){
                    $port[] = $i;
                }
            }
            if(!is_array($port) && is_numeric($port)) die('port 格式错误');
        }
        if($args->hasArgs('pass')){
            $pass = $args->getArgs('pass');
        }else{
            $temp = str_replace('requirepass', '#requirepass', $temp);
        }
        if($args->hasArgs('path_root')){
            $path_root = $args->getArgs('path_root');
        }
        if($args->hasArgs('replicas')){
            $replicas = $args->getArgs('replicas');
        }
        if(file_exists(dirname(__FILE__) . '/redis_config_path.txt') && file_exists(dirname(__FILE__) . '/redis_pass.txt') && file_exists(dirname(__FILE__) . '/redis_bind.txt') && file_exists(dirname(__FILE__) . '/redis_port.txt')){
            $root = dirname(__FILE__);
            //读取redis配置文件路径
            $redis_config_path = file_get_contents($root . '/redis_config_path.txt');
            //读取redis绑定的ip
            $bind = file_get_contents($root . '/redis_bind.txt');
            //读取redis设定的pass
            $pass = file_get_contents($root . '/redis_pass.txt');
            //读取redis启动的端口
            $port = explode(' ', file_get_contents($root . '/redis_port.txt'));
            $clusterServerList = array();
            foreach ($port as $p){
                $clusterServerList[] = $bind . ':' . $p;
                $path = $redis_config_path . '/' . $p;
                //启动redis进程
                exec('/www/server/redis/src/redis-server '.$path.'/redis.conf', $output, $code);
                if(count($output) > 0) print_r(join(PHP_EOL, $output).PHP_EOL);
                if($code != 0) die('['.$p.'] 启动redis进程失败! 请检查错误原因!' . PHP_EOL);
                echo 'redis [' . $p . '] 启动成功 √' . PHP_EOL;
            }
            die();
        }
        file_put_contents(dirname(__FILE__) . '/redis_config_path.txt', $path_root);
        file_put_contents(dirname(__FILE__) . '/redis_bind.txt', $bind);
        file_put_contents(dirname(__FILE__) . '/redis_pass.txt', $pass);
        file_put_contents(dirname(__FILE__) . '/redis_port.txt', is_array($port) ? join(' ', $port) : $port);
        
        //redis配置模板设置
        $temp = str_replace('{bind}', $bind, $temp);
        $temp = str_replace('{pass}', $pass, $temp);
        $temp = str_replace('{timeout}', $timeout, $temp);
        $temp = str_replace('{path_root}', $path_root, $temp);
        
        //创建路径
        mkdirs($path_root);
        $clusterServerList = array();
        if(is_array($port)){
            foreach ($port as $v){
                $clusterServerList[] = $bind . ':' . $v;
                createRedisConfigFile($v, $path_root, $temp);
            }
        }else{
            $clusterServerList[] = $bind . ':' . $port;
            createRedisConfigFile($port, $path_root, $temp);
        }
        //创建redis集群
        exec('yes yes | head -1 | /www/server/redis/src/redis-cli --cluster create '.join(' ', $clusterServerList). (empty($pass) ? '' : ' -a ' . $pass) . ' --cluster-replicas ' . $replicas, $output, $code);
        if(count($output) > 0) print_r(join(PHP_EOL, $output).PHP_EOL);
        if($code != 0) die('创建redis集群失败! 请检查错误原因!' . PHP_EOL);
        die('redis 集群全部开启完毕!' . PHP_EOL);
        break;
    case 'stop':
        $root = dirname(__FILE__);
        //读取redis配置文件路径
        $redis_config_path = file_get_contents($root . '/redis_config_path.txt');
        //读取redis启动的端口
        $port = explode(' ', file_get_contents($root . '/redis_port.txt'));
        //读取redis的进程pid
        foreach ($port as $p){
            $pid = (int)file_get_contents($redis_config_path . '/' . $p . '/redis.pid');
            //杀掉进程
            exec('kill -9 ' . $pid, $output, $code);
            if(count($output) > 0) print_r(join(PHP_EOL, $output).PHP_EOL);
            if($code != 0) die('redis ['.$p.'] - pid: '.$pid.' 停止失败! 请检查错误原因!' . PHP_EOL);
            file_put_contents($redis_config_path . '/' . $p . '/redis.pid', '');
            echo 'redis ['.$p.'] - pid: '.$pid.' 停止成功' . PHP_EOL;
        }
        break;
    case 'luck':
        exec('ps -ef | grep redis | grep cluster', $output, $code);
        if(count($output) > 0) print_r(join(PHP_EOL, $output).PHP_EOL);
        if($code != 0) die('查询redis进程失败! 请检查错误原因!' . PHP_EOL);
        echo '查询redis进程成功' . PHP_EOL;
        break;
    case 'redisnum':
        exec('ps -ef | grep redis | grep cluster |grep -v grep|wc -l', $output, $code);
        if(count($output) > 0) print_r(join(PHP_EOL, $output).PHP_EOL);
        if($code != 0) die('查询redis数量失败! 请检查错误原因!' . PHP_EOL);
        echo '查询redis数量成功' . PHP_EOL;
        break;
    default:
        // code...
        break;
}
exit();
