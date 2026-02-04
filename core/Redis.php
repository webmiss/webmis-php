<?php
namespace Core;

use App\Config\Redis as RedisCfg;

/* Redis */
class Redis extends Base {

  static public $conn = null;     // 连接
  private $name = 'Redis';        // 名称
  private $config = [];           // 配置

  /* 构造函数 */
  public function __construct(string $name='default') {
    // 配置
    $this->config = RedisCfg::config($name);
    // 连接
    if(!self::$conn) $this->ReidsConn();
  }

  /* 获取连接 */
  public function ReidsConn(): object|null {
    if(!self::$conn) {
      try{
        self::$conn = new \Redis();
        self::$conn->pconnect($this->config['host'], $this->config['port'], $this->config['socket_timeout'], 'redis_pool_unique', 0, 3);
        if($this->config['password']) self::$conn->auth($this->config['password']);
        self::$conn->select($this->config['db']);
      }catch (\Exception $e){
        self::Print('[ '.$this->name.' ]', $e->getMessage());
        return null;
      }
    }
    return self::$conn;
  }

  /* 添加 */
  function Set(string $key, string $val): bool|null {
    if(!self::$conn) return null;
    return self::$conn->set($key, $val);
  }

  /* 自增 */
  function Incr(string $key): string|bool| null {
    if(!self::$conn) return null;
    return self::$conn->incr($key);
  }

  /* 自减 */
  function Decr(string $key): string|bool|null {
    if(!self::$conn) return null;
    return self::$conn->decr($key);
  }

  /* 获取 */
  function Gets(string $key): string|bool|null {
    if(!self::$conn) return null;
    return self::$conn->get($key);
  }

  /* 删除 */
  function Del(string ...$key): int|null {
    if(!self::$conn) return null;
    return self::$conn->del($key);
  }

  /* 是否存在 */
  function Exist(string $key): int|null {
    if(!self::$conn) return null;
    return self::$conn->exists($key);
  }

  /* 设置过期时间(秒) */
  function Expire(string $key, int $ttl): bool|null {
    if(!self::$conn) return null;
    return self::$conn->expire($key, $ttl);
  }

  /* 获取过期时间(秒) */
  function Ttl(string $key): int|null {
    if(!self::$conn) return null;
    return @self::$conn->ttl($key);
  }

  /* 获取长度 */
  function StrLen(string $key): int|null {
    if(!self::$conn) return null;
    return self::$conn->strlen($key);
  }

  /* 哈希(Hash)-添加 */
  function HSet(string $name, string $key, $val): int|null {
    if(!self::$conn) return null;
    return self::$conn->hset($name, $key, $val);
  }

  /* 哈希(Hash)-删除 */
  function HDel(string $name, string ...$key): int|null {
    if(!self::$conn) return null;
    return self::$conn->hdel($name, $key);
  }

  /* 哈希(Hash)-获取 */
  function HGet(string $name, string $key): string|bool|null {
    if(!self::$conn) return null;
    return self::$conn->hget($name, $key);
  }

  /* 哈希(Hash)-获取全部 */
  function HGetAll(string $name): array|null {
    if(!self::$conn) return null;
    return self::$conn->hgetall($name);
  }

  /* 哈希(Hash)-获取全部值 */
  function HVals(string $name): array|null {
    if(!self::$conn) return null;
    return self::$conn->hvals($name);
  }

  /* 哈希(Hash)-是否存在 */
  function HExist(string $name, string $key): int|null {
    if(!self::$conn) return null;
    return self::$conn->hexists($name, $key);
  }

  /* 哈希(Hash)-获取长度 */
  function HLen(string $name): int|null {
    if(!self::$conn) return null;
    return self::$conn->hlen($name);
  }

  /* 列表(List)-写入 */
  function LPush(string $key, $val): int|null {
    if(!self::$conn) return null;
    return self::$conn->lpush($key, $val);
  }
  function RPush(string $key, $val): int|null {
    if(!self::$conn) return null;
    return self::$conn->rpush($key, $val);
  }

  /* 列表(List)-读取 */
  function LRange($key, $start, $end): array|null {
    if(!self::$conn) return null;
    return self::$conn->lRange($key, $start, $end);
  }
  function LPop($key): string|bool|null {
    if(!self::$conn) return null;
    return self::$conn->lPop($key);
  }
  function RPop($key): string|bool|null {
    if(!self::$conn) return null;
    return self::$conn->rPop($key);
  }
  function BRPop($key, $timeout): array|bool|null {
    if(!self::$conn) return null;
    return self::$conn->brPop($key, $timeout);
  }
  function BLPop($key, $timeout): array|bool|null {
    if(!self::$conn) return null;
    return self::$conn->blPop($key, $timeout);
  }

}