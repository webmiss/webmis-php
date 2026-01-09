<?php
namespace Core;

use App\Config\Redis as RedisCfg;

/* Redis */
class Redis extends Base {

  private $config = [];         // 配置
  private $conn = null;         // 连接

  /* 获取连接 */
  public function __construct(string $name='default') {
    // 配置
    $this->config = RedisCfg::config($name);
    // 连接
    if(!$this->conn) {
      try{
        $this->conn = new \Redis();
        $this->conn->pconnect($this->config['host'], $this->config['port']); 
        if($this->config['password']) $this->conn->auth($this->config['password']);
        $this->conn->select($this->config['db']);
      }catch (\Exception $e){
        self::Print('[ Redis ]', $e->getMessage());
        return null;
      }
    }
  }
  /* 析构函数 */
  public function __destruct() {
    $this->Close();
  }

  /* 关闭连接 */
  public function Close(): void {
    if($this->conn) {
      $this->conn->close();
      $this->conn = null;
    }
  }

  /* 添加 */
  function Set(string $key, string $val): bool|null {
    if(!$this->conn) return null;
    return $this->conn->set($key, $val);
  }
  /* 自增 */
  function Incr(string $key): string|bool| null {
    if(!$this->conn) return null;
    return $this->conn->incr($key);
  }
  /* 获取 */
  function Gets(string $key): string|bool|null {
    if(!$this->conn) return null;
    return $this->conn->get($key);
  }
  /* 删除 */
  function Del(string ...$key): int|null {
    if(!$this->conn) return null;
    return $this->conn->del($key);
  }
  /* 是否存在 */
  function Exist(string $key): int|null {
    if(!$this->conn) return null;
    return $this->conn->exists($key);
  }
  /* 设置过期时间(秒) */
  function Expire(string $key, int $ttl): bool|null {
    if(!$this->conn) return null;
    return $this->conn->expire($key, $ttl);
  }
  /* 获取过期时间(秒) */
  function Ttl(string $key): int|null {
    if(!$this->conn) return null;
    return @$this->conn->ttl($key);
  }
  /* 获取长度 */
  function StrLen(string $key): int|null {
    if(!$this->conn) return null;
    return $this->conn->strlen($key);
  }

  /* 哈希(Hash)-添加 */
  function HSet(string $name, string $key, $val): int|null {
    if(!$this->conn) return null;
    return $this->conn->hset($name, $key, $val);
  }
  function HMSet(string $name, array $obj): bool|null {
    if(!$this->conn) return null;
    return $this->conn->hmset($name, $obj);
  }
  /* 哈希(Hash)-获取 */
  function HGet(string $name, string $key): string|bool|null {
    if(!$this->conn) return null;
    return $this->conn->hget($name, $key);
  }
  function HMGet(string $name, string $key): array|null {
    if(!$this->conn) return null;
    return $this->conn->hmget($name, $key);
  }
  /* 哈希(Hash)-删除 */
  function HDel(string $name, string ...$key): int|null {
    if(!$this->conn) return null;
    return $this->conn->hdel($name, $key);
  }
  /* 哈希(Hash)-是否存在 */
  function HExist(string $name, string $key): int|null {
    if(!$this->conn) return null;
    return $this->conn->hexists($name, $key);
  }
  /* 哈希(Hash)-Key个数 */
  function HLen(string $name): int|null {
    if(!$this->conn) return null;
    return $this->conn->hlen($name);
  }

  /* 列表(List)-写入 */
  function RPush(string $key, $val): int|null {
    if(!$this->conn) return null;
    return $this->conn->rpush($key, $val);
  }
  function LPush(string $key, $val): int|null {
    if(!$this->conn) return null;
    return $this->conn->lpush($key, $val);
  }

  /* 列表(List)-读取 */
  function LRange($key, $start, $end): array|null {
    if(!$this->conn) return null;
    return $this->conn->lRange($key, $start, $end);
  }
  function RPop($key): string|bool|null {
    if(!$this->conn) return null;
    return $this->conn->rPop($key);
  }
  function LPop($key): string|bool|null {
    if(!$this->conn) return null;
    return $this->conn->lPop($key);
  }
  function BRPop($key, $timeout): array|bool|null {
    if(!$this->conn) return null;
    return $this->conn->brPop($key, $timeout);
  }
  function BLPop($key, $timeout): array|bool|null {
    if(!$this->conn) return null;
    return $this->conn->blPop($key, $timeout);
  }

}