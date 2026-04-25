<?php
namespace Core;

use App\Config\Redis as RedisCfg;

/* Redis */
class Redis extends Base {

  private $name = 'Redis';        // 名称
  private $db = 'default';        // 数据库

  /* 构造函数 */
  public function __construct(string $name='default') {
    $this->db = $name;
  }

  /* 获取连接 */
  public function ReidsConn($name): object|null {
    // 配置
    $config = RedisCfg::config($name);
    // 连接
    $conn = null;
    try{
      $conn = new \Redis();
      $conn->pconnect($config['host'], $config['port'], $config['socket_timeout'], 'redis_pool_unique', 0, 3);
      if($config['password']) $conn->auth($config['password']);
      $conn->select($config['db']);
    }catch (\Exception $e){
      self::Print('[ '.$this->name.' ] RedisConn', $e->getMessage());
    }
    // 返回
    return $conn;
  }

  /* 添加 */
  function Set(string $key, string $val): bool {
    $conn = self::ReidsConn($this->db);
    if($conn===null) return false;
    return $conn->set($key, $val)?true:false;
  }

  /* 自增 */
  function Incr(string $key): string|bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->incr($key);
  }

  /* 自减 */
  function Decr(string $key): string|bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->decr($key);
  }

  /* 获取 */
  function Get(string $key): string|bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->get($key);
  }

  /* 删除 */
  function Del(string ...$key): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->del($key);
  }

  /* 是否存在 */
  function Exist(string $key): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->exists($key);
  }

  /* 设置过期时间(秒) */
  function Expire(string $key, int $ttl): bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->expire($key, $ttl);
  }

  /* 获取过期时间(秒) */
  function Ttl(string $key): int|null {
    $conn = self::ReidsConn($this->db);
    return @$conn->ttl($key);
  }

  /* 获取长度 */
  function StrLen(string $key): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->strlen($key);
  }

  /* 哈希(Hash)-添加 */
  function HSet(string $name, string $key, $val): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->hset($name, $key, $val);
  }

  /* 哈希(Hash)-删除 */
  function HDel(string $name, string ...$key): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->hdel($name, $key);
  }

  /* 哈希(Hash)-获取 */
  function HGet(string $name, string $key): string|bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->hget($name, $key);
  }

  /* 哈希(Hash)-获取全部 */
  function HGetAll(string $name): array|null {
    $conn = self::ReidsConn($this->db);
    return $conn->hgetall($name);
  }

  /* 哈希(Hash)-获取全部值 */
  function HVals(string $name): array|null {
    $conn = self::ReidsConn($this->db);
    return $conn->hvals($name);
  }

  /* 哈希(Hash)-是否存在 */
  function HExist(string $name, string $key): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->hexists($name, $key);
  }

  /* 哈希(Hash)-获取长度 */
  function HLen(string $name): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->hlen($name);
  }

  /* 列表(List)-写入 */
  function LPush(string $key, $val): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->lpush($key, $val);
  }
  function RPush(string $key, $val): int|null {
    $conn = self::ReidsConn($this->db);
    return $conn->rpush($key, $val);
  }

  /* 列表(List)-读取 */
  function LRange($key, $start, $end): array|null {
    $conn = self::ReidsConn($this->db);
    return $conn->lRange($key, $start, $end);
  }
  function LPop($key): string|bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->lPop($key);
  }
  function RPop($key): string|bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->rPop($key);
  }
  function BRPop($key, $timeout): array|bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->brPop($key, $timeout);
  }
  function BLPop($key, $timeout): array|bool|null {
    $conn = self::ReidsConn($this->db);
    return $conn->blPop($key, $timeout);
  }

}