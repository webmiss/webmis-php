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
  public function ReidsConn(): object|null {
    // 配置
    $config = RedisCfg::config($this->db);
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
    $conn = self::ReidsConn();
    if($conn===null) return false;
    return $conn->set($key, $val)?true:false;
  }

  /* 自增 */
  function Incr(string $key): int {
    $conn = self::ReidsConn();
    if($conn===null) return 0;
    $res = $conn->incr($key);
    return $res?:0;
  }

  /* 自减 */
  function Decr(string $key): int {
    $conn = self::ReidsConn();
    if($conn===null) return 0;
    $res = $conn->decr($key);
    return $res?:0;
  }

  /* 获取 */
  function Get(string $key): string|bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->get($key);
    return $res===false?false:$res;
  }

  /* 删除 */
  function Del(string ...$key): bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->del($key);
    return $res!==false;
  }

  /* 是否存在 */
  function Exist(string $key): bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->exists($key);
    return $res?true:false;
  }

  /* 设置过期时间(秒) */
  function Expire(string $key, int $ttl): bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->expire($key, $ttl);
    return $res?true:false;
  }

  /* 获取过期时间(秒) */
  function Ttl(string $key): int {
    $conn = self::ReidsConn();
    if($conn===null) return 0;
    $res = $conn->ttl($key);
    return $res>=0?$res:0;
  }

  /* 获取长度 */
  function StrLen(string $key): int {
    $conn = self::ReidsConn();
    if($conn===null) return 0;
    $res = $conn->strlen($key);
    return $res>=0?$res:0;
  }

  /* 哈希(Hash)-添加 */
  function HSet(string $name, string $key, $val): bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->hset($name, $key, $val);
    return $res?true:false;
  }

  /* 哈希(Hash)-删除 */
  function HDel(string $name, string ...$key): bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->hdel($name, $key);
    return $res>=0?true:false;
  }

  /* 哈希(Hash)-获取 */
  function HGet(string $name, string $key): string {
    $conn = self::ReidsConn();
    if($conn===null) return '';
    $res = $conn->hget($name, $key);
    return $res===false?'':$res;
  }

  /* 哈希(Hash)-获取全部 */
  function HGetAll(string $name): array {
    $conn = self::ReidsConn();
    if($conn===null) return [];
    $res = $conn->hgetall($name);
    return $res?:[];
  }

  /* 哈希(Hash)-获取全部值 */
  function HVals(string $name): array {
    $conn = self::ReidsConn();
    if($conn===null) return [];
    $res = $conn->hvals($name);
    return $res?:[];
  }

  /* 哈希(Hash)-是否存在 */
  function HExist(string $name, string $key): bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->hexists($name, $key);
    return $res?true:false;
  }

  /* 哈希(Hash)-获取长度 */
  function HLen(string $name): int {
    $conn = self::ReidsConn();
    if($conn===null) return 0;
    $res = $conn->hlen($name);
    return $res>=0?$res:0;
  }

  /* 列表(List)-写入 */
  function LPush(string $key, mixed $val): bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->lpush($key, $val);
    return $res?true:false;
  }
  function RPush(string $key, mixed $val): bool {
    $conn = self::ReidsConn();
    if($conn===null) return false;
    $res = $conn->rpush($key, $val);
    return $res?true:false;
  }

  /* 列表(List)-读取 */
  function LRange(string $key, int $start, int $end): array {
    $conn = self::ReidsConn();
    if($conn===null) return [];
    $res = $conn->lRange($key, $start, $end);
    return $res?:[];
  }
  function LPop(string $key): string {
    $conn = self::ReidsConn();
    if($conn===null) return '';
    $res = $conn->lPop($key);
    return $res===false?'':$res;
  }
  function RPop(string $key): string {
    $conn = self::ReidsConn();
    if($conn===null) return '';
    $res = $conn->rPop($key);
    return $res===false?'':$res;
  }
  function BRPop($key, $timeout): array {
    $conn = self::ReidsConn();
    if($conn===null) return [];
    $res = $conn->brPop($key, $timeout);
    return $res?:[];
  }
  function BLPop($key, $timeout): array {
    $conn = self::ReidsConn();
    if($conn===null) return [];
    $res = $conn->blPop($key, $timeout);
    return $res?:[];
  }

}